<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];
}
