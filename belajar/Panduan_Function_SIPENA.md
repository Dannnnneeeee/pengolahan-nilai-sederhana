# PANDUAN MEMBUAT FUNCTION DI SIPENA
## Cara buat, variabelnya apa, nyambunginnya gimana, nampilinnya gimana

---

## KONSEP DASAR YANG HARUS KAMU PEGANG

Function itu seperti mesin:
- Kamu kasih sesuatu ke dalam (INPUT = parameter)
- Dia olah di dalamnya
- Dia keluarkan hasil (OUTPUT = return)

Function tidak mengambil data sendiri dari database.
Function tidak menampilkan apapun sendiri.
Dia cuma: TERIMA → OLAH → KEMBALIKAN.

Yang mengambil data dari database: MODEL / QUERY (sebelum memanggil function)
Yang menampilkan hasil: BLADE / TABEL FILAMENT (setelah function selesai)

Jadi alurnya selalu:
AMBIL DATA → KASIH KE FUNCTION → TAMPILKAN HASIL

---

## LANGKAH 1: TENTUKAN VARIABEL (PARAMETER)

Sebelum nulis kode apapun, tanya diri sendiri:
"Untuk mengerjakan ini, function perlu tahu apa?"

Jawabannya = parameter function.

### Contoh cara berpikir:

Fitur: hitung nilai akhir
→ "Untuk menghitung nilai akhir, function perlu tahu apa?"
→ "Perlu tahu nilai tugas, nilai UTS, dan nilai UAS"
→ Parameter: $tugas, $uts, $uas (tiga angka)

Fitur: tentukan kelulusan
→ "Untuk menentukan lulus/tidak, function perlu tahu apa?"
→ "Perlu tahu nilai akhirnya saja"
→ Parameter: $nilaiAkhir (satu angka)

Fitur: tentukan predikat
→ "Untuk menentukan predikat, function perlu tahu apa?"
→ "Perlu tahu nilai akhirnya saja"
→ Parameter: $nilaiAkhir (satu angka)

Fitur: hitung rata-rata kelas
→ "Untuk menghitung rata-rata kelas, function perlu tahu apa?"
→ "Perlu tahu semua nilai di kelas itu (banyak baris)"
→ Parameter: $daftarNilai (koleksi / banyak data)

Fitur: hitung peringkat siswa
→ "Untuk menentukan peringkat, function perlu tahu apa?"
→ "Perlu tahu siswa yang dicari, DAN nilai semua siswa sekelas"
→ Parameter: $siswa, $daftarSiswa (dua parameter)

### Aturan tipe parameter:

Satu angka hasil perhitungan  → float
Satu angka yang pasti bulat   → int
Teks/kata-kata                → string
Banyak data/daftar            → Collection (dari Eloquent) atau iterable
Satu objek model              → \App\Models\NamaModel

---

## LANGKAH 2: TENTUKAN RETURN (OUTPUT)

Tanya: "Function ini akan mengeluarkan apa?"

Lulus/Tidak Lulus (teks)      → return string
Nilai akhir (bisa desimal)    → return float
Jumlah (pasti bulat)          → return int
Predikat (teks)               → return string
Banyak informasi sekaligus    → return array
Ya/Tidak                      → return bool

### Contoh:
- tentukanKelulusan → mengeluarkan "Lulus" atau "Tidak Lulus" → return string
- hitungNilaiAkhir  → mengeluarkan 84.90 → return float
- hitungPeringkat   → mengeluarkan 1, 2, 3 → return int
- olahLaporan       → mengeluarkan total, rata-rata, lulus, tidak lulus → return array
- validasiNilai     → mengeluarkan true/false → return bool

---

## LANGKAH 3: TULIS FUNCTION DI NILAIHELPER

Semua function logika ditulis di satu tempat:
File: app/Support/NilaiHelper.php

### Template dasar function:
```php
public static function namaFunction(tipe $parameter): tipeReturn
{
    // olah parameter
    return hasil;
}
```

### Contoh nyata satu per satu:

#### Function 1 — satu input, satu output langsung
```php
// Untuk menentukan predikat dari nilai akhir
public static function tentukanPredikat(float $nilaiAkhir): string
{
    return match (true) {
        $nilaiAkhir >= 90 => 'Sangat Baik',
        $nilaiAkhir >= 80 => 'Baik',
        $nilaiAkhir >= 70 => 'Cukup',
        default           => 'Kurang',
    };
}
```
Parameter: $nilaiAkhir (float) — satu angka yang sudah ada
Return: string — langsung jawab tanpa perlu data lain

#### Function 2 — tiga input, dihitung dulu
```php
// Untuk menghitung nilai akhir dari tiga komponen
public static function hitungNilaiAkhir(float $tugas, float $uts, float $uas): float
{
    return round((0.30 * $tugas) + (0.30 * $uts) + (0.40 * $uas), 2);
}
```
Parameter: $tugas, $uts, $uas (tiga angka terpisah)
Return: float — hasil perhitungan

#### Function 3 — input koleksi, pakai loop
```php
// Untuk mengolah ringkasan dari banyak nilai
public static function olahLaporan(iterable $daftarNilai): array
{
    $total = 0;
    $jumlah = 0.0;
    $lulus = 0;

    // loop karena datanya banyak
    foreach ($daftarNilai as $n) {
        $total++;
        $jumlah += (float) $n->nilai_akhir;
        if ($n->status === 'Lulus') $lulus++;
    }

    return [
        'total'       => $total,
        'rata_rata'   => $total ? round($jumlah / $total, 2) : 0,
        'lulus'       => $lulus,
        'tidak_lulus' => $total - $lulus,
    ];
}
```
Parameter: $daftarNilai (koleksi) — banyak baris
Return: array — banyak informasi sekaligus
Ada foreach: karena perlu melihat tiap baris satu per satu

#### Function 4 — input koleksi, pakai sorting
```php
// Untuk mencari nilai tertinggi dari semua mapel siswa
public static function nilaiTertinggi(\Illuminate\Support\Collection $daftarNilai): array
{
    // tidak perlu foreach manual, Collection punya method sorting
    $tertinggi = $daftarNilai->sortByDesc('nilai_akhir')->first();

    return [
        'mapel' => $tertinggi?->guru->mata_pelajaran ?? '-',
        'nilai' => $tertinggi?->nilai_akhir ?? 0,
    ];
}
```
Parameter: Collection (bukan iterable biasa, karena pakai method Collection)
Return: array — nama mapel + angkanya

#### Function 5 — dua input berbeda jenis
```php
// Untuk menghitung peringkat satu siswa di antara semua siswa sekelas
public static function hitungPeringkat(
    \App\Models\Siswa $siswa,
    \Illuminate\Support\Collection $daftarSiswa
): int {
    $posisi = $daftarSiswa
        ->sortByDesc(fn ($s) => $s->nilai->avg('nilai_akhir'))
        ->values()
        ->search(fn ($s) => $s->id === $siswa->id);

    return ($posisi !== false) ? $posisi + 1 : 0;
}
```
Parameter pertama: $siswa (objek model) — siswa yang dicari peringkatnya
Parameter kedua: $daftarSiswa (Collection) — semua siswa sekelas untuk dibandingkan
Return: int — nomor peringkat

---

## LANGKAH 4: NYAMBUNGINNYA (cara memanggil function)

Ada DUA tempat function dipanggil, tergantung jenisnya:

### Cara A — Dipanggil di event saving (untuk yang disimpan)

Dipakai kalau: hasilnya perlu disimpan ke database
Syarat: inputnya cuma dari baris itu sendiri (bukan data baris lain)

File: app/Models/Nilai.php
```php
protected static function booted(): void
{
    static::saving(function (Nilai $nilai) {
        // validasi dulu
        foreach ([$nilai->nilai_tugas, $nilai->nilai_uts, $nilai->nilai_uas] as $n) {
            if (! NilaiHelper::validasiNilai((int) $n)) {
                throw new \InvalidArgumentException('Nilai harus 0-100.');
            }
        }

        // hitung dan simpan — panggil function, hasilnya langsung assign ke kolom
        $nilai->nilai_akhir = NilaiHelper::hitungNilaiAkhir(
            $nilai->nilai_tugas,
            $nilai->nilai_uts,
            $nilai->nilai_uas
        );
        $nilai->status   = NilaiHelper::tentukanKelulusan($nilai->nilai_akhir);
        $nilai->predikat = NilaiHelper::tentukanPredikat($nilai->nilai_akhir);
    });
}
```

Yang terjadi: setiap kali data nilai disimpan (baik create maupun update),
event saving jalan otomatis, memanggil function-function di NilaiHelper,
dan hasilnya langsung diisikan ke kolom sebelum data masuk database.

Cara baca kodenya:
$nilai->nilai_akhir = NilaiHelper::hitungNilaiAkhir(...);
→ "kolom nilai_akhir diisi dengan hasil memanggil function hitungNilaiAkhir"
→ NilaiHelper = nama class helper
→ :: = cara panggil static method
→ hitungNilaiAkhir(...) = nama function + kasih parameter

### Cara B — Dipanggil saat tampil (untuk yang tidak disimpan)

Dipakai kalau: hasilnya bergantung pada perbandingan banyak data
Tempat: di blok @php di blade, atau di method class Page

#### Di halaman blade:
```php
@php
    // 1. ambil data dulu
    $siswa = auth()->user()->siswa;
    $daftarNilai = $siswa->nilai()->with('guru')->get();

    // 2. panggil function, simpan hasilnya ke variabel PHP biasa
    $ringkasan = \App\Support\NilaiHelper::olahLaporan($daftarNilai);
    $tertinggi = \App\Support\NilaiHelper::nilaiTertinggi($daftarNilai);
    $terendah  = \App\Support\NilaiHelper::nilaiTerendah($daftarNilai);

    // untuk peringkat, ambil data tambahan dulu
    $semuaSiswaSeKelas = \App\Models\Siswa::where('kelas', $siswa->kelas)
        ->with('nilai')->get();
    $peringkat = \App\Support\NilaiHelper::hitungPeringkat($siswa, $semuaSiswaSeKelas);
@endphp
```

Cara baca kodenya:
$ringkasan = \App\Support\NilaiHelper::olahLaporan($daftarNilai);
→ "variabel $ringkasan diisi dengan hasil memanggil olahLaporan"
→ kasih $daftarNilai sebagai input (yang sudah diambil dari database di atas)
→ hasilnya array, bisa diakses dengan $ringkasan['rata_rata'], $ringkasan['lulus'], dst

#### Di class Page Filament:
```php
public function getRekapData(): array
{
    // 1. ambil data
    $daftarKelas = \App\Models\Siswa::distinct()->pluck('kelas');

    $rekap = [];
    foreach ($daftarKelas as $kelas) {
        $nilaiKelas = \App\Models\Nilai::whereHas('siswa',
            fn ($q) => $q->where('kelas', $kelas))->get();

        // 2. panggil function
        $ringkasan = \App\Support\NilaiHelper::olahLaporan($nilaiKelas);

        // 3. simpan ke array untuk dikirim ke view
        $rekap[] = [
            'kelas'     => $kelas,
            'rata_rata' => $ringkasan['rata_rata'],
            'lulus'     => $ringkasan['lulus'],
        ];
    }

    return $rekap;
}
```

---

## LANGKAH 5: NAMPILINNYA (di blade / tabel Filament)

### Cara A — Tampil di kolom tabel Filament

Untuk data yang disimpan di kolom database, tinggal tambahkan TextColumn:
File: app/Filament/Resources/NilaiResource.php

```php
// tampil teks biasa
Tables\Columns\TextColumn::make('nilai_akhir')
    ->label('Nilai Akhir')
    ->sortable(),

// tampil dengan badge berwarna
Tables\Columns\TextColumn::make('status')->badge()
    ->color(fn ($state) => $state === 'Lulus' ? 'success' : 'danger'),

// tampil predikat dengan badge berwarna berbeda-beda
Tables\Columns\TextColumn::make('predikat')->badge()
    ->color(fn ($state) => match ($state) {
        'Sangat Baik' => 'success',
        'Baik'        => 'info',
        'Cukup'       => 'warning',
        default       => 'danger',
    }),
```

make('nama_kolom') → nama kolom di database yang mau ditampilkan
->label('Teks') → judul kolom di tabel
->badge() → tampilkan sebagai pill/badge berwarna
->color(...) → tentukan warna berdasarkan isi kolom
->sortable() → bisa diklik untuk mengurutkan

### Cara B — Tampil di blade halaman

Setelah variabel dibuat di @php, langsung pakai di HTML:

```blade
{{-- tampil satu nilai --}}
<div>Nilai Akhir: {{ number_format($n->nilai_akhir, 2) }}</div>

{{-- tampil dari array (hasil olahLaporan) --}}
<div>Rata-rata: {{ number_format($ringkasan['rata_rata'], 2) }}</div>
<div>Lulus: {{ $ringkasan['lulus'] }} dari {{ $ringkasan['total'] }}</div>

{{-- tampil dengan kondisi warna --}}
<span @class([
    'font-bold',
    'text-green-600' => $n->status === 'Lulus',
    'text-red-600'   => $n->status !== 'Lulus',
])>{{ $n->status }}</span>

{{-- tampil loop dari koleksi --}}
@foreach ($daftarNilai as $n)
    <tr>
        <td>{{ $n->guru->mata_pelajaran }}</td>
        <td>{{ $n->nilai_tugas }}</td>
        <td>{{ number_format($n->nilai_akhir, 2) }}</td>
    </tr>
@endforeach

{{-- tampil dari method class Page --}}
@foreach ($this->getRekapData() as $r)
    <tr>
        <td>{{ $r['kelas'] }}</td>
        <td>{{ $r['rata_rata'] }}</td>
    </tr>
@endforeach
```

{{ }} = tampilkan variabel di blade (dengan escape HTML, aman)
@class([]) = tambah class CSS kondisional
@foreach = loop koleksi
$this->namaMethod() = panggil method dari class Page yang sama

---

## RINGKASAN ALUR LENGKAP

Berikut alur lengkap dari "ada fitur baru" sampai "muncul di layar":

```
1. PIKIR
   "Fitur ini butuh tahu apa?" → tentukan parameter
   "Fitur ini keluarkan apa?" → tentukan return type
   "Inputnya satu atau banyak?" → tentukan simple/kompleks

2. TULIS FUNCTION
   File: app/Support/NilaiHelper.php
   public static function namaFunction(tipe $param): tipeReturn { ... }

3. NYAMBUNGIN
   Kalau hasilnya disimpan (simple):
   → app/Models/Nilai.php di dalam static::saving(...)
   → $nilai->kolom = NilaiHelper::namaFunction($nilai->kolom_lain);

   Kalau hasilnya ditampilkan saja (kompleks):
   → di blade @php atau method Page
   → $variabel = NilaiHelper::namaFunction($dataYangSudahDiambil);

4. NAMPILIN
   Kalau disimpan ke kolom → tambah TextColumn di Resource
   Kalau dihitung saat tampil → pakai {{ $variabel }} di blade
```

---

## CONTOH KASUS BARU — LATIHAN

Coba kerjakan sendiri sebelum lihat jawabannya.

### Kasus: "Tambahkan keterangan remedial — siswa yang nilai akhirnya < 70
###         mendapat keterangan 'Perlu Remedial', yang >= 70 'Tidak Perlu Remedial'"

Sebelum nulis kode, jawab dulu:
1. Parameternya apa?
2. Return-nya apa (tipe apa)?
3. Dipanggil di mana (saving atau saat tampil)?
4. Ditampilkan di mana?

Jawaban:
1. $nilaiAkhir (float) — satu angka, cukup nilai akhir saja
2. string — "Perlu Remedial" atau "Tidak Perlu Remedial"
3. Di saving — hasilnya perlu disimpan, dan bergantung pada baris itu sendiri
4. Kolom tabel di NilaiResource

Kodenya:

Di NilaiHelper:
```php
public static function tentukanRemedial(float $nilaiAkhir): string
{
    return $nilaiAkhir < 70 ? 'Perlu Remedial' : 'Tidak Perlu Remedial';
}
```

Di migration nilais, tambah kolom:
```php
$table->string('remedial')->nullable()->after('predikat');
```

Di $fillable model Nilai, tambah 'remedial'.

Di event saving model Nilai, tambah satu baris:
```php
$nilai->remedial = NilaiHelper::tentukanRemedial($nilai->nilai_akhir);
```

Di tabel NilaiResource, tambah kolom:
```php
Tables\Columns\TextColumn::make('remedial')->badge()
    ->color(fn ($state) => $state === 'Perlu Remedial' ? 'danger' : 'success'),
```

Jalankan: php artisan migrate:fresh --seed

Selesai. Semua mengikuti pola yang sama.

---

## POLA YANG SELALU BERULANG

Apapun fiturnya, kamu akan selalu menemukan pola ini:

### Pola 1 — Function sederhana (disimpan)
```
NilaiHelper::namaFunction($satuAngka): string/float/int
    ↓ dipanggil di
Model Nilai booted() → saving()
    ↓ hasilnya disimpan ke kolom
Ditampilkan di TextColumn make('nama_kolom')
```

### Pola 2 — Function kompleks (ditampilkan)
```
NilaiHelper::namaFunction($koleksi): array/int
    ↓ dipanggil di
blade @php atau method Page (setelah query data)
    ↓ hasilnya disimpan ke variabel PHP
Ditampilkan di {{ $variabel }} atau {{ $variabel['key'] }}
```

Dua pola ini menyelesaikan 90% fitur tambahan yang mungkin muncul.
Yang berubah hanya: nama function, isi logikanya, dan nama variabelnya.
Strukturnya selalu sama.
