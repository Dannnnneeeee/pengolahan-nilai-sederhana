<?php

namespace App\Support;

use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;

class RaporService
{
    public static function unduh(Siswa $siswa)
    {
        $daftarNilai = $siswa->nilai()->with('guru')->get();
        $ringkasan = NilaiHelper::olahLaporan($daftarNilai);

        // LULUS hanya bila semua mapel tuntas
        $statusAkhir = ($ringkasan['total'] > 0 && $ringkasan['tidak_lulus'] === 0)
            ? 'LULUS' : 'TIDAK LULUS';

        $pdf = Pdf::loadView('rapor', compact('siswa', 'daftarNilai', 'ringkasan', 'statusAkhir'));

        // streamDownload: hindari error "Malformed UTF-8" saat dipanggil dari action Livewire
        return response()->streamDownload(
            fn () => print($pdf->output()),
            "rapor-{$siswa->nis}.pdf"
        );
    }
}