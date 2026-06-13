<?php

namespace App\Filament\Resources\GuruResource\Pages;

use App\Filament\Resources\GuruResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGuru extends CreateRecord
{
    protected static string $resource = GuruResource::class;
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
{
    $user = \App\Models\User::create([
        'name' => $data['name'], 'email' => $data['email'],
        'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
        'role' => 'guru',
    ]);
    return \App\Models\Guru::create([
        'user_id' => $user->id,
        'kode_guru' => $data['kode_guru'],
        'mata_pelajaran' => $data['mata_pelajaran'],
    ]);
}
}
