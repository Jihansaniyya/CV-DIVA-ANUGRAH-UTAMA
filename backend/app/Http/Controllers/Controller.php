<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /** Batas jumlah data per halaman agar satu permintaan tidak memuat seluruh tabel sekaligus. */
    public const PER_PAGE_MAKS = 100;

    /** Validasi parameter `page` dan `per_page` daftar berpaginasi. */
    protected function perPage(Request $request, int $bawaan = 10): int
    {
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::PER_PAGE_MAKS],
        ], [
            'per_page.max' => 'Jumlah data per halaman maksimal '.self::PER_PAGE_MAKS.'.',
            'per_page.*' => 'Jumlah data per halaman tidak valid.',
            'page.*' => 'Nomor halaman tidak valid.',
        ]);

        return $request->integer('per_page', $bawaan);
    }
}
