<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Payments\Models\Payment;

class Client extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'common_user_id',
        'reference_no',
        'full_name',
        'passport_no',
        'nic',
        'phone',
        'email',
        'country',
        'native_country',
        'visa_type',
        'service_category',
        'agreement_amount',
        'paid_amount',
        'profile_photo_file_id',
        'assigned_supervisor_id',
        'current_stage',
        'visa_outcome',
        'outcome_recorded_at',
        'outcome_recorded_by',
        'decision_file_id',
        'refusal_reason',
        'appeal_status',
        'appeal_due_at',
        'appeal_notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'outcome_recorded_at' => 'datetime',
        'appeal_due_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            // Stamp the decision date automatically, unless the operator is
            // explicitly backdating it to when the authority actually decided.
            if ($client->isDirty('visa_outcome') && $client->visa_outcome && ! $client->isDirty('outcome_recorded_at')) {
                $client->outcome_recorded_at = now();
            }
        });
    }

    public function getBalanceAttribute(): int
    {
        return max(0, $this->agreement_amount - $this->paid_amount);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function adminSummary(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClientAdminSummary::class);
    }

    public function applicationUnit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClientApplicationUnit::class);
    }

    public function responsibilityNotice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClientResponsibilityNotice::class);
    }

    public function caseClosure(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClientCaseClosure::class);
    }

    public function visaSubmission(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VisaSubmission::class);
    }

    public function supervisorReviews(): HasMany
    {
        return $this->hasMany(SupervisorReview::class);
    }

    public function authorityRequests(): HasMany
    {
        return $this->hasMany(AuthorityRequest::class);
    }

    public function decisionFile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'decision_file_id');
    }

    public function profilePhoto(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'profile_photo_file_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(\Modules\Files\Models\File::class);
    }

    public function documentationTasks(): HasMany
    {
        return $this->hasMany(DocumentationTask::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class);
    }

    public function commonUser(): BelongsTo
    {
        return $this->belongsTo(CommonUser::class, 'common_user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_supervisor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
