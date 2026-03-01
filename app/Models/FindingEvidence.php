<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FindingEvidence model for storing evidence attached to findings.
 *
 * Evidence can be either file uploads (images, PDFs, documents) or
 * text notes providing additional context about a finding.
 */
class FindingEvidence extends Model
{
    /** @use HasFactory<\Database\Factories\FindingEvidenceFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'finding_evidence';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'finding_id',
        'type',
        'content',
        'file_path',
        'original_filename',
        'mime_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
        ];
    }

    /**
     * Get the finding this evidence belongs to.
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * Check if this evidence is a file.
     */
    public function isFile(): bool
    {
        return $this->type === EvidenceType::File;
    }

    /**
     * Check if this evidence is a text note.
     */
    public function isText(): bool
    {
        return $this->type === EvidenceType::Text;
    }
}
