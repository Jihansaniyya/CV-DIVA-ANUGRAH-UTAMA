<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_admin_dapat_mengubah_satuan_dan_kode_unik_tetap_dijaga(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $ton = Unit::where('code', 'ton')->firstOrFail();

        $this->actingAs($admin)->putJson("/api/units/{$ton->id}", ['code' => 'TON', 'name' => 'Ton Metrik'])
            ->assertOk()
            ->assertJsonPath('data.code', 'ton')
            ->assertJsonPath('data.name', 'ton metrik');

        $this->actingAs($admin)->putJson("/api/units/{$ton->id}", ['code' => 'kg', 'name' => 'ton'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_satuan_yang_dipakai_pekerjaan_tidak_dapat_dihapus(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $this->proyekContoh(); // Pekerjaan memakai satuan m3 dan kg.

        $m3 = Unit::where('code', 'm3')->firstOrFail();
        $ton = Unit::where('code', 'ton')->firstOrFail();

        $this->actingAs($admin)->getJson('/api/units')
            ->assertOk()
            ->assertJsonFragment(['code' => 'm3', 'jumlah_pekerjaan' => 1]);

        $this->actingAs($admin)->deleteJson("/api/units/{$m3->id}")
            ->assertStatus(422)->assertJsonValidationErrors(['unit']);
        $this->assertModelExists($m3);

        $this->actingAs($admin)->deleteJson("/api/units/{$ton->id}")->assertOk();
        $this->assertModelMissing($ton);
    }

    public function test_selain_admin_tidak_dapat_mengubah_atau_menghapus_satuan(): void
    {
        $ton = Unit::where('code', 'ton')->firstOrFail();

        foreach ([RoleCode::QS, RoleCode::KONTRAKTOR] as $peran) {
            $user = $this->userDenganPeran($peran);

            $this->actingAs($user)->putJson("/api/units/{$ton->id}", ['code' => 'tn', 'name' => 'ton'])->assertForbidden();
            $this->actingAs($user)->deleteJson("/api/units/{$ton->id}")->assertForbidden();
        }

        $this->assertModelExists($ton);
    }
}
