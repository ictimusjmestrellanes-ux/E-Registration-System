<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionHistory extends Model
{
    use HasFactory;

    protected $table = 'transaction_history';

    protected $fillable = [
        'transaction_id',
        'transaction_date',
        'category',
        'source',
        'type',
        'clerk',
        'status',
        'description',
        'actions_taken',
        'remarks',
        'amount',
        'subject_first_name',
        'subject_middle_name',
        'subject_last_name',
        'subject_name_ext',
        'subject_gender',
        'subject_birthdate',
        'subject_age',
        'subject_barangay',
        'subject_municipality',
        'subject_client_relation',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'subject_birthdate' => 'date',
        'subject_age' => 'integer',
        'amount' => 'decimal:2',
    ];

    const CATEGORIES = [
        'social_services' => 'SOCIAL SERVICES ASSISTANCE',
        'solicitation' => 'SOLICITATION',
        'youth_sports' => 'YOUTH & SPORTS',
        'appointments' => 'APPOINTMENTS',
        'infrastructure' => 'INFRASTRUCTURE',
        'scholarships' => 'SCHOLARSHIPS',
        'permits' => 'PERMITS',
        'events' => 'EVENTS',
        'job_application' => 'JOB APPLICATION',
        'hoa' => 'HOA',
        'others' => 'OTHERS',
    ];

    const TYPES = [
        'burial_assistance' => 'BURIAL ASSISTANCE',
        'educational_assistance' => 'EDUCATIONAL ASSISTANCE',
        'financial_balik_probinsya' => 'FINANCIAL ASSISTANCE - BALIK PROBINSYA',
        'financial_fire_victims' => 'FINANCIAL ASSISTANCE - FIRE VICTIMS',
        'medical_hospitalization' => 'MEDICAL ASSISTANCE - CONFINEMENT/HOSPITALIZATION',
        'medical_chemo_dialisys' => 'MEDICAL ASSISTANCE - CHEMO/DIALYSIS',
        'medical_regular' => 'MEDICAL ASSISTANCE - REGULAR MEDICATION',
        'subsistence_assistance' => 'SUBSISTENCE ASSISTANCE',
    ];

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? strtoupper(str_replace('_', ' ', $this->category));
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? strtoupper(str_replace('_', ' ', $this->type));
    }

    public function getSubjectFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->subject_first_name,
            $this->subject_middle_name,
            $this->subject_last_name,
            $this->subject_name_ext,
        ])));
    }

    public function getSubjectSummaryAttribute(): ?string
    {
        if (!$this->subject_full_name) {
            return null;
        }

        return collect([
            $this->subject_full_name,
            $this->subject_client_relation ? 'Relation: ' . $this->subject_client_relation : null,
            $this->subject_age !== null ? 'Age: ' . $this->subject_age : null,
            $this->subject_gender ? 'Gender: ' . $this->subject_gender : null,
            collect([$this->subject_barangay, $this->subject_municipality])->filter()->implode(', ') ?: null,
        ])->filter()->implode(' | ');
    }

    /**
     * Get the requirements for this transaction.
     */
    public function requirements()
    {
        return $this->hasMany(TransactionRequirement::class, 'transaction_id');
    }
}
