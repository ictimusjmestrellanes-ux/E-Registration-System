<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'age',
        'birth_date',
        'birthplace',
        'education',
        'course',
        'sector',
        'position_organization',
        'gender',
        'civil_status',
        'email',
        'contact',
        'contact_2',
        'address',
        'province',
        'city',
        'barangay',
        'photo_path',
        'fingerprint_path',
        'fingerprint_template',
    ];

    public static function generateClientId(): string
    {
        $year = now()->format('y');
        $prefix = $year;

        $latest = self::query()
            ->where('client_id', 'like', "{$prefix}%")
            ->orderBy('client_id', 'desc')
            ->value('client_id');

        if ($latest) {
            $num = (int) substr($latest, -5) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function getPhotoUrlAttribute(): string
    {
        if (!empty($this->photo_path) && Storage::disk('public')->exists($this->photo_path)) {
            return asset('storage/' . $this->photo_path);
        }

        return asset('assets/images/profile.png');
    }

    public function getFingerprintUrlAttribute(): string
    {
        if (!empty($this->fingerprint_path) && Storage::disk('public')->exists($this->fingerprint_path)) {
            return asset('storage/' . $this->fingerprint_path);
        }

        return asset('assets/images/fingerprint.png');
    }

    public function getFullNameAttribute(): string
    {
        return mb_strtoupper(trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ]))));
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'client_id', 'client_id');
    }
}
