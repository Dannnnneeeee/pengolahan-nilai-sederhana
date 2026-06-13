<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuruResource\Pages;
use App\Filament\Resources\GuruResource\RelationManagers;
use App\Models\Guru;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GuruResource extends Resource
{
    protected static ?string $model = Guru::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $modelLabel = 'Guru';
    protected static ?string $pluralModelLabel = 'Guru';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            Forms\Components\TextInput::make('name')->label('Nama')->required(),
        Forms\Components\TextInput::make('email')->email()->required()
            ->unique('users', 'email', ignorable: fn ($record) => $record?->user),
        Forms\Components\TextInput::make('password')->password()->revealable()
            ->required(fn (string $operation) => $operation === 'create')
            ->dehydrated(fn ($state) => filled($state)),
        Forms\Components\Select::make('mata_pelajaran')->required()
            ->options([
                'Matematika' => 'Matematika',
                'IPA' => 'IPA',
                'Bahasa Indonesia' => 'Bahasa Indonesia',
            ])
            ->live()
            ->afterStateUpdated(fn ($state, callable $set) =>
                $state ? $set('kode_guru', \App\Models\Guru::generateKode($state)) : null),
        Forms\Components\TextInput::make('kode_guru')->label('Kode Guru')->required()
            ->unique(ignoreRecord: true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                        Tables\Columns\TextColumn::make('kode_guru')->label('Kode Guru')->searchable()->sortable(),
        Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
        Tables\Columns\TextColumn::make('mata_pelajaran')->badge()->sortable(),
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
            'index' => Pages\ListGurus::route('/'),
            'create' => Pages\CreateGuru::route('/create'),
            'edit' => Pages\EditGuru::route('/{record}/edit'),
        ];
    }
}
