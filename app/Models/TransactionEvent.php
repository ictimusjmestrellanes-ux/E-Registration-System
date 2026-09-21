<?php

namespace App\Models;

use App\Support\ImportName;
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
        'sector',
        'status',
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

    protected $attributes = [
        'status' => 'Pending',
    ];

    public const STATUSES = ['Pending', 'Claimed', 'Unclaimed'];

    public function getDisplayNameAttribute(): string
    {
        return ImportName::format((string) $this->full_name);
    }

    public function transferredTransaction(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'transferred_transaction_id');
    }

    protected static function booted(): void
    {
        static::saving(static function (self $event): void {
            if ($event->isDirty('full_name') || $event->display_name_sort === null) {
                $event->display_name_sort = mb_strtolower(ImportName::format((string) $event->full_name));
            }
        });

        // Event imports, transfers, and undo operations can affect the
        // dashboard's caravan trend. Share the same request-level cache
        // invalidation as transaction history writes.
        static::created(static function (self $event): void {
            TransactionHistory::flushDashboardCache();
        });

        static::updated(static function (self $event): void {
            TransactionHistory::flushDashboardCache();
        });

        static::deleted(static function (self $event): void {
            TransactionHistory::flushDashboardCache();
        });
    }
}
