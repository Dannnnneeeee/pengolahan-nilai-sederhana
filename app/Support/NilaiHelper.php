<?php
namespace App\Support;
final class NilaiHelper
{
    public const KKM = 70;

    public static function validasiNilai(int $nilai) : bool
    {
        return $nilai >= 0 && $nilai <=100;
    }
    public static function hitungNilaiAkhir( float $tugas, float $uts, float $uas) : float
    {
        return round((0.30 * $tugas) + (0.30 * $uts) + (0.40 * $uas), 2);
    }
    public static function tentukanKelulusan(float $nilaiAkhir):string {
        return $nilaiAkhir >= self::KKM?'Lulus':'Tidak Lulus';
    }
    public static function olahLaporan(iterable $daftarNilai):array
    {
        $total =0; $jumlah= 0.0; $lulus =0;
        foreach ($daftarNilai as $n){
            $total ++;
            $jumlah += (float) $n->nilai_akhir;
            if($n->status ==='Lulus') $lulus++;
        }
        return [
            'total' =>$total,
            'rata_rata'=>$total ? round($jumlah/$total, 2):0,
            'lulus' =>$lulus,
            'tidak_lulus'=>$total-$lulus,
        ];
    }
}
