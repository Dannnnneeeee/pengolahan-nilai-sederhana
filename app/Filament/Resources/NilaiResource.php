<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NilaiResource\Pages;
use App\Filament\Resources\NilaiResource\RelationManagers;
use App\Models\Nilai;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NilaiResource extends Resource
{
    protected static ?string $model = Nilai::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Nilai';
    protected static ?string $pluralModelLabel = 'Nilai';

    public static function form(Form $form): Form
    {
         $user = \Illuminate\Support\Facades\Auth::user();
        return $form
            ->schema([
             Forms\Components\Select::make('kelas')->label('Kelas')
            ->options(fn () => \App\Models\Siswa::query()->distinct()->orderBy('kelas')->pluck('kelas', 'kelas'))
            ->live()
            ->afterStateUpdated(fn (callable $set) => $set('siswa_id', null))
            ->dehydrated(false)   // hanya alat bantu, tidak disimpan
            ->required(),

        Forms\Components\Select::make('siswa_id')->label('Siswa')
            ->options(fn (callable $get) => \App\Models\Siswa::query()
                ->when($get('kelas'), fn ($q, $kelas) => $q->where('kelas', $kelas))
                ->with('user')->get()->pluck('user.name', 'id'))
            ->searchable()->required(),

        Forms\Components\Select::make('guru_id')->label('Guru / Mapel')
            ->relationship('guru', 'mata_pelajaran')
            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->mata_pelajaran} ({$record->kode_guru})")
            ->default($user->isGuru() ? $user->guru?->id : null)
            ->disabled($user->isGuru())->dehydrated()->required(),

        Forms\Components\TextInput::make('nilai_tugas')->numeric()->minValue(0)->maxValue(100)->required(),
        Forms\Components\TextInput::make('nilai_uts')->numeric()->minValue(0)->maxValue(100)->required(),
        Forms\Components\TextInput::make('nilai_uas')->numeric()->minValue(0)->maxValue(100)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nis')->label('NIS')->searchable(),
        Tables\Columns\TextColumn::make('siswa.user.name')->label('Siswa')->searchable(),
        Tables\Columns\TextColumn::make('siswa.kelas')->label('Kelas')->badge(),
        Tables\Columns\TextColumn::make('guru.mata_pelajaran')->label('Mapel')->badge(),
        Tables\Columns\TextColumn::make('nilai_tugas')->label('Tugas'),
        Tables\Columns\TextColumn::make('nilai_uts')->label('UTS'),
        Tables\Columns\TextColumn::make('nilai_uas')->label('UAS'),
        Tables\Columns\TextColumn::make('nilai_akhir')->weight('bold')->sortable(),
        Tables\Columns\TextColumn::make('status')->badge()
            ->color(fn ($state) => $state === 'Lulus' ? 'success' : 'danger'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('rapor')
    ->label('Rapor')->icon('heroicon-o-document-arrow-down')->color('success')
    ->action(fn (\App\Models\Nilai $record) => \App\Support\RaporService::unduh($record->siswa)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
{
    $query = parent::getEloquentQuery();
    $user = \Illuminate\Support\Facades\Auth::user();
    if ($user->isGuru()) {
        $query->where('guru_id', $user->guru?->id);
    }
    return $query;
}

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNilais::route('/'),
            'create' => Pages\CreateNilai::route('/create'),
            'edit' => Pages\EditNilai::route('/{record}/edit'),
        ];
    }
}
