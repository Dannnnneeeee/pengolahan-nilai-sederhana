<?php

namespace App\Filament\Resources\SiswaResource\Pages;

use App\Filament\Resources\SiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSiswa extends CreateRecord
{
    protected static string $resource = SiswaResource::class;
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
{
    $user = \App\Models\User::create([
        'name' => $data['name'], 'email' => $data['email'],
        'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
        'role' => 'siswa',
    ]);
    return \App\Models\Siswa::create([
        'user_id' => $user->id, 'nis' => $data['nis'], 'kelas' => $data['kelas'],
    ]);
}
}
