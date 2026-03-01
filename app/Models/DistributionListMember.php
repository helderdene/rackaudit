<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Distribution list member representing an email recipient in a distribution list.
 *
 * Each member belongs to a distribution list and stores an email address
 * along with a sort order for display purposes.
 */
class DistributionListMember extends Model
{
    /** @use HasFactory<\Database\Factories\DistributionListMemberFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'distribution_list_id',
        'email',
        'sort_order',
    ];

    /**
     * Get the distribution list that this member belongs to.
     */
    public function distributionList(): BelongsTo
    {
        return $this->belongsTo(DistributionList::class);
    }
}
