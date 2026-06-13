<x-filament-panels::page>
    @php
        $siswa = auth()->user()->siswa;
        $daftarNilai = $siswa->nilai()->with('guru')->get();
        $ringkasan = \App\Support\NilaiHelper::olahLaporan($daftarNilai);
        $statusAkhir = ($ringkasan['total'] > 0 && $ringkasan['tidak_lulus'] === 0) ? 'LULUS' : 'TIDAK LULUS';
    @endphp

    <x-filament::section>
        <x-slot name="heading">{{ $siswa->user->name }} — {{ $siswa->kelas }} (NIS: {{ $siswa->nis }})</x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Mata Pelajaran</th>
                    <th>Tugas</th><th>UTS</th><th>UAS</th><th>Nilai Akhir</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($daftarNilai as $n)
                <tr class="border-b">
                    <td class="py-2">{{ $n->guru->mata_pelajaran }}</td>
                    <td>{{ $n->nilai_tugas }}</td>
                    <td>{{ $n->nilai_uts }}</td>
                    <td>{{ $n->nilai_uas }}</td>
                    <td class="font-bold">{{ number_format($n->nilai_akhir, 2) }}</td>
                    <td>
                        <span @class([
                            'font-semibold',
                            'text-green-600' => $n->status === 'Lulus',
                            'text-red-600' => $n->status !== 'Lulus',
                        ])>{{ $n->status }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 text-sm space-y-1">
            <div>Rata-rata: <strong>{{ number_format($ringkasan['rata_rata'], 2) }}</strong></div>
            <div>Lulus {{ $ringkasan['lulus'] }} dari {{ $ringkasan['total'] }} mapel</div>
            <div>Status Keseluruhan:
                <span @class([
                    'font-bold',
                    'text-green-600' => $statusAkhir === 'LULUS',
                    'text-red-600' => $statusAkhir !== 'LULUS',
                ])>{{ $statusAkhir }}</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>