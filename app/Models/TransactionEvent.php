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
    ];
}
