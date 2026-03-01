<?php

namespace App\Models;

use App\Enums\DiscrepancyType;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Finding model representing a discrepancy discovered during an audit.
 *
 * Findings are automatically created when a connection or device verification is marked
 * as discrepant or not found during an audit. They track issues that need resolution and
 * link back to the specific verification that identified the problem.
 */
class Finding extends Model
{
    /** @use HasFactory<\Database\Factories\FindingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'audit_id',
        'audit_connection_verification_id',
        'audit_device_verification_id',
        'discrepancy_type',
        'title',
        'description',
        'status',
        'severity',
        'assigned_to',
        'finding_category_id',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'due_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discrepancy_type' => DiscrepancyType::class,
            'status' => FindingStatus::class,
            'severity' => FindingSeverity::class,
            'resolved_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    /**
     * Get the audit this finding belongs to.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Get the connection verification that created this finding.
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(AuditConnectionVerification::class, 'audit_connection_verification_id');
    }

    /**
     * Get the device verification that created this finding.
     */
    public function deviceVerification(): BelongsTo
    {
        return $this->belongsTo(AuditDeviceVerification::class, 'audit_device_verification_id');
    }

    /**
     * Get the user who resolved this finding.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the user assigned to this finding.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the category of this finding.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FindingCategory::class, 'finding_category_id');
    }

    /**
     * Get all evidence attached to this finding.
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(FindingEvidence::class);
    }

    /**
     * Get all status transitions for this finding.
     */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(FindingStatusTransition::class);
    }

    /**
     * Scope to filter findings by status.
     */
    public function scopeFilterByStatus(Builder $query, FindingStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter findings by severity.
     */
    public function scopeFilterBySeverity(Builder $query, FindingSeverity $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to filter findings by category.
     */
    public function scopeFilterByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('finding_category_id', $categoryId);
    }

    /**
     * Scope to filter findings by assignee.
     */
    public function scopeFilterByAssignee(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope to search findings by title or description.
     */
    public function scopeSearchByTitleOrDescription(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to filter findings that are overdue (past due date).
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay());
    }

    /**
     * Scope to filter findings that are due soon (within 3 days, not overdue).
     */
    public function scopeDueSoon(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->where('due_date', '<=', now()->addDays(3)->endOfDay());
    }

    /**
     * Scope to filter findings with no due date set.
     */
    public function scopeNoDueDate(Builder $query): Builder
    {
        return $query->whereNull('due_date');
    }

    /**
     * Check if this finding is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        return $this->due_date->startOfDay()->lt(now()->startOfDay());
    }

    /**
     * Check if this finding is due soon (within 3 days, not overdue).
     */
    public function isDueSoon(): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        $dueDate = $this->due_date->startOfDay();
        $today = now()->startOfDay();
        $threeDaysFromNow = now()->addDays(3)->endOfDay();

        return $dueDate->gte($today) && $dueDate->lte($threeDaysFromNow);
    }

    /**
     * Get the time to first response in minutes.
     *
     * First response is defined as the first transition from Open to InProgress.
     * Returns null if no such transition exists.
     */
    public function getTimeToFirstResponse(): ?int
    {
        $firstResponse = $this->statusTransitions()
            ->where('from_status', FindingStatus::Open)
            ->where('to_status', FindingStatus::InProgress)
            ->orderBy('transitioned_at', 'asc')
            ->first();

        if ($firstResponse === null) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($firstResponse->transitioned_at);
    }

    /**
     * Get the total resolution time in minutes.
     *
     * Resolution time is calculated from finding creation to when it was resolved.
     * Returns null if the finding is not resolved.
     */
    public function getTotalResolutionTime(): ?int
    {
        if ($this->resolved_at === null) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->resolved_at);
    }
}
