<?php

namespace App\Filament\Resources\NilaiResource\Pages;

use App\Filament\Resources\NilaiResource;
use Filament\Actions;
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
