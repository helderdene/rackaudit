<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\HelpInteractionType;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Loggable, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Notification preference category constants.
     */
    public const NOTIFICATION_CATEGORY_AUDIT_ASSIGNMENTS = 'audit_assignments';

    public const NOTIFICATION_CATEGORY_FINDING_UPDATES = 'finding_updates';

    public const NOTIFICATION_CATEGORY_APPROVAL_REQUESTS = 'approval_requests';

    public const NOTIFICATION_CATEGORY_DISCREPANCIES = 'discrepancies';

    public const NOTIFICATION_CATEGORY_SCHEDULED_REPORTS = 'scheduled_reports';

    /**
     * All available notification categories.
     *
     * @var list<string>
     */
    public const NOTIFICATION_CATEGORIES = [
        self::NOTIFICATION_CATEGORY_AUDIT_ASSIGNMENTS,
        self::NOTIFICATION_CATEGORY_FINDING_UPDATES,
        self::NOTIFICATION_CATEGORY_APPROVAL_REQUESTS,
        self::NOTIFICATION_CATEGORY_DISCREPANCIES,
        self::NOTIFICATION_CATEGORY_SCHEDULED_REPORTS,
    ];

    /**
     * Fields to exclude from activity logging.
     *
     * @var list<string>
     */
    protected array $excludeFromActivityLog = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_active_at',
        'discrepancy_notifications',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Get the datacenters that the user has access to.
     */
    public function datacenters(): BelongsToMany
    {
        return $this->belongsToMany(Datacenter::class)->withTimestamps();
    }

    /**
     * Get all help interactions for this user.
     */
    public function helpInteractions(): HasMany
    {
        return $this->hasMany(UserHelpInteraction::class);
    }

    /**
     * Get the IDs of help articles that the user has dismissed.
     *
     * @return Collection<int, int>
     */
    public function dismissedHelpArticles(): Collection
    {
        return $this->helpInteractions()
            ->where('interaction_type', HelpInteractionType::Dismissed)
            ->whereNotNull('help_article_id')
            ->pluck('help_article_id');
    }

    /**
     * Get the IDs of help tours that the user has completed.
     *
     * @return Collection<int, int>
     */
    public function completedTours(): Collection
    {
        return $this->helpInteractions()
            ->where('interaction_type', HelpInteractionType::CompletedTour)
            ->whereNotNull('help_tour_id')
            ->pluck('help_tour_id');
    }

    /**
     * Check if the user has viewed a specific article.
     */
    public function hasViewedArticle(int $articleId): bool
    {
        return $this->helpInteractions()
            ->where('help_article_id', $articleId)
            ->where('interaction_type', HelpInteractionType::Viewed)
            ->exists();
    }

    /**
     * Check if the user has completed a specific tour.
     */
    public function hasCompletedTour(int $tourId): bool
    {
        return $this->helpInteractions()
            ->where('help_tour_id', $tourId)
            ->where('interaction_type', HelpInteractionType::CompletedTour)
            ->exists();
    }

    /**
     * Check if the user status is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the user status is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Check if the user status is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if the user has email notifications enabled for a given category.
     *
     * Uses an opt-out model: defaults to true (enabled) if not explicitly set to false.
     */
    public function hasEmailEnabledFor(string $category): bool
    {
        $preferences = $this->notification_preferences;

        // If no preferences set, default to enabled (opt-out model)
        if ($preferences === null) {
            return true;
        }

        // If category not explicitly set, default to enabled (opt-out model)
        if (! array_key_exists($category, $preferences)) {
            return true;
        }

        return (bool) $preferences[$category];
    }

    /**
     * Get the default notification preferences.
     *
     * All categories default to enabled (opt-out model).
     *
     * @return array<string, bool>
     */
    public static function getDefaultNotificationPreferences(): array
    {
        return [
            self::NOTIFICATION_CATEGORY_AUDIT_ASSIGNMENTS => true,
            self::NOTIFICATION_CATEGORY_FINDING_UPDATES => true,
            self::NOTIFICATION_CATEGORY_APPROVAL_REQUESTS => true,
            self::NOTIFICATION_CATEGORY_DISCREPANCIES => true,
            self::NOTIFICATION_CATEGORY_SCHEDULED_REPORTS => true,
        ];
    }

    /**
     * Check if the user wants to receive all discrepancy notifications.
     */
    public function wantsAllDiscrepancyNotifications(): bool
    {
        return $this->discrepancy_notifications === 'all';
    }

    /**
     * Check if the user wants only threshold-based discrepancy notifications.
     */
    public function wantsThresholdOnlyDiscrepancyNotifications(): bool
    {
        return $this->discrepancy_notifications === 'threshold_only';
    }

    /**
     * Check if the user wants no discrepancy notifications.
     */
    public function wantsNoDiscrepancyNotifications(): bool
    {
        return $this->discrepancy_notifications === 'none';
    }

    /**
     * Get the default notification preference based on the user's role.
     * IT Managers default to 'all', others default to 'none'.
     */
    public function getDefaultDiscrepancyNotificationPreference(): string
    {
        if ($this->hasRole('IT Manager') || $this->hasRole('Administrator')) {
            return 'all';
        }

        if ($this->hasRole('Auditor')) {
            return 'threshold_only';
        }

        return 'none';
    }
}
