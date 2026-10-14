<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Closure;

/** QS penanggung jawab proyek harus akun berperan QS yang masih aktif. */
trait ValidatesQsPenanggungJawab
{
    /**
     * @param  int|null  $qsSaatIni  QS proyek sebelum diubah; nilai yang tidak diganti tidak divalidasi ulang
     *                               agar Admin tetap dapat mengubah data lain walau akun QS tersebut dinonaktifkan.
     */
    protected function qsRule(?int $qsSaatIni = null): array
    {
        return [
            'nullable',
            'integer',
            function (string $attribute, mixed $value, Closure $fail) use ($qsSaatIni) {
                if ($qsSaatIni !== null && (int) $value === $qsSaatIni) {
                    return;
                }

                $user = User::with('role')->find($value);

                if ($user === null || ! $user->isQs()) {
                    $fail('QS penanggung jawab harus pengguna dengan peran QS.');
                } elseif (! $user->is_active) {
                    $fail('Akun QS yang dipilih sedang nonaktif. Pilih akun QS yang aktif.');
                }
            },
        ];
    }
}
