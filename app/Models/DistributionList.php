<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Distribution list for managing named groups of email recipients.
 *
 * Distribution lists are used for scheduling report deliveries to multiple
 * recipients. Each list belongs to a user and contains multiple email
 * addresses as members.
 */
class DistributionList extends Model
{
    /** @use HasFactory<\Database\Factories\DistributionListFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    /**
     * Get the user that owns this distribution list.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the members of this distribution list.
     */
    public function members(): HasMany
    {
        return $this->hasMany(DistributionListMember::class)->orderBy('sort_order');
    }
}
