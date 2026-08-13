<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature',
        'role_name',
        'allowed',
    ];

    protected $casts = [
        'allowed' => 'boolean',
    ];
}