<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/** Pengelolaan master satuan pekerjaan (m³, kg, bh, ...). */
class UnitService
{
    /** @param array{code:string,name:string} $data */
    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    /**
     * Mengubah kode/nama satuan langsung berlaku pada seluruh pekerjaan yang memakainya,
     * karena pekerjaan menyimpan relasi ke satuan, bukan salinan kodenya.
     *
     * @param  array{code:string,name:string}  $data
     */
    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);

        return $unit->fresh();
    }

    /** Satuan yang masih dipakai pekerjaan tidak dapat dihapus agar data pekerjaan tetap utuh. */
    public function delete(Unit $unit): void
    {
        $dipakai = $unit->workItems()->count();

        if ($dipakai > 0) {
            throw ValidationException::withMessages([
                'unit' => "Satuan \"{$unit->code}\" tidak dapat dihapus karena masih dipakai {$dipakai} pekerjaan.",
            ]);
        }

        $unit->delete();
    }
}
