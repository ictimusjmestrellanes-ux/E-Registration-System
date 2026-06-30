<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionRequirement extends Model
{
    use HasFactory;

    protected $table = 'transaction_requirements';

    protected $fillable = [
        'transaction_id',
        'requirement_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Get the transaction that owns the requirement.
     */
    public function transaction()
    {
        return $this->belongsTo(TransactionHistory::class, 'transaction_id');
    }
}
