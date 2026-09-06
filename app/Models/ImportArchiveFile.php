<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportArchiveFile extends Model
{
    protected $fillable = [
        'filename',
        'original_filename',
        'rows_count',
        'file_size',
        'source',
        'imported_by_id',
        'imported_by',
        'role',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];
}
