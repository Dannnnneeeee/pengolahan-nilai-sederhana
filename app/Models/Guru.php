<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guru extends Model
{
    protected $table = 'guru';
    protected $fillable = ['user_id', 'kode_guru','mata_pelajaran'];
    public function user():BelongsTo{return $this->belongsTo(User::class);}
    public function nilai():HasMany{return $this->hasMany(Nilai::class);}
    public static function generateKode(string $mataPelajaran): string
    {
        $prefix = match ($mataPelajaran) {
            'Matematika'       => 'MT',
            'IPA'              => 'IPA',
            'Bahasa Indonesia' => 'IND',
            default            => 'G',
        };
        $last = static::where('kode_guru', 'like', $prefix . '-%')
            ->orderByDesc('kode_guru')->first();
        $next = $last ? ((int) substr($last->kode_guru, strlen($prefix) + 1)) + 1 : 1;
        return $prefix . '-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
