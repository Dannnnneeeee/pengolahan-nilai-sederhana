# TAHAPAN LENGKAP — SISTEM PERPUSTAKAAN
## Pola sama dengan SIPENA, nama dan aturan bisnis yang berbeda

---

## PETA TRANSFER POLA DARI SIPENA

| SIPENA | Perpustakaan |
|--------|-------------|
| Siswa | Anggota |
| Guru | Petugas |
| Nilai | Peminjaman |
| NilaiHelper | PeminjamanHelper |
| hitungNilaiAkhir | hitungDenda |
| tentukanKelulusan | tentukanStatus |
| olahLaporan | olahLaporan |

Event saving di model Peminjaman = persis event saving di model Nilai.
Policy pola Admin/Petugas/Anggota = persis Admin/Guru/Siswa.

---

## DESKRIPSI SISTEM

Mengelola data buku, anggota, dan peminjaman. Anggota bisa meminjam buku,
petugas mengelola peminjaman, admin mengelola semua data.
Denda dihitung otomatis berdasarkan keterlambatan pengembalian.

## ENTITAS DAN RELASI

- users: sumber login (role: admin/petugas/anggota)
- bukus: data koleksi buku
- anggotas: profil akademik anggota (terhubung ke users)
- peminjamans: transaksi peminjaman (terhubung ke anggotas dan bukus)

Relasi:
- User hasOne Anggota, Anggota belongsTo User
- Buku hasMany Peminjaman, Peminjaman belongsTo Buku
- Anggota hasMany Peminjaman, Peminjaman belongsTo Anggota

## FUNGSI TERSTRUKTUR

- validasiStok($stok) → bool: cek stok > 0
- hitungDenda($tglKembali, $tglPengembalian) → float: Rp per hari terlambat
- tentukanStatus($tglKembali, $tglPengembalian) → string: Dipinjam/Terlambat/Dikembalikan
- olahLaporan($daftarPeminjaman) → array: ringkasan total peminjaman

---

## A. KONFIGURASI AWAL

```bash
composer create-project laravel/laravel:^12 sistem-perpustakaan
cd sistem-perpustakaan
composer require filament/filament:"^3.3" -W
php artisan filament:install --panels
```

Edit .env:
```env
APP_NAME="Sistem Perpustakaan"
APP_URL=http://127.0.0.1:8000
DB_DATABASE=sistem_perpustakaan
DB_USERNAME=root
DB_PASSWORD=
```

Edit AdminPanelProvider.php:
```php
->path('app')
->login()
->brandName('SIPERPUS')
```

---

## B. MIGRATION

### Edit migration users — tambah role dan photo
```php
$table->string('password');
$table->enum('role', ['admin', 'petugas', 'anggota'])->default('anggota'); // tambah
$table->string('photo')->nullable();                                        // tambah
$table->rememberToken();
```

### Buat model + migration
```bash
php artisan make:model Buku -m
php artisan make:model Anggota -m
php artisan make:model Peminjaman -m
```

### Migration bukus
```php
Schema::create('bukus', function (Blueprint $table) {
    $table->id();
    $table->string('kode_buku')->unique();
    $table->string('judul');
    $table->string('pengarang');
    $table->string('kategori')->nullable();
    $table->unsignedInteger('stok')->default(0);
    $table->timestamps();
});
```

### Migration anggotas
```php
Schema::create('anggotas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('no_anggota')->unique();
    $table->string('kelas')->nullable();
    $table->timestamps();
});
```

### Migration peminjamans
```php
Schema::create('peminjamans', function (Blueprint $table) {
    $table->id();
    $table->foreignId('anggota_id')->constrained()->cascadeOnDelete();
    $table->foreignId('buku_id')->constrained()->cascadeOnDelete();
    $table->date('tgl_pinjam');
    $table->date('tgl_kembali');                     // batas waktu pengembalian
    $table->date('tgl_pengembalian')->nullable();    // tanggal aktual dikembalikan
    $table->decimal('denda', 10, 2)->default(0);    // dihitung otomatis
    $table->string('status')->default('Dipinjam');  // dihitung otomatis
    $table->timestamps();
});
```

---

## C. HELPER

File: app/Support/PeminjamanHelper.php
```php
<?php

namespace App\Support;

final class PeminjamanHelper
{
    public const DENDA_PER_HARI = 1000; // Rp 1.000 per hari

    // Fungsi 1: validasi stok tersedia
    public static function validasiStok(int $stok): bool
    {
        return $stok > 0;
    }

    // Fungsi 2: hitung denda keterlambatan
    public static function hitungDenda(
        string $tglKembali,
        ?string $tglPengembalian
    ): float {
        if (! $tglPengembalian) return 0;

        $batas   = \Carbon\Carbon::parse($tglKembali);
        $kembali = \Carbon\Carbon::parse($tglPengembalian);
        $selisih = $batas->diffInDays($kembali, false); // negatif = tepat waktu

        return $selisih > 0 ? $selisih * self::DENDA_PER_HARI : 0;
    }

    // Fungsi 3: tentukan status peminjaman
    public static function tentukanStatus(
        string $tglKembali,
        ?string $tglPengembalian
    ): string {
        if (! $tglPengembalian) {
            return \Carbon\Carbon::now()->gt(\Carbon\Carbon::parse($tglKembali))
                ? 'Terlambat'
                : 'Dipinjam';
        }
        $denda = self::hitungDenda($tglKembali, $tglPengembalian);
        return $denda > 0 ? 'Dikembalikan (Terlambat)' : 'Dikembalikan';
    }

    // Fungsi 4: olah ringkasan laporan
    public static function olahLaporan(iterable $daftarPeminjaman): array
    {
        $total = 0; $dikembalikan = 0; $terlambat = 0; $totalDenda = 0;
        foreach ($daftarPeminjaman as $p) {
            $total++;
            if (str_contains($p->status, 'Dikembalikan')) $dikembalikan++;
            if (str_contains($p->status, 'Terlambat'))    $terlambat++;
            $totalDenda += (float) $p->denda;
        }
        return [
            'total'        => $total,
            'dipinjam'     => $total - $dikembalikan,
            'dikembalikan' => $dikembalikan,
            'terlambat'    => $terlambat,
            'total_denda'  => $totalDenda,
        ];
    }

    // Fungsi pendukung: generate kode buku otomatis
    public static function generateKodeBuku(): string
    {
        $last = \App\Models\Buku::orderByDesc('id')->first();
        $next = $last ? ((int) substr($last->kode_buku, 3)) + 1 : 1;
        return 'BK-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // Fungsi pendukung: generate nomor anggota otomatis
    public static function generateNoAnggota(): string
    {
        $tahun = date('y');
        $last  = \App\Models\Anggota::where('no_anggota', 'like', $tahun . '%')
            ->orderByDesc('no_anggota')->first();
        $next  = $last ? ((int) substr($last->no_anggota, 2)) + 1 : 1;
        return $tahun . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
```

---

## D. MODEL

### app/Models/User.php
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

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'petugas', 'anggota']);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function anggota(): HasOne { return $this->hasOne(Anggota::class); }

    public function isAdmin(): bool   { return $this->role === 'admin'; }
    public function isPetugas(): bool { return $this->role === 'petugas'; }
    public function isAnggota(): bool { return $this->role === 'anggota'; }
}
```

### app/Models/Buku.php
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Buku extends Model
{
    protected $fillable = ['kode_buku', 'judul', 'pengarang', 'kategori', 'stok'];

    public function peminjaman(): HasMany { return $this->hasMany(Peminjaman::class); }
}
```

### app/Models/Anggota.php
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Anggota extends Model
{
    protected $fillable = ['user_id', 'no_anggota', 'kelas'];

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function peminjaman(): HasMany   { return $this->hasMany(Peminjaman::class); }

    public static function generateNoAnggota(): string
    {
        return \App\Support\PeminjamanHelper::generateNoAnggota();
    }

    protected static function booted(): void
    {
        static::deleted(function (Anggota $anggota) {
            $anggota->user()->delete();
        });
    }
}
```

### app/Models/Peminjaman.php — TITIK INTEGRASI OOP + TERSTRUKTUR
```php
<?php
namespace App\Models;

use App\Support\PeminjamanHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Peminjaman extends Model
{
    protected $fillable = [
        'anggota_id', 'buku_id',
        'tgl_pinjam', 'tgl_kembali', 'tgl_pengembalian',
        'denda', 'status',
    ];

    // TITIK INTEGRASI: OOP memanggil fungsi terstruktur
    protected static function booted(): void
    {
        // sebelum disimpan: hitung denda dan status otomatis
        static::saving(function (Peminjaman $p) {
            $p->denda  = PeminjamanHelper::hitungDenda(
                $p->tgl_kembali, $p->tgl_pengembalian
            );
            $p->status = PeminjamanHelper::tentukanStatus(
                $p->tgl_kembali, $p->tgl_pengembalian
            );
        });

        // setelah dibuat: kurangi stok buku
        static::created(function (Peminjaman $p) {
            $p->buku->decrement('stok');
        });

        // setelah dihapus: kembalikan stok kalau belum dikembalikan
        static::deleted(function (Peminjaman $p) {
            if (! str_contains($p->status, 'Dikembalikan')) {
                $p->buku->increment('stok');
            }
        });
    }

    public function anggota(): BelongsTo { return $this->belongsTo(Anggota::class); }
    public function buku(): BelongsTo    { return $this->belongsTo(Buku::class); }
}
```

---

## E. SEEDER

File: database/seeders/DatabaseSeeder.php
```php
<?php
namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\Buku;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'name' => 'Administrator', 'email' => 'admin@perpus.test',
            'password' => Hash::make('password'), 'role' => 'admin',
        ]);

        // Petugas
        User::create([
            'name' => 'Pak Rudi', 'email' => 'rudi@perpus.test',
            'password' => Hash::make('password'), 'role' => 'petugas',
        ]);

        // Anggota
        $u = User::create([
            'name' => 'Ahmad Fauzi', 'email' => 'ahmad@perpus.test',
            'password' => Hash::make('password'), 'role' => 'anggota',
        ]);
        $anggota = Anggota::create([
            'user_id' => $u->id, 'no_anggota' => '260001', 'kelas' => '6A',
        ]);

        // Buku
        $buku1 = Buku::create([
            'kode_buku' => 'BK-0001', 'judul' => 'Matematika Dasar',
            'pengarang' => 'Budi Santoso', 'kategori' => 'Pelajaran', 'stok' => 5,
        ]);
        $buku2 = Buku::create([
            'kode_buku' => 'BK-0002', 'judul' => 'IPA Terpadu',
            'pengarang' => 'Siti Aminah', 'kategori' => 'Pelajaran', 'stok' => 3,
        ]);

        // Peminjaman (denda & status otomatis via saving, stok berkurang via created)
        Peminjaman::create([
            'anggota_id'       => $anggota->id,
            'buku_id'          => $buku1->id,
            'tgl_pinjam'       => '2026-06-01',
            'tgl_kembali'      => '2026-06-08',
            'tgl_pengembalian' => '2026-06-10', // terlambat 2 hari → denda 2000
        ]);
        Peminjaman::create([
            'anggota_id'       => $anggota->id,
            'buku_id'          => $buku2->id,
            'tgl_pinjam'       => '2026-06-10',
            'tgl_kembali'      => '2026-06-17',
            'tgl_pengembalian' => null, // belum dikembalikan
        ]);
    }
}
```

Jalankan:
```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
```

---

## F. POLICY

```bash
php artisan make:policy BukuPolicy --model=Buku
php artisan make:policy AnggotaPolicy --model=Anggota
php artisan make:policy PeminjamanPolicy --model=Peminjaman
```

### BukuPolicy
```php
<?php
namespace App\Policies;
use App\Models\Buku;
use App\Models\User;

class BukuPolicy
{
    public function viewAny(User $user): bool           { return true; }
    public function view(User $user, Buku $buku): bool  { return true; }
    public function create(User $user): bool             { return $user->isAdmin(); }
    public function update(User $user, Buku $buku): bool { return $user->isAdmin(); }
    public function delete(User $user, Buku $buku): bool { return $user->isAdmin(); }
}
```

### AnggotaPolicy
```php
<?php
namespace App\Policies;
use App\Models\Anggota;
use App\Models\User;

class AnggotaPolicy
{
    public function viewAny(User $user): bool               { return $user->isAdmin(); }
    public function view(User $user, Anggota $a): bool      { return $user->isAdmin(); }
    public function create(User $user): bool                 { return $user->isAdmin(); }
    public function update(User $user, Anggota $a): bool    { return $user->isAdmin(); }
    public function delete(User $user, Anggota $a): bool    { return $user->isAdmin(); }
}
```

### PeminjamanPolicy
```php
<?php
namespace App\Policies;
use App\Models\Peminjaman;
use App\Models\User;

class PeminjamanPolicy
{
    public function viewAny(User $user): bool               { return true; }
    public function view(User $user, Peminjaman $p): bool   { return true; }
    public function create(User $user): bool                 { return $user->isAdmin() || $user->isPetugas(); }
    public function update(User $user, Peminjaman $p): bool { return $user->isAdmin() || $user->isPetugas(); }
    public function delete(User $user, Peminjaman $p): bool { return $user->isAdmin(); }
}
```

---

## G. RESOURCE

```bash
php artisan make:filament-resource Buku
php artisan make:filament-resource Anggota
php artisan make:filament-resource Peminjaman
```

### BukuResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\BukuResource\Pages;
use App\Models\Buku;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BukuResource extends Resource
{
    protected static ?string $model = Buku::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Data Master';
    protected static ?string $modelLabel = 'Buku';
    protected static ?string $pluralModelLabel = 'Buku';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('kode_buku')->label('Kode Buku')
                ->default(fn () => \App\Support\PeminjamanHelper::generateKodeBuku())
                ->required()->unique('bukus', 'kode_buku', ignoreRecord: true),
            Forms\Components\TextInput::make('judul')->required(),
            Forms\Components\TextInput::make('pengarang')->required(),
            Forms\Components\TextInput::make('kategori')->nullable(),
            Forms\Components\TextInput::make('stok')->numeric()->minValue(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('kode_buku')->label('Kode')->searchable(),
            Tables\Columns\TextColumn::make('judul')->searchable(),
            Tables\Columns\TextColumn::make('pengarang')->searchable(),
            Tables\Columns\TextColumn::make('kategori')->badge(),
            Tables\Columns\TextColumn::make('stok')->sortable()
                ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBukus::route('/'),
            'create' => Pages\CreateBuku::route('/create'),
            'edit'   => Pages\EditBuku::route('/{record}/edit'),
        ];
    }
}
```

### AnggotaResource.php — pola sama SiswaResource SIPENA
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\AnggotaResource\Pages;
use App\Models\Anggota;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AnggotaResource extends Resource
{
    protected static ?string $model = Anggota::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Data Master';
    protected static ?string $modelLabel = 'Anggota';
    protected static ?string $pluralModelLabel = 'Anggota';

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
            Forms\Components\TextInput::make('no_anggota')->label('No. Anggota')
                ->default(fn () => \App\Models\Anggota::generateNoAnggota())
                ->required()->unique('anggotas', 'no_anggota', ignoreRecord: true),
            Forms\Components\TextInput::make('kelas')->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('no_anggota')->label('No. Anggota')->searchable(),
            Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
            Tables\Columns\TextColumn::make('kelas')->badge(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAnggotas::route('/'),
            'create' => Pages\CreateAnggota::route('/create'),
            'edit'   => Pages\EditAnggota::route('/{record}/edit'),
        ];
    }
}
```

### CreateAnggota.php
```php
<?php
namespace App\Filament\Resources\AnggotaResource\Pages;

use App\Filament\Resources\AnggotaResource;
use App\Models\Anggota;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CreateAnggota extends CreateRecord
{
    protected static string $resource = AnggotaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'],
            'password' => Hash::make($data['password']), 'role' => 'anggota',
        ]);
        return Anggota::create([
            'user_id'    => $user->id,
            'no_anggota' => $data['no_anggota'],
            'kelas'      => $data['kelas'] ?? null,
        ]);
    }
}
```

### EditAnggota.php
```php
<?php
namespace App\Filament\Resources\AnggotaResource\Pages;

use App\Filament\Resources\AnggotaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EditAnggota extends EditRecord
{
    protected static string $resource = AnggotaResource::class;

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
            'no_anggota' => $data['no_anggota'],
            'kelas'      => $data['kelas'] ?? null,
        ]);
        return $record;
    }
}
```

### PeminjamanResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\PeminjamanResource\Pages;
use App\Models\Peminjaman;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PeminjamanResource extends Resource
{
    protected static ?string $model = Peminjaman::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Transaksi';
    protected static ?string $modelLabel = 'Peminjaman';
    protected static ?string $pluralModelLabel = 'Peminjaman';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('anggota_id')->label('Anggota')
                ->relationship('anggota', 'no_anggota')
                ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->no_anggota} — {$r->user->name}")
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('buku_id')->label('Buku')
                ->relationship('buku', 'judul')
                ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->judul} (stok: {$r->stok})")
                ->searchable()->preload()->required(),
            Forms\Components\DatePicker::make('tgl_pinjam')->label('Tanggal Pinjam')
                ->default(now())->required(),
            Forms\Components\DatePicker::make('tgl_kembali')->label('Batas Kembali')
                ->default(now()->addDays(7))->required(),
            Forms\Components\DatePicker::make('tgl_pengembalian')->label('Tanggal Dikembalikan')
                ->nullable()
                ->helperText('Kosongkan jika belum dikembalikan.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('anggota.user.name')->label('Nama Anggota')->searchable(),
            Tables\Columns\TextColumn::make('buku.judul')->label('Judul Buku')->searchable(),
            Tables\Columns\TextColumn::make('tgl_pinjam')->label('Tgl Pinjam')->date(),
            Tables\Columns\TextColumn::make('tgl_kembali')->label('Batas Kembali')->date(),
            Tables\Columns\TextColumn::make('tgl_pengembalian')->label('Tgl Kembali')
                ->date()->placeholder('-'),
            Tables\Columns\TextColumn::make('denda')
                ->formatStateUsing(fn ($state) => 'Rp ' . number_format($state, 0, ',', '.')),
            Tables\Columns\TextColumn::make('status')->badge()
                ->color(fn ($state) => match (true) {
                    str_contains($state, 'Terlambat')    => 'danger',
                    str_contains($state, 'Dikembalikan') => 'success',
                    default                              => 'warning',
                }),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'Dipinjam'                => 'Dipinjam',
                    'Terlambat'               => 'Terlambat',
                    'Dikembalikan'            => 'Dikembalikan',
                    'Dikembalikan (Terlambat)'=> 'Dikembalikan (Terlambat)',
                ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    // anggota hanya lihat peminjaman miliknya
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = Auth::user();
        if ($user->isAnggota()) {
            $query->where('anggota_id', $user->anggota?->id);
        }
        return $query;
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPeminjamans::route('/'),
            'create' => Pages\CreatePeminjaman::route('/create'),
            'edit'   => Pages\EditPeminjaman::route('/{record}/edit'),
        ];
    }
}
```

---

## H. HALAMAN RIWAYAT (untuk anggota)

```bash
php artisan make:filament-page RiwayatPeminjaman
```

File: app/Filament/Pages/RiwayatPeminjaman.php
```php
<?php
namespace App\Filament\Pages;

use App\Support\PeminjamanHelper;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class RiwayatPeminjaman extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.riwayat-peminjaman';
    protected static ?string $title = 'Riwayat Peminjaman Saya';

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->isAnggota() ?? false;
    }

    public function getRiwayatData(): array
    {
        $anggota = Auth::user()->anggota;
        $daftar  = $anggota->peminjaman()->with('buku')->get();
        return [
            'daftar'    => $daftar,
            'ringkasan' => PeminjamanHelper::olahLaporan($daftar),
        ];
    }
}
```

File: resources/views/filament/pages/riwayat-peminjaman.blade.php
```blade
<x-filament-panels::page>
    @php
        $data      = $this->getRiwayatData();
        $daftar    = $data['daftar'];
        $ringkasan = $data['ringkasan'];
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            {{ auth()->user()->name }} ({{ auth()->user()->anggota->no_anggota }})
        </x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b font-semibold">
                    <th class="py-2">Judul Buku</th>
                    <th>Tgl Pinjam</th><th>Batas Kembali</th>
                    <th>Tgl Kembali</th><th>Denda</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($daftar as $p)
                <tr class="border-b">
                    <td class="py-2">{{ $p->buku->judul }}</td>
                    <td>{{ $p->tgl_pinjam }}</td>
                    <td>{{ $p->tgl_kembali }}</td>
                    <td>{{ $p->tgl_pengembalian ?? '-' }}</td>
                    <td>Rp {{ number_format($p->denda, 0, ',', '.') }}</td>
                    <td>
                        <span @class([
                            'font-semibold',
                            'text-green-600' => str_contains($p->status, 'Dikembalikan'),
                            'text-red-600'   => str_contains($p->status, 'Terlambat'),
                            'text-yellow-600'=> $p->status === 'Dipinjam',
                        ])>{{ $p->status }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 text-sm space-y-1">
            <div>Total Peminjaman: <strong>{{ $ringkasan['total'] }}</strong></div>
            <div>Sedang Dipinjam: <strong>{{ $ringkasan['dipinjam'] }}</strong></div>
            <div>Sudah Dikembalikan: <strong>{{ $ringkasan['dikembalikan'] }}</strong></div>
            <div>Total Denda:
                <strong>Rp {{ number_format($ringkasan['total_denda'], 0, ',', '.') }}</strong>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
```

---

## I. CHECKLIST PENGUJIAN

```
LOGIN
[ ] admin@perpus.test → lihat semua menu
[ ] rudi@perpus.test (petugas) → lihat Buku + Peminjaman, tidak bisa hapus buku
[ ] ahmad@perpus.test (anggota) → hanya lihat Riwayat Peminjaman miliknya

BUKU
[ ] Tambah buku → kode otomatis BK-0001
[ ] Stok tampil merah kalau 0

ANGGOTA
[ ] Tambah anggota → no. anggota otomatis 260001
[ ] Edit tanpa isi password → password lama tetap

PEMINJAMAN
[ ] Tambah peminjaman → stok buku berkurang otomatis
[ ] Isi tgl_pengembalian → denda dan status terhitung otomatis
[ ] Terlambat 2 hari → denda Rp 2.000
[ ] Hapus peminjaman yang belum dikembalikan → stok buku kembali naik

RIWAYAT (login anggota)
[ ] Hanya tampil peminjaman miliknya
[ ] Ringkasan total + denda tampil benar
```

---

## J. URUTAN FILE YANG DIBUAT

```
app/
├── Models/
│   ├── User.php          (edit bawaan)
│   ├── Buku.php          (buat baru)
│   ├── Anggota.php       (buat baru)
│   └── Peminjaman.php    (buat baru — titik integrasi)
├── Support/
│   └── PeminjamanHelper.php  (buat manual)
├── Policies/
│   ├── BukuPolicy.php
│   ├── AnggotaPolicy.php
│   └── PeminjamanPolicy.php
├── Filament/
│   ├── Resources/
│   │   ├── BukuResource.php + Pages/
│   │   ├── AnggotaResource.php + Pages/ (Create + Edit diisi manual)
│   │   └── PeminjamanResource.php + Pages/
│   └── Pages/
│       └── RiwayatPeminjaman.php
└── Providers/Filament/AdminPanelProvider.php (edit path + brandName)

database/
├── migrations/
│   ├── ..._create_users_table.php    (edit, tambah role+photo)
│   ├── ..._create_bukus_table.php
│   ├── ..._create_anggotas_table.php
│   └── ..._create_peminjamans_table.php
└── seeders/DatabaseSeeder.php

resources/views/filament/pages/
└── riwayat-peminjaman.blade.php
```
