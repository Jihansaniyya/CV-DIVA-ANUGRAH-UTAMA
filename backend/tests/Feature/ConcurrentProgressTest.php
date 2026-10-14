<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\ProgressDetail;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permintaan bersamaan yang sungguhan: setiap permintaan dijalankan proses PHP terpisah dengan koneksi
 * database sendiri dan dimulai pada saat yang sama. Data disimpan permanen (tanpa transaksi RefreshDatabase)
 * agar terlihat oleh proses lain; skema dibersihkan lagi setelah setiap pengujian.
 */
class ConcurrentProgressTest extends TestCase
{
    /** Lama proses pertama menahan transaksinya setelah membuat laporan. */
    private const JEDA_MS = 1500;

    /** @var array{project: Project, qs: User, items: array<int,WorkItem>} */
    private array $konteks;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->seedMasterData();

        $this->konteks = $this->proyekContoh();
        $project = $this->konteks['project'];

        // Jadwal dimulai dua minggu lalu agar ada beberapa tanggal yang sudah lewat untuk dilaporkan.
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $this->actingAs($admin)->putJson("/api/projects/{$project->id}", [
            'tanggal_mulai' => now()->subDays(14)->toDateString(),
            'tanggal_selesai' => now()->addDays(14)->toDateString(),
        ])->assertOk();
        $this->konteks['project'] = $project->fresh();
        $this->susunRencana($this->konteks);

        $this->token = $this->konteks['qs']->createToken('uji-bersamaan')->plainTextToken;
    }

    protected function tearDown(): void
    {
        // Data pengujian ini tidak dibungkus transaksi, jadi skema dikosongkan agar tidak terbawa ke pengujian lain.
        $this->artisan('migrate:fresh');

        parent::tearDown();
    }

    private function tanggal(int $hariSetelahMulai): string
    {
        return $this->konteks['project']->tanggal_mulai->addDays($hariSetelahMulai)->toDateString();
    }

    private function payload(int $item, float $volume, string $tanggal, ?string $kunci = null): array
    {
        return [
            'project_id' => $this->konteks['project']->id,
            'tanggal_laporan' => $tanggal,
            'status' => 'DRAFT',
            'client_request_id' => $kunci ?? (string) Str::uuid(),
            'details' => [['work_item_id' => $this->konteks['items'][$item]->id, 'volume_realisasi' => $volume]],
        ];
    }

    /**
     * Jalankan beberapa permintaan sekaligus di proses terpisah.
     *
     * @param  list<array<string,mixed>>  $payloads
     * @return list<array{status:int, body:array<string,mixed>|null}>
     */
    private function bersamaan(array $payloads): array
    {
        $mulai = microtime(true) + 4; // waktu cukup untuk semua proses selesai booting
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => config('database.default'),
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => (string) config('database.connections.mysql.database'),
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        $proses = [];

        foreach ($payloads as $payload) {
            $argumen = json_encode(['token' => $this->token, 'payload' => $payload, 'mulai' => $mulai, 'jeda_ms' => self::JEDA_MS]);
            $handle = proc_open(
                [PHP_BINARY, base_path('tests/Concurrency/kirim_progres.php'), $argumen],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $env,
            );
            $this->assertIsResource($handle);
            $proses[] = [$handle, $pipes];
        }

        $hasil = [];

        foreach ($proses as [$handle, $pipes]) {
            $keluaran = stream_get_contents($pipes[1]);
            $galat = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($handle);

            $data = json_decode((string) $keluaran, true);
            $this->assertIsArray($data, "Proses anak gagal: {$keluaran} {$galat}");
            $hasil[] = $data;
        }

        return $hasil;
    }

    /** @param list<array{status:int}> $hasil */
    private function kodeStatus(array $hasil): array
    {
        $status = array_column($hasil, 'status');
        sort($status);

        return $status;
    }

    public function test_pekerjaan_dan_tanggal_sama_dari_dua_permintaan_bersamaan_hanya_tersimpan_sekali(): void
    {
        $hasil = $this->bersamaan([
            $this->payload(0, 10, $this->tanggal(0)),
            $this->payload(0, 15, $this->tanggal(0)),
        ]);

        $this->assertSame([201, 422], $this->kodeStatus($hasil), json_encode($hasil));
        $ditolak = collect($hasil)->firstWhere('status', 422);
        $this->assertStringContainsString('sudah tercatat', json_encode($ditolak['body']['errors']['details']));
        $this->assertSame(1, ProgressReport::count());
        $this->assertSame(1, ProgressDetail::count());
    }

    public function test_permintaan_yang_sama_dikirim_dua_kali_bersamaan_hanya_diproses_sekali(): void
    {
        $kunci = (string) Str::uuid();
        $hasil = $this->bersamaan([
            $this->payload(0, 30, $this->tanggal(0), $kunci),
            $this->payload(0, 30, $this->tanggal(0), $kunci),
        ]);

        // Permintaan kedua menunggu kunci, lalu memakai laporan yang dibuat permintaan pertama (bukan 500).
        $this->assertSame([200, 201], $this->kodeStatus($hasil), json_encode($hasil));
        $this->assertSame($hasil[0]['body']['data']['id'], $hasil[1]['body']['data']['id']);
        $this->assertSame(1, ProgressReport::count());
        $this->assertEqualsWithDelta(30.0, (float) ProgressDetail::sum('volume_realisasi'), 0.0001);

        // Percobaan ulang setelah respons hilang juga tidak menambah realisasi.
        $ulang = $this->bersamaan([$this->payload(0, 30, $this->tanggal(0), $kunci)]);
        $this->assertSame(200, $ulang[0]['status']);
        $this->assertEqualsWithDelta(30.0, (float) ProgressDetail::sum('volume_realisasi'), 0.0001);
    }

    public function test_dua_draf_bersamaan_tidak_dapat_memakai_sisa_volume_yang_sama(): void
    {
        // Volume galian 100: masing-masing 60 sah sendiri-sendiri, tetapi tidak keduanya.
        $hasil = $this->bersamaan([
            $this->payload(0, 60, $this->tanggal(0)),
            $this->payload(0, 60, $this->tanggal(1)),
        ]);

        $this->assertSame([201, 422], $this->kodeStatus($hasil), json_encode($hasil));
        $ditolak = collect($hasil)->firstWhere('status', 422);
        $this->assertStringContainsString('melebihi sisa volume', json_encode($ditolak['body']['errors']['details']));
        $this->assertEqualsWithDelta(60.0, (float) ProgressDetail::sum('volume_realisasi'), 0.0001);
    }

    public function test_laporan_sah_untuk_pekerjaan_berbeda_tetap_tersimpan_bersamaan(): void
    {
        $hasil = $this->bersamaan([
            $this->payload(0, 10, $this->tanggal(0)),
            $this->payload(1, 20, $this->tanggal(0)),
        ]);

        $this->assertSame([201, 201], $this->kodeStatus($hasil), json_encode($hasil));
        $this->assertSame(2, ProgressReport::count());
    }
}
