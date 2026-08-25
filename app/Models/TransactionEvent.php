<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'event_date',
        'transferred_at',
        'transferred_transaction_id',
        'not_duplicate',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'event_date' => 'date',
        'transferred_at' => 'datetime',
        'transferred_transaction_id' => 'integer',
        'age' => 'integer',
        'not_duplicate' => 'boolean',
    ];

    public function transferredTransaction(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'transferred_transaction_id');
    }
}
