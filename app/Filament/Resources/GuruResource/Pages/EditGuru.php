<?php

namespace App\Filament\Resources\GuruResource\Pages;

use App\Filament\Resources\GuruResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuru extends EditRecord
{
    protected static string $resource = GuruResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function mutateFormDataBeforeFill(array $data): array
{
    $data['name'] = $this->record->user->name;
    $data['email'] = $this->record->user->email;
    return $data;
}
protected function handleRecordUpdate($record, array $data): \Illuminate\Database\Eloquent\Model
{
    $u = ['name' => $data['name'], 'email' => $data['email']];
    if (filled($data['password'] ?? null)) {
        $u['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
    }
    $record->user->update($u);
    $record->update(['kode_guru' => $data['kode_guru'], 'mata_pelajaran' => $data['mata_pelajaran']]);
    return $record;
}
}
