<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Activity actions surfaced in the navbar notification dropdown:
     * import / transfer / force-create / tag / delete / undo updates.
     */
    public const NOTIFICATION_ACTIONS = [
        'events_imported',
        'event_transferred',
        'events_transfer_selected',
        'event_force_created',
        'events_force_created_all',
        'event_status_tagged',
        'events_status_tagged',
        'event_record_edited',
        'events_marked_not_duplicate',
        'events_not_duplicate_review_undone',
        'duplicate_event_clients_merged',
        'event_deleted',
        'events_bulk_deleted',
        'event_transfer_undone',
        'events_transfer_undone',
        'payroll_pdf_exported',
        'payroll_xlsx_exported',
        'event_records_xlsx_exported',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'subject_id');
    }

    public function subjectTransactionHistory(): BelongsTo
    {
        return $this->belongsTo(TransactionHistory::class, 'subject_id');
    }

    public function subjectTransactionEvent(): BelongsTo
    {
        return $this->belongsTo(TransactionEvent::class, 'subject_id');
    }

    /**
     * Resolve the public Client ID represented by this activity's subject.
     */
    public function getRelatedClientIdAttribute(): ?string
    {
        $clientId = match ($this->subject_type) {
            'Client' => $this->subjectClient?->client_id,
            'TransactionHistory' => $this->subjectTransactionHistory?->client_id,
            'TransactionEvent' => $this->subjectTransactionEvent?->transferredTransaction?->client_id,
            default => null,
        };

        if (filled($clientId)) {
            return (string) $clientId;
        }

        foreach (['client_id', 'target_client_id', 'after.client_id', 'before.client_id'] as $key) {
            $value = data_get($this->properties, $key);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Resolve the client/event name represented by this activity.
     */
    public function getRelatedClientNameAttribute(): ?string
    {
        $name = match ($this->subject_type) {
            'Client' => $this->subjectClient?->full_name,
            'TransactionHistory' => $this->subjectTransactionHistory?->client?->full_name,
            'TransactionEvent' => $this->subjectTransactionEvent?->transferredTransaction?->client?->full_name
                ?: $this->subjectTransactionEvent?->full_name,
            default => null,
        };

        if (filled($name)) {
            return (string) $name;
        }

        $properties = $this->properties;
        foreach (['full_name', 'client_name', 'after.full_name', 'before.full_name'] as $key) {
            $value = data_get($properties, $key);
            if (filled($value)) {
                return (string) $value;
            }
        }

        foreach (['after', 'before'] as $snapshotKey) {
            $snapshot = data_get($properties, $snapshotKey, []);
            if (! is_array($snapshot)) {
                continue;
            }

            $value = trim(implode(' ', array_filter([
                $snapshot['first_name'] ?? null,
                $snapshot['middle_name'] ?? null,
                $snapshot['last_name'] ?? null,
                $snapshot['suffix'] ?? null,
            ], fn ($part) => filled($part))));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
