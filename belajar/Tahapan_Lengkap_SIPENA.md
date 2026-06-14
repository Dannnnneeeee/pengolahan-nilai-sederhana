# TAHAPAN LENGKAP MEMBANGUN SIPENA
## Dari Install Laravel sampai Aplikasi Jalan

---

## CATATAN PENTING SEBELUM MULAI

Ada beberapa koreksi dari daftar tahapanmu yang perlu diluruskan:

1. Soal `protected $table` — INI TIDAK PERLU kalau nama tabel mengikuti
   konvensi plural Laravel. Laravel otomatis menebak:
   Model Siswa → tabel siswas
   Model Guru  → tabel gurus
   Model Nilai → tabel nilais
   Tambahkan $table hanya kalau nama tabelmu BERBEDA dari tebakan Laravel.
   Karena kita pakai konvensi default, skip bagian E.

2. Soal `php artisan make:model Siswa -m` — flag -m artinya sekalian
   bikin migration. Tapi migration users sudah ada bawaan Laravel.
   Jadi untuk users: EDIT saja yang sudah ada, jangan buat baru.

3. Urutan yang benar: NilaiHelper dibuat SEBELUM model Nilai,
   karena model Nilai akan mengimport NilaiHelper di event saving.

---

## A. INSTALL LARAVEL

Pilih salah satu cara:

### Cara 1 — via Composer (paling umum)
```bash
composer create-project laravel/laravel:^12 sistem-nilai-siswa
```

### Cara 2 — via Laravel Installer
```bash
laravel new sistem-nilai-siswa --using=12
```

Masuk ke folder project:
```bash
cd sistem-nilai-siswa
```

---

## B. KONFIGURASI DATABASE

### 1. Buka file .env, cari bagian ini dan sesuaikan:
```env
APP_NAME="Sistem Nilai Siswa"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistem_nilai_siswa
DB_USERNAME=root
DB_PASSWORD=
```

Catatan:
- DB_PORT default MySQL = 3306. Kalau pakai Laragon biasanya sama.
- DB_PASSWORD kosong kalau root tidak pakai password (default Laragon/XAMPP).
- DB_DATABASE = nama database yang sudah kamu buat di phpMyAdmin/HeidiSQL.
- APP_URL harus sama dengan URL yang kamu buka di browser (penting untuk storage/foto).

### 2. Buat database dulu di phpMyAdmin atau HeidiSQL
Nama database: sistem_nilai_siswa (sesuai DB_DATABASE di .env)

### 3. Test koneksi
```bash
php artisan migrate
```
Kalau berhasil, tabel users, password_reset_tokens, sessions, dll terbuat.
Kalau error "Access denied" → cek DB_USERNAME dan DB_PASSWORD di .env.
Kalau error "Unknown database" → buat databasenya dulu di phpMyAdmin.

---

## C. INSTALL FILAMENT

### 1. Install package Filament
```bash
composer require filament/filament:"^3.3" -W
```
Flag -W artinya update dependency yang konflik. Proses ini agak lama, tunggu sampai selesai.

### 2. Install panel Filament
```bash
php artisan filament:install --panels
```
Saat ditanya nama panel, ketik: admin
Ini membuat file: app/Providers/Filament/AdminPanelProvider.php

### 3. Konfigurasi panel
Buka app/Providers/Filament/AdminPanelProvider.php
Cari method panel(), sesuaikan:
```php
return $panel
    ->default()
    ->id('admin')
    ->path('app')           // ganti dari 'admin' ke 'app' supaya URL jadi /app
    ->login()               // halaman login sudah otomatis ada
    ->brandName('SIPENA')   // nama aplikasi di panel
    ->colors(['primary' => \Filament\Support\Colors\Color::Blue])
    ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
    ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
    ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
    ->middleware([...])     // biarkan default
    ->authMiddleware([...]) // biarkan default
```

---

## D. BUAT MIGRATION

### Penting: migration users sudah ada, JANGAN buat baru — EDIT saja.

### 1. Edit migration users yang sudah ada
File: database/migrations/0001_01_01_000000_create_users_table.php
Tambahkan dua kolom setelah 'password':
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->enum('role', ['admin', 'guru', 'siswa'])->default('siswa'); // TAMBAH
    $table->string('photo')->nullable();                                // TAMBAH
    $table->rememberToken();
    $table->timestamps();
});
```

### 2. Buat model + migration sekaligus
```bash
php artisan make:model Guru -m
php artisan make:model Siswa -m
php artisan make:model Nilai -m
```
Flag -m = sekalian buat file migration-nya.
Urutan: Guru dulu, lalu Siswa, lalu Nilai.
Kenapa Guru sebelum Siswa? Tidak wajib, tapi Nilai butuh keduanya jadi keduanya harus ada duluan.

### 3. Isi migration gurus
File: database/migrations/xxxx_create_gurus_table.php
```php
Schema::create('gurus', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('kode_guru')->unique();
    $table->string('mata_pelajaran');
    $table->string('wali_kelas')->nullable(); // untuk fitur rekap per kelas
    $table->timestamps();
});
```

### 4. Isi migration siswas
File: database/migrations/xxxx_create_siswas_table.php
```php
Schema::create('siswas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('nis')->unique();
    $table->string('kelas');
    $table->timestamps();
});
```

### 5. Isi migration nilais
File: database/migrations/xxxx_create_nilais_table.php
```php
Schema::create('nilais', function (Blueprint $table) {
    $table->id();
    $table->foreignId('siswa_id')->constrained()->cascadeOnDelete();
    $table->foreignId('guru_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('nilai_tugas');   // 0-255, pas untuk 0-100
    $table->unsignedTinyInteger('nilai_uts');
    $table->unsignedTinyInteger('nilai_uas');
    $table->decimal('nilai_akhir', 5, 2)->default(0); // presisi 2 desimal
    $table->string('status')->default('-');
    $table->string('predikat')->nullable();
    $table->timestamps();
    $table->unique(['siswa_id', 'guru_id']); // satu siswa satu nilai per guru
});
```

---

## E. BUAT FOLDER SUPPORT + NILAIHELPER

Buat folder: app/Support/
Buat file: app/Support/NilaiHelper.php

```php
<?php

namespace App\Support;

final class NilaiHelper
{
    public const KKM = 70;

    // Fungsi 1: validasi rentang nilai 0-100
    public static function validasiNilai(int $nilai): bool
    {
        return $nilai >= 0 && $nilai <= 100;
    }

    // Fungsi 2: hitung nilai akhir dengan rumus 30/30/40
    public static function hitungNilaiAkhir(float $tugas, float $uts, float $uas): float
    {
        return round((0.30 * $tugas) + (0.30 * $uts) + (0.40 * $uas), 2);
    }

    // Fungsi 3: tentukan status kelulusan
    public static function tentukanKelulusan(float $nilaiAkhir): string
    {
        return $nilaiAkhir >= self::KKM ? 'Lulus' : 'Tidak Lulus';
    }

    // Fungsi 4: tentukan predikat
    public static function tentukanPredikat(float $nilaiAkhir): string
    {
        return match (true) {
            $nilaiAkhir >= 90 => 'Sangat Baik',
            $nilaiAkhir >= 80 => 'Baik',
            $nilaiAkhir >= 70 => 'Cukup',
            default           => 'Kurang',
        };
    }

    // Fungsi 5: olah ringkasan laporan dari koleksi nilai
    public static function olahLaporan(iterable $daftarNilai): array
    {
        $total = 0; $jumlah = 0.0; $lulus = 0;
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

    // Fungsi 6: nilai tertinggi dari koleksi nilai satu siswa
    public static function nilaiTertinggi(\Illuminate\Support\Collection $daftarNilai): array
    {
        $tertinggi = $daftarNilai->sortByDesc('nilai_akhir')->first();
        return [
            'mapel' => $tertinggi?->guru->mata_pelajaran ?? '-',
            'nilai' => $tertinggi?->nilai_akhir ?? 0,
        ];
    }

    // Fungsi 7: nilai terendah dari koleksi nilai satu siswa
    public static function nilaiTerendah(\Illuminate\Support\Collection $daftarNilai): array
    {
        $terendah = $daftarNilai->sortBy('nilai_akhir')->first();
        return [
            'mapel' => $terendah?->guru->mata_pelajaran ?? '-',
            'nilai' => $terendah?->nilai_akhir ?? 0,
        ];
    }

    // Fungsi 8: peringkat siswa dalam kelas
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
}
```

---

## F. ISI MODEL

### 1. app/Models/User.php (edit yang sudah ada, jangan buat baru)
```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'photo'];
    protected $hidden   = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    // kontrol akses panel: semua role boleh masuk
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'guru', 'siswa']);
    }

    // avatar di pojok kanan atas panel
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    // relasi ke profil akademik
    public function siswa(): HasOne { return $this->hasOne(Siswa::class); }
    public function guru(): HasOne  { return $this->hasOne(Guru::class); }

    // helper role
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isGuru(): bool  { return $this->role === 'guru'; }
    public function isSiswa(): bool { return $this->role === 'siswa'; }
}
```

### 2. app/Models/Guru.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guru extends Model
{
    protected $fillable = ['user_id', 'kode_guru', 'mata_pelajaran', 'wali_kelas'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function nilai(): HasMany  { return $this->hasMany(Nilai::class); }

    public function isWaliKelas(): bool { return $this->wali_kelas !== null; }

    // auto-generate kode guru ber-prefix mapel: MT-001, IPA-001, IND-001
    public static function generateKode(string $mataPelajaran): string
    {
        $prefix = match ($mataPelajaran) {
            'Matematika'       => 'MT',
            'IPA'              => 'IPA',
            'Bahasa Indonesia' => 'IND',
            default            => 'G',
        };
        $last = static::where('kode_guru', 'like', $prefix . '-%')
            ->orderByDesc('kode_guru')->first();
        $next = $last ? ((int) substr($last->kode_guru, strlen($prefix) + 1)) + 1 : 1;
        return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    // hapus akun user saat guru dihapus
    protected static function booted(): void
    {
        static::deleted(function (Guru $guru) {
            $guru->user()->delete();
        });
    }
}
```

### 3. app/Models/Siswa.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    protected $fillable = ['user_id', 'nis', 'kelas'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function nilai(): HasMany  { return $this->hasMany(Nilai::class); }

    // auto-generate NIS berbasis tahun: 260001, 260002, ...
    public static function generateNis(): string
    {
        $tahun = date('y');
        $last  = static::where('nis', 'like', $tahun . '%')->orderByDesc('nis')->first();
        $next  = $last ? ((int) substr($last->nis, 2)) + 1 : 1;
        return $tahun . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // hapus akun user saat siswa dihapus
    protected static function booted(): void
    {
        static::deleted(function (Siswa $siswa) {
            $siswa->user()->delete();
        });
    }
}
```

### 4. app/Models/Nilai.php — TITIK INTEGRASI OOP + TERSTRUKTUR
```php
<?php

namespace App\Models;

use App\Support\NilaiHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nilai extends Model
{
    protected $fillable = [
        'siswa_id', 'guru_id',
        'nilai_tugas', 'nilai_uts', 'nilai_uas',
        'nilai_akhir', 'status', 'predikat',
    ];

    // event saving: OOP (model) memanggil fungsi terstruktur (NilaiHelper)
    protected static function booted(): void
    {
        static::saving(function (Nilai $nilai) {
            // validasi rentang nilai
            foreach ([$nilai->nilai_tugas, $nilai->nilai_uts, $nilai->nilai_uas] as $n) {
                if (! NilaiHelper::validasiNilai((int) $n)) {
                    throw new \InvalidArgumentException('Nilai harus berada pada rentang 0-100.');
                }
            }
            // hitung dan isi otomatis
            $nilai->nilai_akhir = NilaiHelper::hitungNilaiAkhir(
                $nilai->nilai_tugas, $nilai->nilai_uts, $nilai->nilai_uas
            );
            $nilai->status   = NilaiHelper::tentukanKelulusan($nilai->nilai_akhir);
            $nilai->predikat = NilaiHelper::tentukanPredikat($nilai->nilai_akhir);
        });
    }

    public function siswa(): BelongsTo { return $this->belongsTo(Siswa::class); }
    public function guru(): BelongsTo  { return $this->belongsTo(Guru::class); }
}
```

---

## G. ISI SEEDER

File: database/seeders/DatabaseSeeder.php
```php
<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1 admin
        User::create([
            'name' => 'Administrator', 'email' => 'admin@sekolah.test',
            'password' => Hash::make('password'), 'role' => 'admin',
        ]);

        // 4 guru (2 Matematika untuk buktikan banyak guru per mapel)
        $dataGuru = [
            ['Budi Santoso', 'budi@sekolah.test',  'MT-001', 'Matematika',        '6A'],
            ['Citra Dewi',   'citra@sekolah.test', 'MT-002', 'Matematika',        '6B'],
            ['Siti Aminah',  'siti@sekolah.test',  'IPA-001', 'IPA',              null],
            ['Andi Wijaya',  'andi@sekolah.test',  'IND-001', 'Bahasa Indonesia',  null],
        ];
        $gurus = [];
        foreach ($dataGuru as [$nama, $email, $kode, $mapel, $wali]) {
            $u = User::create([
                'name' => $nama, 'email' => $email,
                'password' => Hash::make('password'), 'role' => 'guru',
            ]);
            $gurus[] = Guru::create([
                'user_id' => $u->id, 'kode_guru' => $kode,
                'mata_pelajaran' => $mapel, 'wali_kelas' => $wali,
            ]);
        }

        // 3 siswa
        $dataSiswa = [
            ['Ahmad Fauzi',  'ahmad@sekolah.test', '260001', '6A'],
            ['Dewi Lestari', 'dewi@sekolah.test',  '260002', '6A'],
            ['Rudi Hartono', 'rudi@sekolah.test',  '260003', '6B'],
        ];
        $siswas = [];
        foreach ($dataSiswa as [$nama, $email, $nis, $kelas]) {
            $u = User::create([
                'name' => $nama, 'email' => $email,
                'password' => Hash::make('password'), 'role' => 'siswa',
            ]);
            $siswas[] = Siswa::create([
                'user_id' => $u->id, 'nis' => $nis, 'kelas' => $kelas,
            ]);
        }

        // nilai: tiap siswa dinilai MT-001, IPA-001, IND-001
        // (nilai_akhir, status, predikat terisi OTOMATIS via event saving)
        $guruPenilai = [$gurus[0], $gurus[2], $gurus[3]];
        $contoh = [[85, 78, 90], [70, 65, 80], [60, 55, 68]];
        foreach ($siswas as $i => $siswa) {
            foreach ($guruPenilai as $j => $guru) {
                [$t, $u, $a] = $contoh[($i + $j) % 3];
                Nilai::create([
                    'siswa_id' => $siswa->id, 'guru_id' => $guru->id,
                    'nilai_tugas' => $t, 'nilai_uts' => $u, 'nilai_uas' => $a,
                ]);
            }
        }
    }
}
```

### Jalankan migrate dan seed
```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
```

PENTING: kalau nilai_akhir masih 0.00 setelah seed, itu karena OPcache CLI.
Solusi: tambahkan opcache.enable_cli=0 di php.ini
Atau: jalankan php artisan optimize:clear sebelum setiap seed.

---

## H. TEST LOGIN

```bash
php artisan serve
```
Buka browser: http://127.0.0.1:8000/app

Login dengan:
- admin@sekolah.test / password (admin)
- budi@sekolah.test / password (guru Matematika, wali 6A)
- ahmad@sekolah.test / password (siswa)

Dashboard muncul tapi masih kosong = NORMAL. Resource belum dibuat.

---

## I. BUAT POLICY

```bash
php artisan make:policy SiswaPolicy --model=Siswa
php artisan make:policy GuruPolicy --model=Guru
php artisan make:policy NilaiPolicy --model=Nilai
```

PENTING: stub bawaan policy isinya return false semua.
Kalau lupa ganti isinya, menu tidak akan muncul di panel.

### app/Policies/SiswaPolicy.php
```php
<?php
namespace App\Policies;
use App\Models\Siswa;
use App\Models\User;

class SiswaPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, Siswa $siswa): bool { return $user->isAdmin(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Siswa $siswa): bool { return $user->isAdmin(); }
    public function delete(User $user, Siswa $siswa): bool { return $user->isAdmin(); }
}
```

### app/Policies/GuruPolicy.php
```php
<?php
namespace App\Policies;
use App\Models\Guru;
use App\Models\User;

class GuruPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, Guru $guru): bool { return $user->isAdmin(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Guru $guru): bool { return $user->isAdmin(); }
    public function delete(User $user, Guru $guru): bool { return $user->isAdmin(); }
}
```

### app/Policies/NilaiPolicy.php
```php
<?php
namespace App\Policies;
use App\Models\Nilai;
use App\Models\User;

class NilaiPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->isGuru(); }
    public function view(User $user, Nilai $nilai): bool { return $user->isAdmin() || $this->milik($user, $nilai); }
    public function create(User $user): bool { return $user->isAdmin() || $user->isGuru(); }
    public function update(User $user, Nilai $nilai): bool { return $user->isAdmin() || $this->milik($user, $nilai); }
    public function delete(User $user, Nilai $nilai): bool { return $user->isAdmin() || $this->milik($user, $nilai); }

    private function milik(User $user, Nilai $nilai): bool
    {
        return $user->isGuru() && $user->guru?->id === $nilai->guru_id;
    }
}
```

---

## J. BUAT RESOURCE FILAMENT

```bash
php artisan make:filament-resource Siswa
php artisan make:filament-resource Guru
php artisan make:filament-resource Nilai
```

Ini membuat folder dan file berikut:
app/Filament/Resources/
├── SiswaResource.php
├── SiswaResource/Pages/
│   ├── ListSiswas.php
│   ├── CreateSiswa.php
│   └── EditSiswa.php
├── GuruResource.php
├── GuruResource/Pages/...
├── NilaiResource.php
└── NilaiResource/Pages/...

### app/Filament/Resources/SiswaResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\SiswaResource\Pages;
use App\Models\Siswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SiswaResource extends Resource
{
    protected static ?string $model = Siswa::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Data Master';
    protected static ?string $modelLabel = 'Siswa';
    protected static ?string $pluralModelLabel = 'Siswa';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
            Forms\Components\TextInput::make('email')->email()->required()
                ->unique('users', 'email', ignorable: fn ($record) => $record?->user),
            Forms\Components\TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('Kosongkan saat edit bila tidak ganti password.'),
            Forms\Components\TextInput::make('nis')->label('NIS')->required()
                ->default(fn () => \App\Models\Siswa::generateNis())
                ->unique('siswas', 'nis', ignoreRecord: true),
            Forms\Components\TextInput::make('kelas')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nis')->label('NIS')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
            Tables\Columns\TextColumn::make('kelas')->badge()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSiswas::route('/'),
            'create' => Pages\CreateSiswa::route('/create'),
            'edit'   => Pages\EditSiswa::route('/{record}/edit'),
        ];
    }
}
```

### app/Filament/Resources/SiswaResource/Pages/CreateSiswa.php
```php
<?php
namespace App\Filament\Resources\SiswaResource\Pages;

use App\Filament\Resources\SiswaResource;
use App\Models\Siswa;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CreateSiswa extends CreateRecord
{
    protected static string $resource = SiswaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'],
            'password' => Hash::make($data['password']), 'role' => 'siswa',
        ]);
        return Siswa::create([
            'user_id' => $user->id, 'nis' => $data['nis'], 'kelas' => $data['kelas'],
        ]);
    }
}
```

### app/Filament/Resources/SiswaResource/Pages/EditSiswa.php
```php
<?php
namespace App\Filament\Resources\SiswaResource\Pages;

use App\Filament\Resources\SiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EditSiswa extends EditRecord
{
    protected static string $resource = SiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name']  = $this->record->user->name;
        $data['email'] = $this->record->user->email;
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $u = ['name' => $data['name'], 'email' => $data['email']];
        if (filled($data['password'] ?? null)) {
            $u['password'] = Hash::make($data['password']);
        }
        $record->user->update($u);
        $record->update(['nis' => $data['nis'], 'kelas' => $data['kelas']]);
        return $record;
    }
}
```

### app/Filament/Resources/GuruResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\GuruResource\Pages;
use App\Models\Guru;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GuruResource extends Resource
{
    protected static ?string $model = Guru::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Data Master';
    protected static ?string $modelLabel = 'Guru';
    protected static ?string $pluralModelLabel = 'Guru';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
            Forms\Components\TextInput::make('email')->email()->required()
                ->unique('users', 'email', ignorable: fn ($record) => $record?->user),
            Forms\Components\TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state)),
            Forms\Components\Select::make('mata_pelajaran')->required()
                ->options([
                    'Matematika'       => 'Matematika',
                    'IPA'              => 'IPA',
                    'Bahasa Indonesia' => 'Bahasa Indonesia',
                ])
                ->live()
                ->afterStateUpdated(fn ($state, callable $set) =>
                    $state ? $set('kode_guru', \App\Models\Guru::generateKode($state)) : null),
            Forms\Components\TextInput::make('kode_guru')->label('Kode Guru')->required()
                ->unique('gurus', 'kode_guru', ignoreRecord: true),
            Forms\Components\Select::make('wali_kelas')->label('Wali Kelas')
                ->placeholder('Bukan Wali Kelas')
                ->options(fn () => \App\Models\Siswa::query()
                    ->distinct()->orderBy('kelas')->pluck('kelas', 'kelas'))
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('kode_guru')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
            Tables\Columns\TextColumn::make('mata_pelajaran')->badge()->sortable(),
            Tables\Columns\TextColumn::make('wali_kelas')->label('Wali Kelas')->badge()
                ->color('info')->placeholder('-'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGurus::route('/'),
            'create' => Pages\CreateGuru::route('/create'),
            'edit'   => Pages\EditGuru::route('/{record}/edit'),
        ];
    }
}
```

### CreateGuru.php
```php
<?php
namespace App\Filament\Resources\GuruResource\Pages;

use App\Filament\Resources\GuruResource;
use App\Models\Guru;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CreateGuru extends CreateRecord
{
    protected static string $resource = GuruResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'],
            'password' => Hash::make($data['password']), 'role' => 'guru',
        ]);
        return Guru::create([
            'user_id'        => $user->id,
            'kode_guru'      => $data['kode_guru'],
            'mata_pelajaran' => $data['mata_pelajaran'],
            'wali_kelas'     => $data['wali_kelas'] ?? null,
        ]);
    }
}
```

### EditGuru.php
```php
<?php
namespace App\Filament\Resources\GuruResource\Pages;

use App\Filament\Resources\GuruResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EditGuru extends EditRecord
{
    protected static string $resource = GuruResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name']  = $this->record->user->name;
        $data['email'] = $this->record->user->email;
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $u = ['name' => $data['name'], 'email' => $data['email']];
        if (filled($data['password'] ?? null)) {
            $u['password'] = Hash::make($data['password']);
        }
        $record->user->update($u);
        $record->update([
            'kode_guru'      => $data['kode_guru'],
            'mata_pelajaran' => $data['mata_pelajaran'],
            'wali_kelas'     => $data['wali_kelas'] ?? null,
        ]);
        return $record;
    }
}
```

### app/Filament/Resources/NilaiResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\NilaiResource\Pages;
use App\Models\Nilai;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class NilaiResource extends Resource
{
    protected static ?string $model = Nilai::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Penilaian';
    protected static ?string $modelLabel = 'Nilai';
    protected static ?string $pluralModelLabel = 'Nilai';

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        return $form->schema([
            // dropdown kelas dulu, baru siswa tersaring
            Forms\Components\Select::make('kelas')->label('Kelas')
                ->options(fn () => \App\Models\Siswa::query()
                    ->distinct()->orderBy('kelas')->pluck('kelas', 'kelas'))
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('siswa_id', null))
                ->dehydrated(false)
                ->required(),

            Forms\Components\Select::make('siswa_id')->label('Siswa')
                ->options(fn (callable $get) => \App\Models\Siswa::query()
                    ->when($get('kelas'), fn ($q, $k) => $q->where('kelas', $k))
                    ->with('user')->get()->pluck('user.name', 'id'))
                ->searchable()->required(),

            Forms\Components\Select::make('guru_id')->label('Guru / Mapel')
                ->relationship('guru', 'mata_pelajaran')
                ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->mata_pelajaran} ({$r->kode_guru})")
                ->default($user->isGuru() ? $user->guru?->id : null)
                ->disabled($user->isGuru())->dehydrated()->required(),

            Forms\Components\TextInput::make('nilai_tugas')->label('Tugas')
                ->numeric()->minValue(0)->maxValue(100)->required(),
            Forms\Components\TextInput::make('nilai_uts')->label('UTS')
                ->numeric()->minValue(0)->maxValue(100)->required(),
            Forms\Components\TextInput::make('nilai_uas')->label('UAS')
                ->numeric()->minValue(0)->maxValue(100)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('siswa.nis')->label('NIS')->searchable(),
            Tables\Columns\TextColumn::make('siswa.user.name')->label('Nama Siswa')->searchable(),
            Tables\Columns\TextColumn::make('siswa.kelas')->label('Kelas')->badge(),
            Tables\Columns\TextColumn::make('guru.mata_pelajaran')->label('Mapel')->badge(),
            Tables\Columns\TextColumn::make('nilai_tugas')->label('Tugas'),
            Tables\Columns\TextColumn::make('nilai_uts')->label('UTS'),
            Tables\Columns\TextColumn::make('nilai_uas')->label('UAS'),
            Tables\Columns\TextColumn::make('nilai_akhir')->label('Nilai Akhir')->weight('bold')->sortable(),
            Tables\Columns\TextColumn::make('status')->badge()
                ->color(fn ($state) => $state === 'Lulus' ? 'success' : 'danger'),
            Tables\Columns\TextColumn::make('predikat')->badge()
                ->color(fn ($state) => match ($state) {
                    'Sangat Baik' => 'success',
                    'Baik'        => 'info',
                    'Cukup'       => 'warning',
                    default       => 'danger',
                }),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options(['Lulus' => 'Lulus', 'Tidak Lulus' => 'Tidak Lulus']),
            Tables\Filters\SelectFilter::make('guru_id')
                ->label('Mata Pelajaran')->relationship('guru', 'mata_pelajaran'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
            Tables\Actions\Action::make('rapor')
                ->label('Rapor')->icon('heroicon-o-document-arrow-down')->color('success')
                ->action(fn (Nilai $record) => \App\Support\RaporService::unduh($record->siswa)),
        ]);
    }

    // scoping: guru hanya lihat nilai mapelnya sendiri
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        if ($user->isGuru()) {
            $query->where('guru_id', $user->guru?->id);
        }
        return $query;
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNilais::route('/'),
            'create' => Pages\CreateNilai::route('/create'),
            'edit'   => Pages\EditNilai::route('/{record}/edit'),
        ];
    }
}
```

### CreateNilai.php — paksa guru_id ke mapel sendiri
```php
<?php
namespace App\Filament\Resources\NilaiResource\Pages;

use App\Filament\Resources\NilaiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNilai extends CreateRecord
{
    protected static string $resource = NilaiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user->isGuru()) {
            $data['guru_id'] = $user->guru->id;
        }
        return $data;
    }
}
```

### EditNilai.php — isi dropdown kelas saat edit
```php
<?php
namespace App\Filament\Resources\NilaiResource\Pages;

use App\Filament\Resources\NilaiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNilai extends EditRecord
{
    protected static string $resource = NilaiResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['kelas'] = $this->record->siswa->kelas;
        return $data;
    }
}
```

---

## K. INSTALL DOMPDF + BUAT RAPORSERVICE

### 1. Install dompdf
```bash
composer require barryvdh/laravel-dompdf
```

### 2. Buat app/Support/RaporService.php
```php
<?php

namespace App\Support;

use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;

class RaporService
{
    public static function unduh(Siswa $siswa)
    {
        $daftarNilai = $siswa->nilai()->with('guru')->get();
        $ringkasan   = NilaiHelper::olahLaporan($daftarNilai);
        $statusAkhir = ($ringkasan['total'] > 0 && $ringkasan['tidak_lulus'] === 0)
            ? 'LULUS' : 'TIDAK LULUS';

        $pdf = Pdf::loadView('rapor', compact(
            'siswa', 'daftarNilai', 'ringkasan', 'statusAkhir'
        ));

        // streamDownload: hindari error "Malformed UTF-8" dari Livewire
        return response()->streamDownload(
            fn () => print($pdf->output()),
            "rapor-{$siswa->nis}.pdf"
        );
    }
}
```

### 3. Buat resources/views/rapor.blade.php
```blade
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
    .ringkas { margin-top: 16px; }
    .ringkas td { padding: 3px 4px; }
</style>
</head>
<body>
    <h2>LAPORAN HASIL BELAJAR SISWA</h2>
    <div class="sub">SIPENA — Sistem Pengolahan Nilai</div>

    <table class="info">
        <tr><td width="80">NIS</td><td>:</td><td>{{ $siswa->nis }}</td></tr>
        <tr><td>Nama</td><td>:</td><td>{{ $siswa->user->name }}</td></tr>
        <tr><td>Kelas</td><td>:</td><td>{{ $siswa->kelas }}</td></tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th>No</th><th>Mata Pelajaran</th>
                <th>Tugas</th><th>UTS</th><th>UAS</th>
                <th>Nilai Akhir</th><th>Predikat</th><th>Keterangan</th>
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
                <td>{{ $n->predikat ?? '-' }}</td>
                <td class="{{ $n->status === 'Lulus' ? 'lulus' : 'tidak' }}">
                    {{ $n->status }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="ringkas">
        <tr><td width="160">Jumlah Mata Pelajaran</td><td>:</td><td>{{ $ringkasan['total'] }}</td></tr>
        <tr><td>Rata-rata Nilai</td><td>:</td><td>{{ number_format($ringkasan['rata_rata'], 2) }}</td></tr>
        <tr><td>Mapel Lulus</td><td>:</td><td>{{ $ringkasan['lulus'] }} dari {{ $ringkasan['total'] }}</td></tr>
        <tr>
            <td>Status Keseluruhan</td><td>:</td>
            <td class="{{ $statusAkhir === 'LULUS' ? 'lulus' : 'tidak' }}">
                <strong>{{ $statusAkhir }}</strong>
            </td>
        </tr>
    </table>
</body>
</html>
```

---

## L. BUAT HALAMAN FILAMENT

### 1. Halaman Rapor (untuk siswa)
```bash
php artisan make:filament-page Rapor
```

File: app/Filament/Pages/Rapor.php
```php
<?php
namespace App\Filament\Pages;

use App\Support\RaporService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Rapor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.rapor';
    protected static ?string $title = 'Rapor Saya';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->isSiswa() ?? false;
    }

    public function unduhRapor()
    {
        return RaporService::unduh(Auth::user()->siswa);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduh')->label('Unduh PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action('unduhRapor'),
        ];
    }
}
```

File: resources/views/filament/pages/rapor.blade.php
```blade
<x-filament-panels::page>
    @php
        $siswa = auth()->user()->siswa;
        $daftarNilai = $siswa->nilai()->with('guru')->get();
        $ringkasan = \App\Support\NilaiHelper::olahLaporan($daftarNilai);
        $statusAkhir = ($ringkasan['total'] > 0 && $ringkasan['tidak_lulus'] === 0)
            ? 'LULUS' : 'TIDAK LULUS';
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            {{ $siswa->user->name }} — {{ $siswa->kelas }} (NIS: {{ $siswa->nis }})
        </x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b font-semibold">
                    <th class="py-2">Mata Pelajaran</th>
                    <th>Tugas</th><th>UTS</th><th>UAS</th>
                    <th>Nilai Akhir</th><th>Predikat</th><th>Status</th>
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
                    <td>{{ $n->predikat ?? '-' }}</td>
                    <td>
                        <span @class([
                            'font-semibold',
                            'text-green-600' => $n->status === 'Lulus',
                            'text-red-600'   => $n->status !== 'Lulus',
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
                    'text-red-600'   => $statusAkhir !== 'LULUS',
                ])>{{ $statusAkhir }}</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
```

### 2. Halaman Profil (semua role)
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
            FileUpload::make('photo')->label('Foto Profil')
                ->image()->avatar()->directory('foto-profil')
                ->imageEditor()->nullable(),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ];

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

Daftarkan di AdminPanelProvider.php:
```php
use App\Filament\Pages\EditProfile;
// di method panel():
->profile(EditProfile::class)
```

Buat storage link untuk foto:
```bash
php artisan storage:link
```

---

## M. FINALISASI DAN CEK AKHIR

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan serve
```

### Checklist pengujian:

LOGIN
- [ ] admin@sekolah.test masuk, lihat menu Data Master + Penilaian
- [ ] budi@sekolah.test masuk, hanya lihat menu Penilaian (nilai mapelnya saja)
- [ ] ahmad@sekolah.test masuk, hanya lihat menu Rapor Saya

DATA SISWA (login admin)
- [ ] Tambah siswa baru → NIS terisi otomatis
- [ ] Edit siswa → ubah nama, password kosong → password lama tetap
- [ ] Hapus siswa → akun user ikut terhapus

DATA GURU (login admin)
- [ ] Tambah guru → pilih mapel Matematika → kode terisi MT-003
- [ ] Tambah guru Matematika lagi → kode MT-004 (increment jalan)
- [ ] Edit guru → kode guru dan mapel bisa diubah

INPUT NILAI (login guru budi@)
- [ ] Pilih kelas → dropdown siswa menyaring sesuai kelas
- [ ] Field guru terkunci ke mapel sendiri
- [ ] Isi nilai → simpan → nilai akhir + status + predikat terisi otomatis
- [ ] Coba isi nilai 150 → ditolak (maxValue 100)

RAPOR SISWA (login siswa ahmad@)
- [ ] Halaman Rapor Saya muncul
- [ ] Tabel nilai tampil lengkap dengan predikat dan status
- [ ] Unduh PDF → file terunduh, isinya benar

PROFIL (semua role)
- [ ] Upload foto → simpan → avatar di pojok kanan atas berubah
- [ ] Siswa: lihat NIS dan kelas (read-only)
- [ ] Guru: lihat kode guru dan mapel (read-only)

---

## URUTAN FILE YANG DIBUAT (ringkasan)

```
app/
├── Models/
│   ├── User.php          (edit bawaan)
│   ├── Guru.php          (buat baru)
│   ├── Siswa.php         (buat baru)
│   └── Nilai.php         (buat baru)
├── Support/
│   ├── NilaiHelper.php   (buat manual)
│   └── RaporService.php  (buat manual)
├── Policies/
│   ├── SiswaPolicy.php
│   ├── GuruPolicy.php
│   └── NilaiPolicy.php
├── Filament/
│   ├── Resources/
│   │   ├── SiswaResource.php + Pages/
│   │   ├── GuruResource.php + Pages/
│   │   └── NilaiResource.php + Pages/
│   └── Pages/
│       ├── Rapor.php
│       └── EditProfile.php
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php (edit)

database/
├── migrations/
│   ├── ..._create_users_table.php    (edit, tambah role+photo)
│   ├── ..._create_gurus_table.php    (buat via make:model -m)
│   ├── ..._create_siswas_table.php   (buat via make:model -m)
│   └── ..._create_nilais_table.php   (buat via make:model -m)
└── seeders/
    └── DatabaseSeeder.php (edit)

resources/views/
├── rapor.blade.php                   (buat manual)
└── filament/pages/
    └── rapor.blade.php               (buat manual)
```
