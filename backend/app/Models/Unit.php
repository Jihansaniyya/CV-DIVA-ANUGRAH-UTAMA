<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    /**
     * Kode satuan untuk ditampilkan: angka pangkat 2/3 di akhir kode ditulis superskrip
     * (m2 → m², m3 → m³). Kode tersimpan tetap apa adanya agar input dan pencarian tidak berubah.
     */
    public static function tampilan(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        return preg_replace_callback('/^([a-zA-Z]+)([23])$/', fn ($m) => $m[1].($m[2] === '2' ? '²' : '³'), $code);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }
}
