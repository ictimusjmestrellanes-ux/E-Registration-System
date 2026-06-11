<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'age',
        'gender',
        'civil_status',
        'email',
        'contact',
        'address',
        'province',
        'city',
        'barangay',
        'photo_path',
        'fingerprint_path',
        'fingerprint_template',
    ];

    public function getPhotoUrlAttribute(): string
    {
        if (!empty($this->photo_path) && Storage::disk('public')->exists($this->photo_path)) {
            return asset('storage/' . $this->photo_path);
        }

        return asset('assets/images/avatar-1.jpg');
    }

    public function getFingerprintUrlAttribute(): string
    {
        if (!empty($this->fingerprint_path) && Storage::disk('public')->exists($this->fingerprint_path)) {
            return asset('storage/' . $this->fingerprint_path);
        }

        return asset('assets/images/fingerprint.png');
    }
}
