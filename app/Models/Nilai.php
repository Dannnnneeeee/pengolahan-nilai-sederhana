<?php

namespace App\Models;

use App\Support\NilaiHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nilai extends Model
{
    protected $table ='nilai';
    protected $fillable = ['siswa_id', 'guru_id','nilai_tugas','nilai_uts', 'nilai_uas', 'nilai_akhir', 'status'];
     protected static function booted(): void
    {
        static::saving(function (Nilai $nilai) {
            foreach ([$nilai->nilai_tugas, $nilai->nilai_uts, $nilai->nilai_uas] as $n) {
                if (! NilaiHelper::validasiNilai((int) $n)) {
                    throw new \InvalidArgumentException('Nilai harus 0–100.');
                }
            }
            $nilai->nilai_akhir = NilaiHelper::hitungNilaiAkhir(
                $nilai->nilai_tugas, $nilai->nilai_uts, $nilai->nilai_uas
            );
            $nilai->status = NilaiHelper::tentukanKelulusan($nilai->nilai_akhir);
        });
    }
     public function siswa(): BelongsTo { return $this->belongsTo(Siswa::class); }
    public function guru(): BelongsTo  { return $this->belongsTo(Guru::class); }
}
