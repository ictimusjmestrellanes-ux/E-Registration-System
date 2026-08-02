<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionEvent extends Model
{
    use HasFactory;

    protected $table = 'transaction_events';

    protected $fillable = [
        'full_name',
        'contact_no',
        'address',
        'age',
        'birth_date',
        'client_category',
        'transaction_category',
        'transaction_type',
        'transferred_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'transferred_at' => 'datetime',
        'age' => 'integer',
    ];
}
