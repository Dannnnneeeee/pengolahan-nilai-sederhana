# CATATAN FITUR TAMBAHAN SIPENA
## Prediksi & Implementasi untuk Ujikom

Dokumen ini berisi semua fitur tambahan yang sudah kita diskusikan dan prediksi
kemungkinan keluar saat ujikom. Tiap fitur berisi: penjelasan, rancangan logika,
dan kode lengkap yang tinggal diimplementasikan.

---

## PRINSIP DASAR (baca ini dulu sebelum implementasi apapun)

Setiap fitur tambahan, jawab 4 pertanyaan ini dulu:

1. Fitur ini menghitung sesuatu? → tulis function di NilaiHelper
2. Inputnya satu angka atau koleksi banyak data?
   - Satu angka → function sederhana, bisa dipanggil di event saving
   - Koleksi → function kompleks, dipanggil saat tampil
3. Hasilnya perlu disimpan ke database?
   - Bergantung pada baris itu sendiri → simpan (via saving)
   - Bergantung pada perbandingan dengan baris lain → hitung saat tampil
4. Ditampilkan di mana? → kolom tabel, halaman, atau rapor

---

## FITUR 1 — PREDIKAT NILAI
### Kemungkinan keluar: SANGAT TINGGI

Menambahkan predikat (Sangat Baik/Baik/Cukup/Kurang) berdasarkan nilai akhir
tiap mata pelajaran.

### Analisis
- Input: satu angka (nilai_akhir) → function sederhana
- Return: string
- Dipanggil: di event saving model Nilai (disimpan ke kolom)
- Tampil: kolom tabel NilaiResource + rapor PDF

### Langkah implementasi

#### 1. Tambah kolom di migration nilais
File: database/migrations/..._create_nilais_table.php
Tambahkan setelah kolom status:
```php
$table->string('predikat')->nullable()->after('status');
```

#### 2. Tambah ke $fillable model Nilai
File: app/Models/Nilai.php
```php
protected $fillable = [
    'siswa_id', 'guru_id',
    'nilai_tugas', 'nilai_uts', 'nilai_uas',
    'nilai_akhir', 'status', 'predikat', // tambah predikat
];
```

#### 3. Tambah function di NilaiHelper
File: app/Support/NilaiHelper.php
```php
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

#### 4. Panggil di event saving model Nilai
File: app/Models/Nilai.php — di dalam static::saving(...)
```php
static::saving(function (Nilai $nilai) {
    $nilai->nilai_akhir = NilaiHelper::hitungNilaiAkhir(
        $nilai->nilai_tugas, $nilai->nilai_uts, $nilai->nilai_uas
    );
    $nilai->status   = NilaiHelper::tentukanKelulusan($nilai->nilai_akhir);
    $nilai->predikat = NilaiHelper::tentukanPredikat($nilai->nilai_akhir); // tambah
});
```

#### 5. Tampilkan di tabel NilaiResource
File: app/Filament/Resources/NilaiResource.php — di columns()
```php
Tables\Columns\TextColumn::make('predikat')->badge()
    ->color(fn ($state) => match ($state) {
        'Sangat Baik' => 'success',
        'Baik'        => 'info',
        'Cukup'       => 'warning',
        default       => 'danger',
    }),
```

#### 6. Tambahkan di rapor PDF
File: resources/views/rapor.blade.php
Tambah kolom Predikat di tabel nilai:
```blade
<th>Predikat</th>
{{-- di dalam @foreach --}}
<td>{{ $n->predikat ?? '-' }}</td>
```

#### 7. Jalankan
```bash
php artisan migrate:fresh --seed
```

---

## FITUR 2 — NILAI TERTINGGI & TERENDAH PER SISWA
### Kemungkinan keluar: TINGGI

Menampilkan mapel dengan nilai tertinggi dan terendah di rapor siswa.

### Analisis
- Input: koleksi nilai siswa itu sendiri (bukan semua siswa) → function kompleks
- Return: array (nama mapel + angkanya)
- Dipanggil: saat tampil di halaman rapor (tidak disimpan)
- Kenapa tidak disimpan: kalau guru edit nilai IPA jadi lebih tinggi, posisi
  tertinggi berpindah — kalau disimpan di kolom, data tidak sinkron

### Langkah implementasi

#### 1. Tambah dua function di NilaiHelper
File: app/Support/NilaiHelper.php
```php
public static function nilaiTertinggi(\Illuminate\Support\Collection $daftarNilai): array
{
    $tertinggi = $daftarNilai->sortByDesc('nilai_akhir')->first();
    return [
        'mapel' => $tertinggi?->guru->mata_pelajaran ?? '-',
        'nilai' => $tertinggi?->nilai_akhir ?? 0,
    ];
}

public static function nilaiTerendah(\Illuminate\Support\Collection $daftarNilai): array
{
    $terendah = $daftarNilai->sortBy('nilai_akhir')->first();
    return [
        'mapel' => $terendah?->guru->mata_pelajaran ?? '-',
        'nilai' => $terendah?->nilai_akhir ?? 0,
    ];
}
```

#### 2. Panggil di view rapor siswa
File: resources/views/filament/pages/rapor.blade.php
Di blok @php tambahkan:
```php
$tertinggi = \App\Support\NilaiHelper::nilaiTertinggi($daftarNilai);
$terendah  = \App\Support\NilaiHelper::nilaiTerendah($daftarNilai);
```

Tampilkan di bawah ringkasan:
```blade
<div>Nilai Tertinggi:
    <strong>{{ $tertinggi['mapel'] }}</strong>
    ({{ number_format($tertinggi['nilai'], 2) }})
</div>
<div>Nilai Terendah:
    <strong>{{ $terendah['mapel'] }}</strong>
    ({{ number_format($terendah['nilai'], 2) }})
</div>
```

#### 3. Tambahkan juga di rapor PDF (opsional)
File: resources/views/rapor.blade.php
Di bawah tabel ringkasan:
```blade
<p>
    Nilai Tertinggi: <b>{{ $tertinggi['mapel'] }}</b>
    ({{ number_format($tertinggi['nilai'], 2) }})<br>
    Nilai Terendah: <b>{{ $terendah['mapel'] }}</b>
    ({{ number_format($terendah['nilai'], 2) }})
</p>
```

Dan di RaporService.php, tambah variabel sebelum loadView:
```php
$tertinggi = NilaiHelper::nilaiTertinggi($daftarNilai);
$terendah  = NilaiHelper::nilaiTerendah($daftarNilai);
$pdf = Pdf::loadView('rapor', compact(
    'siswa', 'daftarNilai', 'ringkasan', 'statusAkhir',
    'tertinggi', 'terendah' // tambah
));
```

---

## FITUR 3 — PERINGKAT SISWA DALAM KELAS
### Kemungkinan keluar: TINGGI

Menampilkan posisi/peringkat siswa dibanding teman sekelasnya berdasarkan
rata-rata nilai akhir.

### Analisis
- Input: siswa yang dicari + koleksi SEMUA siswa sekelas → function kompleks
- Return: int (1, 2, 3, ...)
- Dipanggil: saat tampil di rapor (tidak disimpan)
- Kenapa tidak disimpan: peringkat berubah setiap ada perubahan nilai
  siapapun di kelas — tidak mungkin dijaga tetap sinkron kalau disimpan

### Langkah implementasi

#### 1. Tambah function di NilaiHelper
File: app/Support/NilaiHelper.php
```php
public static function hitungPeringkat(
    \App\Models\Siswa $siswa,
    \Illuminate\Support\Collection $daftarSiswa
): int {
    $peringkat = $daftarSiswa
        ->sortByDesc(fn ($s) => $s->nilai->avg('nilai_akhir'))
        ->values()
        ->search(fn ($s) => $s->id === $siswa->id);

    return ($peringkat !== false) ? $peringkat + 1 : 0;
}
```

#### 2. Panggil di view rapor siswa
File: resources/views/filament/pages/rapor.blade.php
Di blok @php tambahkan:
```php
$semuaSiswaSeKelas = \App\Models\Siswa::where('kelas', $siswa->kelas)
    ->with('nilai')->get();
$peringkat = \App\Support\NilaiHelper::hitungPeringkat($siswa, $semuaSiswaSeKelas);
$totalSiswaKelas = $semuaSiswaSeKelas->count();
```

Tampilkan:
```blade
<div>Peringkat:
    <strong>{{ $peringkat }}</strong> dari {{ $totalSiswaKelas }} siswa
</div>
```

---

## FITUR 4 — REKAP PER KELAS + WALI KELAS
### Kemungkinan keluar: TINGGI

Halaman yang menampilkan ringkasan nilai per kelas. Admin melihat semua kelas,
wali kelas hanya melihat kelasnya sendiri.

### Analisis
- Tidak butuh function baru — pakai olahLaporan() yang sudah ada
- Yang baru: kolom wali_kelas di tabel gurus, dan halaman Filament baru
- Ini membuktikan function yang dirancang baik bisa dipakai ulang

### Langkah implementasi

#### 1. Tambah kolom di migration gurus
File: database/migrations/..._create_gurus_table.php
```php
$table->string('wali_kelas')->nullable()->after('mata_pelajaran');
```

#### 2. Update model Guru
File: app/Models/Guru.php
```php
protected $fillable = ['user_id', 'kode_guru', 'mata_pelajaran', 'wali_kelas'];

public function isWaliKelas(): bool
{
    return $this->wali_kelas !== null;
}
```

#### 3. Tambah field di GuruResource form()
File: app/Filament/Resources/GuruResource.php
Tambahkan setelah field mata_pelajaran:
```php
Forms\Components\Select::make('wali_kelas')
    ->label('Wali Kelas')
    ->placeholder('Bukan Wali Kelas')
    ->options(fn () => \App\Models\Siswa::query()
        ->distinct()->orderBy('kelas')->pluck('kelas', 'kelas'))
    ->nullable(),
```

#### 4. Buat halaman RekapKelas
```bash
php artisan make:filament-page RekapKelas
```

File: app/Filament/Pages/RekapKelas.php
```php
<?php

namespace App\Filament\Pages;

use App\Models\Nilai;
use App\Models\Siswa;
use App\Support\NilaiHelper;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class RekapKelas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.rekap-kelas';
    protected static ?string $title = 'Rekap Per Kelas';

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        if ($user->isAdmin()) return true;
        if ($user->isGuru() && $user->guru?->isWaliKelas()) return true;
        return false;
    }

    public function getRekapData(): array
    {
        $user = Auth::user();

        $daftarKelas = $user->isAdmin()
            ? Siswa::query()->distinct()->orderBy('kelas')->pluck('kelas')
            : collect([$user->guru->wali_kelas]);

        $rekap = [];
        foreach ($daftarKelas as $kelas) {
            $nilaiKelas  = Nilai::whereHas('siswa', fn ($q) => $q->where('kelas', $kelas))->get();
            $jumlahSiswa = Siswa::where('kelas', $kelas)->count();
            $ringkasan   = NilaiHelper::olahLaporan($nilaiKelas);

            $rekap[] = [
                'kelas'        => $kelas,
                'jumlah_siswa' => $jumlahSiswa,
                'rata_rata'    => $ringkasan['rata_rata'],
                'lulus'        => $ringkasan['lulus'],
                'tidak_lulus'  => $ringkasan['tidak_lulus'],
            ];
        }

        return $rekap;
    }
}
```

#### 5. Buat view rekap-kelas
File: resources/views/filament/pages/rekap-kelas.blade.php
```blade
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Rekap Nilai Per Kelas</x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b font-semibold">
                    <th class="py-2 pr-4">Kelas</th>
                    <th class="pr-4">Jumlah Siswa</th>
                    <th class="pr-4">Rata-rata</th>
                    <th class="pr-4">Lulus</th>
                    <th class="pr-4">Tidak Lulus</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->getRekapData() as $r)
                <tr class="border-b">
                    <td class="py-2 pr-4 font-bold">{{ $r['kelas'] }}</td>
                    <td class="pr-4">{{ $r['jumlah_siswa'] }}</td>
                    <td class="pr-4">{{ number_format($r['rata_rata'], 2) }}</td>
                    <td class="pr-4 text-green-600 font-semibold">{{ $r['lulus'] }}</td>
                    <td class="pr-4 text-red-600 font-semibold">{{ $r['tidak_lulus'] }}</td>
                    <td>
                        @if ($r['tidak_lulus'] === 0 && $r['lulus'] > 0)
                            <span class="text-green-600 font-bold">Semua Lulus</span>
                        @else
                            <span class="text-red-600 font-bold">Ada Yang Tidak Lulus</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
```

#### 6. Update seeder — isi wali_kelas
File: database/seeders/DatabaseSeeder.php
```php
$dataGuru = [
    ['Budi Santoso', 'budi@sekolah.test',  'MT-001', 'Matematika',        '6A'],
    ['Citra Dewi',   'citra@sekolah.test', 'MT-002', 'Matematika',        '6B'],
    ['Siti Aminah',  'siti@sekolah.test',  'IPA-001', 'IPA',              null],
    ['Andi Wijaya',  'andi@sekolah.test',  'IND-001', 'Bahasa Indonesia',  null],
];

foreach ($dataGuru as [$nama, $email, $kode, $mapel, $wali]) {
    $u = User::create([
        'name' => $nama, 'email' => $email,
        'password' => Hash::make('password'), 'role' => 'guru',
    ]);
    Guru::create([
        'user_id' => $u->id, 'kode_guru' => $kode,
        'mata_pelajaran' => $mapel, 'wali_kelas' => $wali,
    ]);
}
```

#### 7. Jalankan
```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
```

#### Tes
- Login admin → Rekap Per Kelas muncul → semua kelas tampil
- Login budi@ (wali 6A) → hanya kelas 6A tampil
- Login siti@ (bukan wali) → menu tidak muncul

---

## FITUR 5 — UPDATE PROFIL + FOTO
### Kemungkinan keluar: TINGGI

Semua role bisa update nama, email, password, dan foto profil.
Siswa dan guru juga melihat data akademiknya (read-only) di halaman yang sama.

### Langkah implementasi

#### 1. Pastikan storage link sudah ada
```bash
php artisan storage:link
```

#### 2. Tambah kolom photo di migration users (jika belum)
File: database/migrations/..._create_users_table.php
```php
$table->string('photo')->nullable()->after('role');
```
Dan tambah ke $fillable User: 'photo'

#### 3. Buat custom profile page
```bash
php artisan make:filament-page EditProfile
```

File: app/Filament/Pages/EditProfile.php
```php
<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Auth;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        $user = Auth::user();

        $schema = [
            FileUpload::make('photo')
                ->label('Foto Profil')
                ->image()->avatar()
                ->directory('foto-profil')
                ->imageEditor()->nullable(),

            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ];

        // tampilkan data akademik read-only sesuai role
        if ($user->isSiswa() && $user->siswa) {
            $schema[] = Placeholder::make('nis')
                ->label('NIS')->content($user->siswa->nis);
            $schema[] = Placeholder::make('kelas')
                ->label('Kelas')->content($user->siswa->kelas);
        }

        if ($user->isGuru() && $user->guru) {
            $schema[] = Placeholder::make('kode_guru')
                ->label('Kode Guru')->content($user->guru->kode_guru);
            $schema[] = Placeholder::make('mata_pelajaran')
                ->label('Mata Pelajaran')->content($user->guru->mata_pelajaran);
        }

        return $form->schema($schema);
    }
}
```

#### 4. Daftarkan di panel provider
File: app/Providers/Filament/AdminPanelProvider.php
```php
use App\Filament\Pages\EditProfile;

// di method panel():
->profile(EditProfile::class)
```

#### 5. Tampilkan avatar di pojok panel (opsional tapi keren)
File: app/Models/User.php
Tambah interface HasAvatar:
```php
use Filament\Models\Contracts\HasAvatar;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }
}
```

#### 6. Jalankan
```bash
php artisan migrate:fresh --seed
```

#### Tes
- Login siswa → klik profil → lihat NIS & kelas (read-only), upload foto
- Login guru → lihat kode guru & mapel (read-only), upload foto
- Login admin → foto & nama saja, tidak ada data akademik
- Upload foto → pojok kanan atas berubah jadi foto

---

## FITUR 6 — FILTER TABEL NILAI
### Kemungkinan keluar: SEDANG

Filter di tabel NilaiResource: berdasarkan status, kelas, dan mapel.

### Langkah implementasi

#### Tambah di filters() NilaiResource
File: app/Filament/Resources/NilaiResource.php
```php
->filters([
    Tables\Filters\SelectFilter::make('status')
        ->label('Status Kelulusan')
        ->options([
            'Lulus'       => 'Lulus',
            'Tidak Lulus' => 'Tidak Lulus',
        ]),

    Tables\Filters\SelectFilter::make('kelas')
        ->label('Kelas')
        ->options(fn () => \App\Models\Siswa::query()
            ->distinct()->orderBy('kelas')->pluck('kelas', 'kelas'))
        ->query(fn ($query, $data) =>
            $query->when($data['value'], fn ($q, $kelas) =>
                $q->whereHas('siswa', fn ($s) => $s->where('kelas', $kelas)))),

    Tables\Filters\SelectFilter::make('guru_id')
        ->label('Mata Pelajaran')
        ->relationship('guru', 'mata_pelajaran'),
])
->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent),
```

Tidak perlu function Helper. Tidak perlu migration baru. Langsung jalan.

---

## FITUR 7 — EXPORT EXCEL
### Kemungkinan keluar: SEDANG

Export seluruh data nilai ke file .xlsx.

### Langkah implementasi

#### 1. Install library
```bash
composer require maatwebsite/excel
```

#### 2. Buat Export class
```bash
php artisan make:export NilaiExport --model=Nilai
```

File: app/Exports/NilaiExport.php
```php
<?php

namespace App\Exports;

use App\Models\Nilai;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NilaiExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Nilai::with(['siswa.user', 'guru'])->get()->map(fn ($n) => [
            'NIS'            => $n->siswa->nis,
            'Nama'           => $n->siswa->user->name,
            'Kelas'          => $n->siswa->kelas,
            'Mata Pelajaran' => $n->guru->mata_pelajaran,
            'Tugas'          => $n->nilai_tugas,
            'UTS'            => $n->nilai_uts,
            'UAS'            => $n->nilai_uas,
            'Nilai Akhir'    => $n->nilai_akhir,
            'Status'         => $n->status,
            'Predikat'       => $n->predikat ?? '-',
        ]);
    }

    public function headings(): array
    {
        return [
            'NIS', 'Nama', 'Kelas', 'Mata Pelajaran',
            'Tugas', 'UTS', 'UAS', 'Nilai Akhir', 'Status', 'Predikat',
        ];
    }
}
```

#### 3. Tambah tombol di NilaiResource
File: app/Filament/Resources/NilaiResource.php
Di getHeaderActions() atau sebagai action tabel:
```php
use App\Exports\NilaiExport;
use Maatwebsite\Excel\Facades\Excel;

// tambah di getHeaderActions():
protected function getHeaderActions(): array
{
    return [
        \Filament\Actions\Action::make('export')
            ->label('Export Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->action(fn () => Excel::download(new NilaiExport, 'nilai-siswa.xlsx')),
    ];
}
```

---

## FITUR 8 — WIDGET DASHBOARD
### Kemungkinan keluar: SEDANG

Statistik di halaman utama: total siswa, guru, persentase kelulusan.

### Langkah implementasi

#### 1. Buat widget
```bash
php artisan make:filament-widget StatsOverview --stats-overview
```

File: app/Filament/Widgets/StatsOverview.php
```php
<?php

namespace App\Filament\Widgets;

use App\Models\Guru;
use App\Models\Nilai;
use App\Models\Siswa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalNilai  = Nilai::count();
        $lulus       = Nilai::where('status', 'Lulus')->count();
        $tidakLulus  = Nilai::where('status', 'Tidak Lulus')->count();
        $persen      = $totalNilai > 0
            ? round(($lulus / $totalNilai) * 100, 1) . '%'
            : '0%';

        return [
            Stat::make('Total Siswa', Siswa::count())
                ->icon('heroicon-o-academic-cap')->color('info'),
            Stat::make('Total Guru', Guru::count())
                ->icon('heroicon-o-user')->color('warning'),
            Stat::make('Nilai Lulus', $lulus)
                ->icon('heroicon-o-check-circle')->color('success'),
            Stat::make('Nilai Tidak Lulus', $tidakLulus)
                ->icon('heroicon-o-x-circle')->color('danger'),
            Stat::make('Persentase Lulus', $persen)
                ->icon('heroicon-o-chart-bar')->color('success'),
        ];
    }
}
```

#### 2. Daftarkan di panel provider jika tidak muncul otomatis
File: app/Providers/Filament/AdminPanelProvider.php
```php
->widgets([
    \App\Filament\Widgets\StatsOverview::class,
])
```

---

## RINGKASAN PRIORITAS

| Prioritas | Fitur | Kompleksitas | File yang disentuh |
|-----------|-------|-------------|-------------------|
| 1 | Predikat nilai | Rendah | NilaiHelper, migration nilais, NilaiResource, rapor |
| 2 | Update profil + foto | Rendah | EditProfile (baru), AdminPanelProvider, User model |
| 3 | Filter tabel nilai | Rendah | NilaiResource saja |
| 4 | Widget dashboard | Rendah | StatsOverview (baru), AdminPanelProvider |
| 5 | Rekap per kelas + wali kelas | Sedang | migration gurus, Guru model, GuruResource, RekapKelas (baru), seeder |
| 6 | Nilai tertinggi/terendah | Sedang | NilaiHelper, rapor blade, RaporService |
| 7 | Peringkat siswa | Sedang | NilaiHelper, rapor blade |
| 8 | Export Excel | Sedang | NilaiExport (baru), NilaiResource, composer |

---

## ATURAN MIGRATION UNTUK UJIKOM

Karena ujikom = development (database bisa di-fresh):
- SELALU edit migration yang sudah ada langsung
- SELALU jalankan php artisan migrate:fresh --seed setelah
- JANGAN bikin migration baru hanya untuk tambah kolom
- Bikin migration baru hanya di production (data nyata yang tidak boleh hilang)

---

## ATURAN FUNCTION UNTUK UJIKOM

Sederhana (input 1 angka/teks) → di NilaiHelper → panggil di saving → simpan ke kolom
Kompleks (input koleksi) → di NilaiHelper → panggil saat tampil → tidak disimpan

Kapan simpan, kapan tidak:
- Bergantung pada baris itu sendiri → SIMPAN (predikat, status, nilai_akhir)
- Bergantung pada perbandingan baris lain → HITUNG SAAT TAMPIL (peringkat, tertinggi/terendah)
