<?php

use App\Services\ProjectScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periode pelaksanaan pekerjaan berbasis minggu (M-I, M-II, ...), bukan tanggal.
 *
 * - work_items.waktu_mulai/waktu_selesai diganti period_mulai_id/period_selesai_id
 *   yang merujuk ke periode proyek. Data lama dipetakan ke periode yang memuat tanggalnya.
 * - Label periode diseragamkan menjadi "M-I", "M-II", ...
 * - work_plans.target_volume memakai 4 desimal agar pembagian rata (mis. 23,87 / 4 = 5,9675)
 *   tersimpan tanpa selisih pembulatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->foreignId('period_mulai_id')->nullable()->after('harga_pekerjaan')->constrained('periods')->nullOnDelete();
            $table->foreignId('period_selesai_id')->nullable()->after('period_mulai_id')->constrained('periods')->nullOnDelete();
        });

        foreach (DB::table('periods')->get(['id', 'urutan']) as $period) {
            DB::table('periods')->where('id', $period->id)
                ->update(['nama_periode' => 'M-'.ProjectScheduleService::romawi((int) $period->urutan)]);
        }

        foreach (DB::table('work_items')->get(['id', 'project_id', 'waktu_mulai', 'waktu_selesai']) as $item) {
            $periods = DB::table('periods')->where('project_id', $item->project_id)->orderBy('urutan')->get();

            if ($periods->isEmpty()) {
                continue;
            }

            $cari = fn (?string $tanggal) => $tanggal
                ? $periods->first(fn ($p) => $p->tanggal_mulai <= $tanggal && $p->tanggal_selesai >= $tanggal)
                : null;

            $mulai = $cari($item->waktu_mulai) ?? $periods->first();
            $selesai = $cari($item->waktu_selesai) ?? $periods->last();

            // Rentang juga harus memuat seluruh rencana yang sudah tersimpan.
            $urutanRencana = DB::table('work_plans')
                ->join('periods', 'periods.id', '=', 'work_plans.period_id')
                ->where('work_plans.work_item_id', $item->id)
                ->selectRaw('MIN(periods.urutan) as awal, MAX(periods.urutan) as akhir')
                ->first();

            if ($urutanRencana?->awal !== null && $urutanRencana->awal < $mulai->urutan) {
                $mulai = $periods->firstWhere('urutan', $urutanRencana->awal);
            }

            if ($urutanRencana?->akhir !== null && $urutanRencana->akhir > $selesai->urutan) {
                $selesai = $periods->firstWhere('urutan', $urutanRencana->akhir);
            }

            if ($selesai->urutan < $mulai->urutan) {
                $selesai = $mulai;
            }

            DB::table('work_items')->where('id', $item->id)->update([
                'period_mulai_id' => $mulai->id,
                'period_selesai_id' => $selesai->id,
            ]);
        }

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn(['waktu_mulai', 'waktu_selesai']);
        });

        Schema::table('work_plans', function (Blueprint $table) {
            $table->decimal('target_volume', 15, 4)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_plans', function (Blueprint $table) {
            $table->decimal('target_volume', 15, 3)->default(0)->change();
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->date('waktu_mulai')->nullable()->after('harga_pekerjaan');
            $table->date('waktu_selesai')->nullable()->after('waktu_mulai');
        });

        foreach (DB::table('work_items')->get(['id', 'period_mulai_id', 'period_selesai_id']) as $item) {
            DB::table('work_items')->where('id', $item->id)->update([
                'waktu_mulai' => DB::table('periods')->where('id', $item->period_mulai_id)->value('tanggal_mulai'),
                'waktu_selesai' => DB::table('periods')->where('id', $item->period_selesai_id)->value('tanggal_selesai'),
            ]);
        }

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('period_mulai_id');
            $table->dropConstrainedForeignId('period_selesai_id');
        });

        foreach (DB::table('periods')->get(['id', 'urutan']) as $period) {
            DB::table('periods')->where('id', $period->id)
                ->update(['nama_periode' => 'Minggu '.ProjectScheduleService::romawi((int) $period->urutan)]);
        }
    }
};
