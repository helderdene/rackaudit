<?php

namespace App\Models;

use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ImplementationFile model representing implementation specification documents.
 *
 * Stores file metadata for uploaded implementation documents (PDF, Excel, CSV,
 * Word, text) that serve as the authoritative source for expected connections
 * in datacenter audits.
 *
 * Supports version tracking via version_group_id and version_number fields,
 * allowing multiple versions of the same logical file to be preserved.
 *
 * Includes approval workflow with approval_status, approved_by, and approved_at
 * fields to ensure only reviewed files serve as authoritative sources.
 */
class ImplementationFile extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'datacenter_id',
        'file_name',
        'original_name',
        'description',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
        'version_group_id',
        'version_number',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version_number' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Mapping of MIME types to human-readable labels.
     */
    protected const MIME_TYPE_LABELS = [
        'application/pdf' => 'PDF Document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel Spreadsheet',
        'application/vnd.ms-excel' => 'Excel Spreadsheet',
        'text/csv' => 'CSV File',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
        'text/plain' => 'Text File',
    ];

    /**
     * Get the datacenter that owns this implementation file.
     */
    public function datacenter(): BelongsTo
    {
        return $this->belongsTo(Datacenter::class);
    }

    /**
     * Get the user who uploaded this file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who approved this file.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get all versions of this file (files sharing the same version_group_id).
     *
     * This relationship returns all files in the version chain, including the current file.
     * Results are ordered by version_number descending (newest first).
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ImplementationFile::class, 'version_group_id', 'version_group_id')
            ->orderByDesc('version_number');
    }

    /**
     * Get the latest version in the version group.
     *
     * Returns the file with the highest version_number in the same version group.
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(ImplementationFile::class, 'version_group_id', 'version_group_id')
            ->orderByDesc('version_number')
            ->limit(1);
    }

    /**
     * Get all expected connections parsed from this implementation file.
     *
     * Expected connections are the port-to-port mappings extracted from the file
     * that serve as the authoritative reference for audit comparisons.
     */
    public function expectedConnections(): HasMany
    {
        return $this->hasMany(ExpectedConnection::class);
    }

    /**
     * Check if this file is the latest version in its version group.
     */
    protected function isLatestVersion(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->version_group_id === null) {
                    return true;
                }

                $maxVersion = static::where('version_group_id', $this->version_group_id)
                    ->max('version_number');

                return $this->version_number === $maxVersion;
            }
        );
    }

    /**
     * Check if this file has multiple versions in its version group.
     */
    protected function hasMultipleVersions(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->version_group_id === null) {
                    return false;
                }

                return static::where('version_group_id', $this->version_group_id)->count() > 1;
            }
        );
    }

    /**
     * Get the formatted file size for display (e.g., "2.5 MB").
     *
     * Converts bytes to the most appropriate unit:
     * - Bytes (B) for < 1 KB
     * - Kilobytes (KB) for < 1 MB
     * - Megabytes (MB) for >= 1 MB
     */
    protected function formattedFileSize(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $bytes = $this->file_size;

                if ($bytes < 1024) {
                    return $bytes.' B';
                }

                if ($bytes < 1048576) {
                    $kb = $bytes / 1024;

                    return ($kb == floor($kb) ? (int) $kb : round($kb, 1)).' KB';
                }

                $mb = $bytes / 1048576;

                return ($mb == floor($mb) ? (int) $mb : round($mb, 1)).' MB';
            }
        );
    }

    /**
     * Get the human-readable file type label based on MIME type.
     *
     * Returns labels like "PDF Document", "Excel Spreadsheet", etc.
     */
    protected function fileTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::MIME_TYPE_LABELS[$this->mime_type] ?? 'Unknown File Type'
        );
    }

    /**
     * Check if this file is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending_approval';
    }

    /**
     * Check if this file has been approved.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }
}
