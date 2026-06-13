<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #222; }
    h2 { text-align: center; margin: 0; }
    .sub { text-align: center; font-size: 11px; margin: 2px 0 16px; }
    .info td { padding: 2px 4px; }
    table.nilai { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.nilai th, table.nilai td { border: 1px solid #555; padding: 6px 8px; text-align: center; }
    table.nilai th { background: #eee; }
    .lulus { color: #137333; font-weight: bold; }
    .tidak { color: #b00020; font-weight: bold; }
    .ringkas { margin-top: 16px; width: 100%; }
    .ringkas td { padding: 3px 4px; }
</style>
</head>
<body>
    <h2>LAPORAN HASIL BELAJAR SISWA</h2>
    <div class="sub">Sistem Pengolahan Nilai &mdash; SD</div>

    <table class="info">
        <tr><td width="80">NIS</td><td width="10">:</td><td>{{ $siswa->nis }}</td></tr>
        <tr><td>Nama</td><td>:</td><td>{{ $siswa->user->name }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ $siswa->kelas }}</td></tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th>No</th><th>Mata Pelajaran</th><th>Tugas</th><th>UTS</th><th>UAS</th>
                <th>Nilai Akhir</th><th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($daftarNilai as $i => $n)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td style="text-align:left">{{ $n->guru->mata_pelajaran }}</td>
                <td>{{ $n->nilai_tugas }}</td>
                <td>{{ $n->nilai_uts }}</td>
                <td>{{ $n->nilai_uas }}</td>
                <td>{{ number_format($n->nilai_akhir, 2) }}</td>
                <td class="{{ $n->status === 'Lulus' ? 'lulus' : 'tidak' }}">{{ $n->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="ringkas">
        <tr><td width="160">Jumlah Mata Pelajaran</td><td width="10">:</td><td>{{ $ringkasan['total'] }}</td></tr>
        <tr><td>Rata-rata Nilai</td><td>:</td><td>{{ number_format($ringkasan['rata_rata'], 2) }}</td></tr>
        <tr><td>Mapel Lulus</td><td>:</td><td>{{ $ringkasan['lulus'] }} dari {{ $ringkasan['total'] }}</td></tr>
        <tr><td>Status Keseluruhan</td><td>:</td>
            <td class="{{ $statusAkhir === 'LULUS' ? 'lulus' : 'tidak' }}">{{ $statusAkhir }}</td></tr>
    </table>
</body>
</html>