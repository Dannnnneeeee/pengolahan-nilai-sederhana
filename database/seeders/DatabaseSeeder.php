<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

         User::create([
            'name' => 'Administrator', 'email' => 'admin@sekolah.test',
            'password' => Hash::make('password'), 'role' => 'admin',
        ]);

        // Guru (dua Matematika untuk bukti banyak guru per mapel)
        $dataGuru = [
            ['Budi Santoso', 'budi@sekolah.test', 'MT-001', 'Matematika'],
            ['Citra Dewi',   'citra@sekolah.test', 'MT-002', 'Matematika'],
            ['Siti Aminah',  'siti@sekolah.test', 'IPA-001', 'IPA'],
            ['Andi Wijaya',  'andi@sekolah.test', 'IND-001', 'Bahasa Indonesia'],
        ];
        $gurus = [];
        foreach ($dataGuru as [$nama, $email, $kode, $mapel]) {
            $u = User::create(['name' => $nama, 'email' => $email, 'password' => Hash::make('password'), 'role' => 'guru']);
            $gurus[] = Guru::create(['user_id' => $u->id, 'kode_guru' => $kode, 'mata_pelajaran' => $mapel]);
        }

        // Siswa
        $dataSiswa = [
            ['Ahmad Fauzi', 'ahmad@sekolah.test', '260001', '6A'],
            ['Dewi Lestari', 'dewi@sekolah.test', '260002', '6A'],
            ['Rudi Hartono', 'rudi@sekolah.test', '260003', '6B'],
        ];
        $siswas = [];
        foreach ($dataSiswa as [$nama, $email, $nis, $kelas]) {
            $u = User::create(['name' => $nama, 'email' => $email, 'password' => Hash::make('password'), 'role' => 'siswa']);
            $siswas[] = Siswa::create(['user_id' => $u->id, 'nis' => $nis, 'kelas' => $kelas]);
        }

        // Nilai: tiap siswa dinilai oleh guru IPA, B.Indonesia, dan SATU guru Matematika (MT-001)
        // (tidak semua guru menilai semua siswa, supaya realistis)
        $guruPenilai = [$gurus[0], $gurus[2], $gurus[3]]; // MT-001, IPA, IND
        $contoh = [[85, 78, 90], [70, 65, 80], [60, 55, 68]];
        foreach ($siswas as $i => $siswa) {
            foreach ($guruPenilai as $j => $guru) {
                [$t, $uts, $uas] = $contoh[($i + $j) % 3];
                Nilai::create([
                    'siswa_id' => $siswa->id, 'guru_id' => $guru->id,
                    'nilai_tugas' => $t, 'nilai_uts' => $uts, 'nilai_uas' => $uas,
                ]);
            }
        }
    }
}
