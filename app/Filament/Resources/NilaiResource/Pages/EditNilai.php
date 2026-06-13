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
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function mutateFormDataBeforeFill(array $data): array
{
    $data['kelas'] = $this->record->siswa->kelas;
    return $data;
}
}
