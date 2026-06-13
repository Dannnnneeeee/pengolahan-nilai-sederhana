<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    protected $table = 'siswa';
    protected $fillable = ['user_id', 'nis', 'kelas'];
    public function user():BelongsTo{return $this->belongsTo(User::class);}
    public function nilai():HasMany{return $this->hasMany(Nilai::class);}
    public static function generateNis(): string
    {
        $tahun = date('y');
        $last = static::where('nis', 'like', $tahun . '%')->orderByDesc('nis')->first();
        $next = $last ? ((int) substr($last->nis, 2)) + 1 : 1;
        return $tahun . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

}
