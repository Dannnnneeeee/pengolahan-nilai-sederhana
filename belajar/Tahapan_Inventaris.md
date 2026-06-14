# TAHAPAN LENGKAP — SISTEM INVENTARIS BARANG
## Pola sama dengan SIPENA, nama dan aturan bisnis yang berbeda

---

## PETA TRANSFER POLA DARI SIPENA

| SIPENA | Inventaris |
|--------|-----------|
| Siswa/Guru | Barang |
| Nilai | Transaksi (masuk/keluar) |
| NilaiHelper | InventarisHelper |
| hitungNilaiAkhir | hitungStokSesudah |
| tentukanKelulusan | tentukanStatusStok |
| olahLaporan | olahLaporan |

Event saving di model Transaksi = persis event saving di model Nilai.
Tidak ada role anggota/siswa — hanya admin dan petugas.

---

## DESKRIPSI SISTEM

Mengelola data barang dan transaksi keluar/masuk stok. Admin mengelola semua.
Petugas gudang mengelola transaksi harian. Stok dihitung dan diperbarui otomatis
setiap ada transaksi masuk atau keluar.

## ENTITAS DAN RELASI

- users: sumber login (role: admin/petugas)
- kategoris: kategori barang (Elektronik, ATK, dll)
- barangs: data barang + stok (terhubung ke kategoris)
- transaksis: transaksi masuk/keluar (terhubung ke barangs dan users)

Relasi:
- Kategori hasMany Barang, Barang belongsTo Kategori
- Barang hasMany Transaksi, Transaksi belongsTo Barang
- User hasMany Transaksi, Transaksi belongsTo User

## FUNGSI TERSTRUKTUR

- validasiJumlah($jumlah, $stok) → bool: cek jumlah keluar tidak melebihi stok
- hitungStokSesudah($stokSebelum, $jenis, $jumlah) → int: stok setelah transaksi
- tentukanStatusStok($stok, $stokMinimum) → string: Aman/Menipis/Habis
- olahLaporan($daftarTransaksi) → array: ringkasan total masuk/keluar

---

## A. KONFIGURASI AWAL

```bash
composer create-project laravel/laravel:^12 sistem-inventaris
cd sistem-inventaris
composer require filament/filament:"^3.3" -W
php artisan filament:install --panels
```

Edit .env:
```env
APP_NAME="Sistem Inventaris"
APP_URL=http://127.0.0.1:8000
DB_DATABASE=sistem_inventaris
DB_USERNAME=root
DB_PASSWORD=
```

Edit AdminPanelProvider.php:
```php
->path('app')
->login()
->brandName('SIVENTARIS')
```

---

## B. MIGRATION

### Edit migration users — tambah role dan photo
```php
$table->string('password');
$table->enum('role', ['admin', 'petugas'])->default('petugas'); // tambah
$table->string('photo')->nullable();                            // tambah
$table->rememberToken();
```

### Buat model + migration
```bash
php artisan make:model Kategori -m
php artisan make:model Barang -m
php artisan make:model Transaksi -m
```

### Migration kategoris
```php
Schema::create('kategoris', function (Blueprint $table) {
    $table->id();
    $table->string('nama')->unique();
    $table->string('keterangan')->nullable();
    $table->timestamps();
});
```

### Migration barangs
```php
Schema::create('barangs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kategori_id')->constrained()->cascadeOnDelete();
    $table->string('kode_barang')->unique();
    $table->string('nama');
    $table->string('satuan');           // pcs, kg, liter, rim, unit, dll
    $table->unsignedInteger('stok')->default(0);
    $table->unsignedInteger('stok_minimum')->default(5); // ambang batas "menipis"
    $table->timestamps();
});
```

### Migration transaksis
```php
Schema::create('transaksis', function (Blueprint $table) {
    $table->id();
    $table->foreignId('barang_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained(); // siapa yang menginput
    $table->enum('jenis', ['masuk', 'keluar']);
    $table->unsignedInteger('jumlah');
    $table->text('keterangan')->nullable();
    $table->date('tanggal');
    $table->unsignedInteger('stok_sesudah')->default(0); // dihitung otomatis
    $table->timestamps();
});
```

---

## C. HELPER

File: app/Support/InventarisHelper.php
```php
<?php

namespace App\Support;

final class InventarisHelper
{
    // Fungsi 1: validasi jumlah keluar tidak melebihi stok
    public static function validasiJumlah(int $jumlah, int $stokSaatIni): bool
    {
        return $jumlah > 0 && $jumlah <= $stokSaatIni;
    }

    // Fungsi 2: hitung stok setelah transaksi
    public static function hitungStokSesudah(
        int $stokSebelum,
        string $jenis,
        int $jumlah
    ): int {
        return match ($jenis) {
            'masuk'  => $stokSebelum + $jumlah,
            'keluar' => $stokSebelum - $jumlah,
            default  => $stokSebelum,
        };
    }

    // Fungsi 3: tentukan status stok
    public static function tentukanStatusStok(int $stok, int $stokMinimum): string
    {
        return match (true) {
            $stok === 0           => 'Habis',
            $stok <= $stokMinimum => 'Menipis',
            default               => 'Aman',
        };
    }

    // Fungsi 4: olah ringkasan laporan transaksi
    public static function olahLaporan(iterable $daftarTransaksi): array
    {
        $totalMasuk = 0; $totalKeluar = 0; $jumlahTransaksi = 0;
        foreach ($daftarTransaksi as $t) {
            $jumlahTransaksi++;
            if ($t->jenis === 'masuk')  $totalMasuk  += $t->jumlah;
            if ($t->jenis === 'keluar') $totalKeluar += $t->jumlah;
        }
        return [
            'jumlah_transaksi' => $jumlahTransaksi,
            'total_masuk'      => $totalMasuk,
            'total_keluar'     => $totalKeluar,
            'selisih'          => $totalMasuk - $totalKeluar,
        ];
    }

    // Fungsi pendukung: generate kode barang berdasarkan kategori
    public static function generateKodeBarang(string $namaKategori): string
    {
        $prefix = strtoupper(substr($namaKategori, 0, 3)); // 3 huruf pertama
        $last   = \App\Models\Barang::where('kode_barang', 'like', $prefix . '-%')
            ->orderByDesc('kode_barang')->first();
        $next   = $last ? ((int) substr($last->kode_barang, strlen($prefix) + 1)) + 1 : 1;
        return $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
        return in_array($this->role, ['admin', 'petugas']);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function isAdmin(): bool   { return $this->role === 'admin'; }
    public function isPetugas(): bool { return $this->role === 'petugas'; }
}
```

### app/Models/Kategori.php
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $fillable = ['nama', 'keterangan'];

    public function barang(): HasMany { return $this->hasMany(Barang::class); }
}
```

### app/Models/Barang.php
```php
<?php
namespace App\Models;

use App\Support\InventarisHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    protected $fillable = [
        'kategori_id', 'kode_barang', 'nama', 'satuan', 'stok', 'stok_minimum',
    ];

    public function kategori(): BelongsTo { return $this->belongsTo(Kategori::class); }
    public function transaksi(): HasMany  { return $this->hasMany(Transaksi::class); }

    // accessor: status stok dihitung saat dipanggil, tidak disimpan ke kolom
    // karena bergantung pada perbandingan dua kolom (stok vs stok_minimum)
    public function getStatusStokAttribute(): string
    {
        return InventarisHelper::tentukanStatusStok($this->stok, $this->stok_minimum);
    }
}
```

### app/Models/Transaksi.php — TITIK INTEGRASI OOP + TERSTRUKTUR
```php
<?php
namespace App\Models;

use App\Support\InventarisHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaksi extends Model
{
    protected $fillable = [
        'barang_id', 'user_id', 'jenis', 'jumlah',
        'keterangan', 'tanggal', 'stok_sesudah',
    ];

    // TITIK INTEGRASI: OOP memanggil fungsi terstruktur
    protected static function booted(): void
    {
        // sebelum disimpan: hitung stok_sesudah otomatis
        static::saving(function (Transaksi $t) {
            $barang      = \App\Models\Barang::find($t->barang_id);
            $t->stok_sesudah = InventarisHelper::hitungStokSesudah(
                $barang->stok, $t->jenis, $t->jumlah
            );
        });

        // setelah dibuat: update stok barang
        static::created(function (Transaksi $t) {
            $t->barang->update(['stok' => $t->stok_sesudah]);
        });

        // setelah diupdate: update stok barang
        static::updated(function (Transaksi $t) {
            $t->barang->update(['stok' => $t->stok_sesudah]);
        });
    }

    public function barang(): BelongsTo { return $this->belongsTo(Barang::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
```

---

## E. SEEDER

File: database/seeders/DatabaseSeeder.php
```php
<?php
namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'name' => 'Administrator', 'email' => 'admin@inventaris.test',
            'password' => Hash::make('password'), 'role' => 'admin',
        ]);

        // Petugas
        User::create([
            'name' => 'Petugas Gudang', 'email' => 'petugas@inventaris.test',
            'password' => Hash::make('password'), 'role' => 'petugas',
        ]);

        // Kategori
        $elektronik = Kategori::create(['nama' => 'Elektronik', 'keterangan' => 'Peralatan elektronik']);
        $atk        = Kategori::create(['nama' => 'ATK', 'keterangan' => 'Alat tulis kantor']);
        $furnitur   = Kategori::create(['nama' => 'Furnitur', 'keterangan' => 'Perabot kantor']);

        // Barang (stok awal diisi manual karena belum ada transaksi)
        $laptop = Barang::create([
            'kategori_id' => $elektronik->id, 'kode_barang' => 'ELE-0001',
            'nama' => 'Laptop', 'satuan' => 'unit', 'stok' => 10, 'stok_minimum' => 3,
        ]);
        $kertas = Barang::create([
            'kategori_id' => $atk->id, 'kode_barang' => 'ATK-0001',
            'nama' => 'Kertas A4', 'satuan' => 'rim', 'stok' => 50, 'stok_minimum' => 10,
        ]);
        $kursi = Barang::create([
            'kategori_id' => $furnitur->id, 'kode_barang' => 'FUR-0001',
            'nama' => 'Kursi Kantor', 'satuan' => 'unit', 'stok' => 0, 'stok_minimum' => 5,
        ]);

        // Transaksi (stok_sesudah otomatis via saving, stok barang update via created)
        Transaksi::create([
            'barang_id' => $laptop->id, 'user_id' => $admin->id,
            'jenis' => 'keluar', 'jumlah' => 2,
            'keterangan' => 'Dipinjam lab komputer', 'tanggal' => '2026-06-01',
        ]);
        Transaksi::create([
            'barang_id' => $kertas->id, 'user_id' => $admin->id,
            'jenis' => 'masuk', 'jumlah' => 20,
            'keterangan' => 'Restock dari supplier', 'tanggal' => '2026-06-05',
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
php artisan make:policy KategoriPolicy --model=Kategori
php artisan make:policy BarangPolicy --model=Barang
php artisan make:policy TransaksiPolicy --model=Transaksi
```

### KategoriPolicy — admin kelola, petugas lihat
```php
<?php
namespace App\Policies;
use App\Models\Kategori;
use App\Models\User;

class KategoriPolicy
{
    public function viewAny(User $user): bool               { return true; }
    public function view(User $user, Kategori $k): bool     { return true; }
    public function create(User $user): bool                 { return $user->isAdmin(); }
    public function update(User $user, Kategori $k): bool   { return $user->isAdmin(); }
    public function delete(User $user, Kategori $k): bool   { return $user->isAdmin(); }
}
```

### BarangPolicy — admin kelola, petugas lihat
```php
<?php
namespace App\Policies;
use App\Models\Barang;
use App\Models\User;

class BarangPolicy
{
    public function viewAny(User $user): bool               { return true; }
    public function view(User $user, Barang $b): bool       { return true; }
    public function create(User $user): bool                 { return $user->isAdmin(); }
    public function update(User $user, Barang $b): bool     { return $user->isAdmin(); }
    public function delete(User $user, Barang $b): bool     { return $user->isAdmin(); }
}
```

### TransaksiPolicy — admin dan petugas bisa input
```php
<?php
namespace App\Policies;
use App\Models\Transaksi;
use App\Models\User;

class TransaksiPolicy
{
    public function viewAny(User $user): bool               { return true; }
    public function view(User $user, Transaksi $t): bool    { return true; }
    public function create(User $user): bool                 { return $user->isAdmin() || $user->isPetugas(); }
    public function update(User $user, Transaksi $t): bool  { return $user->isAdmin(); }
    public function delete(User $user, Transaksi $t): bool  { return $user->isAdmin(); }
}
```

---

## G. RESOURCE

```bash
php artisan make:filament-resource Kategori
php artisan make:filament-resource Barang
php artisan make:filament-resource Transaksi
```

### KategoriResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\KategoriResource\Pages;
use App\Models\Kategori;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KategoriResource extends Resource
{
    protected static ?string $model = Kategori::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Data Master';
    protected static ?string $modelLabel = 'Kategori';
    protected static ?string $pluralModelLabel = 'Kategori';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nama')
                ->required()->unique('kategoris', 'nama', ignoreRecord: true),
            Forms\Components\Textarea::make('keterangan')->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('keterangan')->limit(50),
            Tables\Columns\TextColumn::make('barang_count')->label('Jml Barang')
                ->counts('barang'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListKategoris::route('/'),
            'create' => Pages\CreateKategori::route('/create'),
            'edit'   => Pages\EditKategori::route('/{record}/edit'),
        ];
    }
}
```

### BarangResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\BarangResource\Pages;
use App\Models\Barang;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BarangResource extends Resource
{
    protected static ?string $model = Barang::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Data Master';
    protected static ?string $modelLabel = 'Barang';
    protected static ?string $pluralModelLabel = 'Barang';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('kategori_id')->label('Kategori')
                ->relationship('kategori', 'nama')
                ->searchable()->preload()->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $kategori = \App\Models\Kategori::find($state);
                        $set('kode_barang',
                            \App\Support\InventarisHelper::generateKodeBarang($kategori->nama));
                    }
                }),
            Forms\Components\TextInput::make('kode_barang')->label('Kode Barang')
                ->required()->unique('barangs', 'kode_barang', ignoreRecord: true),
            Forms\Components\TextInput::make('nama')->required(),
            Forms\Components\TextInput::make('satuan')->required()
                ->helperText('Contoh: pcs, unit, rim, kg, liter'),
            Forms\Components\TextInput::make('stok')
                ->numeric()->minValue(0)->default(0)->required(),
            Forms\Components\TextInput::make('stok_minimum')->label('Stok Minimum')
                ->numeric()->minValue(0)->default(5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('kode_barang')->label('Kode')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('nama')->searchable(),
            Tables\Columns\TextColumn::make('kategori.nama')->badge()->sortable(),
            Tables\Columns\TextColumn::make('satuan'),
            Tables\Columns\TextColumn::make('stok')->sortable(),
            Tables\Columns\TextColumn::make('stok_minimum')->label('Min'),
            // accessor status_stok — dihitung saat tampil, tidak disimpan ke kolom
            Tables\Columns\TextColumn::make('status_stok')->label('Status')->badge()
                ->color(fn ($state) => match ($state) {
                    'Aman'    => 'success',
                    'Menipis' => 'warning',
                    default   => 'danger',
                }),
        ])->filters([
            Tables\Filters\SelectFilter::make('kategori')
                ->relationship('kategori', 'nama'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBarangs::route('/'),
            'create' => Pages\CreateBarang::route('/create'),
            'edit'   => Pages\EditBarang::route('/{record}/edit'),
        ];
    }
}
```

### TransaksiResource.php
```php
<?php
namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Models\Transaksi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Transaksi';
    protected static ?string $modelLabel = 'Transaksi';
    protected static ?string $pluralModelLabel = 'Transaksi';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('barang_id')->label('Barang')
                ->relationship('barang', 'nama')
                ->getOptionLabelFromRecordUsing(fn ($r) => "{$r->nama} (stok: {$r->stok})")
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('jenis')
                ->options(['masuk' => 'Masuk', 'keluar' => 'Keluar'])
                ->required(),
            Forms\Components\TextInput::make('jumlah')
                ->numeric()->minValue(1)->required(),
            Forms\Components\DatePicker::make('tanggal')
                ->default(now())->required(),
            Forms\Components\Textarea::make('keterangan')->nullable(),
            // user_id diisi otomatis dari yang login
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('barang.nama')->label('Barang')->searchable(),
            Tables\Columns\TextColumn::make('barang.kategori.nama')->label('Kategori')->badge(),
            Tables\Columns\TextColumn::make('jenis')->badge()
                ->color(fn ($state) => $state === 'masuk' ? 'success' : 'danger'),
            Tables\Columns\TextColumn::make('jumlah')->sortable(),
            Tables\Columns\TextColumn::make('stok_sesudah')->label('Stok Sesudah'),
            Tables\Columns\TextColumn::make('tanggal')->date()->sortable(),
            Tables\Columns\TextColumn::make('user.name')->label('Diinput Oleh'),
            Tables\Columns\TextColumn::make('keterangan')->limit(30)->placeholder('-'),
        ])->filters([
            Tables\Filters\SelectFilter::make('jenis')
                ->options(['masuk' => 'Masuk', 'keluar' => 'Keluar']),
            Tables\Filters\SelectFilter::make('barang')
                ->relationship('barang', 'nama'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'edit'   => Pages\EditTransaksi::route('/{record}/edit'),
        ];
    }
}
```

### CreateTransaksi.php — isi user_id otomatis dari yang login
```php
<?php
namespace App\Filament\Resources\TransaksiResource\Pages;

use App\Filament\Resources\TransaksiResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTransaksi extends CreateRecord
{
    protected static string $resource = TransaksiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        return $data;
    }
}
```

---

## H. HALAMAN LAPORAN STOK

```bash
php artisan make:filament-page LaporanStok
```

File: app/Filament/Pages/LaporanStok.php
```php
<?php
namespace App\Filament\Pages;

use App\Models\Barang;
use App\Models\Transaksi;
use App\Support\InventarisHelper;
use Filament\Pages\Page;

class LaporanStok extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.laporan-stok';
    protected static ?string $title = 'Laporan Stok';
    protected static ?string $navigationGroup = 'Laporan';

    public function getLaporanData(): array
    {
        $semuaBarang     = Barang::with('kategori')->get();
        $semuaTransaksi  = Transaksi::all();
        $ringkasan       = InventarisHelper::olahLaporan($semuaTransaksi);

        return [
            'barang'    => $semuaBarang,
            'ringkasan' => $ringkasan,
        ];
    }
}
```

File: resources/views/filament/pages/laporan-stok.blade.php
```blade
<x-filament-panels::page>
    @php
        $data      = $this->getLaporanData();
        $barang    = $data['barang'];
        $ringkasan = $data['ringkasan'];
    @endphp

    {{-- Ringkasan transaksi --}}
    <x-filament::section>
        <x-slot name="heading">Ringkasan Transaksi</x-slot>
        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
            <div class="p-4 rounded bg-gray-50">
                <div class="text-gray-500">Total Transaksi</div>
                <div class="text-2xl font-bold">{{ $ringkasan['jumlah_transaksi'] }}</div>
            </div>
            <div class="p-4 rounded bg-green-50">
                <div class="text-gray-500">Total Masuk</div>
                <div class="text-2xl font-bold text-green-600">{{ $ringkasan['total_masuk'] }}</div>
            </div>
            <div class="p-4 rounded bg-red-50">
                <div class="text-gray-500">Total Keluar</div>
                <div class="text-2xl font-bold text-red-600">{{ $ringkasan['total_keluar'] }}</div>
            </div>
            <div class="p-4 rounded bg-blue-50">
                <div class="text-gray-500">Selisih</div>
                <div class="text-2xl font-bold text-blue-600">{{ $ringkasan['selisih'] }}</div>
            </div>
        </div>
    </x-filament::section>

    {{-- Daftar stok barang --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">Stok Barang Saat Ini</x-slot>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b font-semibold">
                    <th class="py-2">Kode</th><th>Nama</th><th>Kategori</th>
                    <th>Satuan</th><th>Stok</th><th>Min</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($barang as $b)
                <tr class="border-b">
                    <td class="py-2">{{ $b->kode_barang }}</td>
                    <td>{{ $b->nama }}</td>
                    <td>{{ $b->kategori->nama }}</td>
                    <td>{{ $b->satuan }}</td>
                    <td class="font-bold">{{ $b->stok }}</td>
                    <td>{{ $b->stok_minimum }}</td>
                    <td>
                        <span @class([
                            'font-semibold px-2 py-1 rounded text-xs',
                            'bg-green-100 text-green-700' => $b->status_stok === 'Aman',
                            'bg-yellow-100 text-yellow-700' => $b->status_stok === 'Menipis',
                            'bg-red-100 text-red-700' => $b->status_stok === 'Habis',
                        ])>{{ $b->status_stok }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
```

---

## I. CHECKLIST PENGUJIAN

```
LOGIN
[ ] admin@inventaris.test → lihat semua menu
[ ] petugas@inventaris.test → bisa tambah transaksi, tidak bisa hapus barang

KATEGORI (login admin)
[ ] Tambah kategori Elektronik, ATK, Furnitur
[ ] Hapus kategori → barang di bawahnya ikut terhapus (cascade)

BARANG (login admin)
[ ] Tambah barang → pilih kategori Elektronik → kode otomatis ELE-0001
[ ] Tambah barang ATK → kode ATK-0001
[ ] Kolom Status: Aman (hijau), Menipis (kuning), Habis (merah)

TRANSAKSI
[ ] Tambah transaksi keluar 2 laptop → stok laptop berkurang otomatis dari 10 → 8
[ ] Tambah transaksi masuk 20 kertas → stok kertas bertambah otomatis
[ ] Kolom stok_sesudah terisi otomatis
[ ] User yang menginput tercatat otomatis

LAPORAN STOK
[ ] Ringkasan total masuk/keluar tampil benar
[ ] Tabel stok semua barang tampil dengan status warna
```

---

## J. PERBEDAAN PENTING DARI SIPENA

### 1. Tidak ada anggota/siswa sebagai role
Hanya admin dan petugas. Tidak perlu Create/Edit page yang memisah User + profil.
Kedua role langsung dibuat lewat seeder tanpa tabel profil terpisah.

### 2. Status stok pakai accessor, bukan kolom
```php
// di model Barang — dihitung saat dipanggil, tidak disimpan ke database
public function getStatusStokAttribute(): string
{
    return InventarisHelper::tentukanStatusStok($this->stok, $this->stok_minimum);
}
```
Kenapa tidak disimpan: status bergantung pada perbandingan dua kolom (stok vs
stok_minimum). Kalau admin ubah stok_minimum, status berubah otomatis tanpa
perlu update kolom tambahan.

### 3. Event created DAN updated di Transaksi
Berbeda dengan SIPENA (hanya saving), Transaksi punya tiga event:
- saving: hitung stok_sesudah
- created: update stok barang setelah dibuat
- updated: update stok barang setelah diedit

Kenapa dipisah: stok_sesudah butuh dihitung SEBELUM disimpan (saving),
tapi update stok barang harus SETELAH transaksi tersimpan (punya ID).

### 4. User_id diisi otomatis di CreateTransaksi
Petugas yang login otomatis tercatat sebagai yang menginput.
Tidak perlu field user_id di form.

---

## K. URUTAN FILE YANG DIBUAT

```
app/
├── Models/
│   ├── User.php          (edit bawaan — tidak ada relasi profil)
│   ├── Kategori.php      (buat baru)
│   ├── Barang.php        (buat baru — punya accessor status_stok)
│   └── Transaksi.php     (buat baru — titik integrasi, 3 event)
├── Support/
│   └── InventarisHelper.php  (buat manual)
├── Policies/
│   ├── KategoriPolicy.php
│   ├── BarangPolicy.php
│   └── TransaksiPolicy.php
├── Filament/
│   ├── Resources/
│   │   ├── KategoriResource.php + Pages/
│   │   ├── BarangResource.php + Pages/
│   │   └── TransaksiResource.php + Pages/ (CreateTransaksi diisi user_id)
│   └── Pages/
│       └── LaporanStok.php
└── Providers/Filament/AdminPanelProvider.php (edit path + brandName)

database/
├── migrations/
│   ├── ..._create_users_table.php      (edit, tambah role+photo)
│   ├── ..._create_kategoris_table.php
│   ├── ..._create_barangs_table.php
│   └── ..._create_transaksis_table.php
└── seeders/DatabaseSeeder.php

resources/views/filament/pages/
└── laporan-stok.blade.php
```
