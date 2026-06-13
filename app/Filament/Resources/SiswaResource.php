<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiswaResource\Pages;
use App\Filament\Resources\SiswaResource\RelationManagers;
use App\Models\Siswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiswaResource extends Resource
{
    protected static ?string $model = Siswa::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Siswa';
    protected static ?string $pluralModelLabel = 'Siswa';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
        Forms\Components\TextInput::make('email')->email()->required()
            ->unique('users', 'email', ignorable: fn ($record) => $record?->user),
        Forms\Components\TextInput::make('password')->password()->revealable()
            ->required(fn (string $operation) => $operation === 'create')
            ->dehydrated(fn ($state) => filled($state))
            ->helperText('Kosongkan saat edit bila tidak ganti password.'),
        Forms\Components\TextInput::make('nis')->label('NIS')->required()
            ->default(fn () => \App\Models\Siswa::generateNis())
            ->unique(ignoreRecord: true),
        Forms\Components\TextInput::make('kelas')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                 Tables\Columns\TextColumn::make('nis')->label('NIS')->searchable()->sortable(),
        Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
        Tables\Columns\TextColumn::make('kelas')->badge()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListSiswas::route('/'),
            'create' => Pages\CreateSiswa::route('/create'),
            'edit' => Pages\EditSiswa::route('/{record}/edit'),
        ];
    }
}
