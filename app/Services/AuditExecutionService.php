<?php

namespace App\Services;

use App\DTOs\ComparisonResult;
use App\Enums\AuditScopeType;
use App\Enums\AuditStatus;
use App\Enums\AuditType;
use App\Enums\DeviceVerificationStatus;
use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Enums\FindingStatus;
use App\Enums\VerificationStatus;
use App\Events\AuditExecution\ConnectionLocked;
use App\Events\AuditExecution\ConnectionUnlocked;
use App\Events\AuditExecution\DeviceLocked;
use App\Events\AuditExecution\DeviceUnlocked;
use App\Events\AuditExecution\DeviceVerificationCompleted;
use App\Events\AuditExecution\VerificationCompleted;
use App\Models\Audit;
use App\Models\AuditConnectionVerification;
use App\Models\AuditDeviceVerification;
use App\Models\AuditRackVerification;
use App\Models\Device;
use App\Models\Discrepancy;
use App\Models\Finding;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing audit execution workflow.
 *
 * Handles the verification process for connection and inventory audits, including
 * pre-populating verification items from comparison results, managing
 * locks for multi-operator support, and tracking audit progress.
 */
class AuditExecutionService
{
    public function __construct(
        protected ConnectionComparisonService $comparisonService,
    ) {}

    /**
     * Prepare verification items for an audit from comparison results.
     *
     * Generates AuditConnectionVerification records based on the audit's scope
     * using the ConnectionComparisonService.
     */
    public function prepareVerificationItems(Audit $audit): void
    {
        // Skip if verification items already exist
        if ($audit->verifications()->exists()) {
            return;
        }

        // Get comparison results based on audit scope
        $comparisonResults = $this->getComparisonResults($audit);

        // Create verification records for each comparison result
        DB::transaction(function () use ($audit, $comparisonResults) {
            foreach ($comparisonResults as $result) {
                AuditConnectionVerification::create([
                    'audit_id' => $audit->id,
                    'expected_connection_id' => $result->expectedConnection?->id,
                    'connection_id' => $result->actualConnection?->id,
                    'comparison_status' => $result->discrepancyType,
                    'verification_status' => VerificationStatus::Pending,
                    'discrepancy_type' => null,
                    'notes' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                    'locked_by' => null,
                    'locked_at' => null,
                ]);
            }
        });
    }

    /**
     * Get comparison results based on audit scope type.
     *
     * @return \App\DTOs\ComparisonResultCollection
     */
    protected function getComparisonResults(Audit $audit)
    {
        // If audit has an implementation file, use file-based comparison
        if ($audit->implementation_file_id && $audit->implementationFile) {
            return $this->comparisonService->compareForImplementationFile($audit->implementationFile);
        }

        // Otherwise, use datacenter-level comparison
        return $this->comparisonService->compareForDatacenter($audit->datacenter);
    }

    /**
     * Import discrepancies as verification items for an audit.
     *
     * Creates AuditConnectionVerification records from selected discrepancies,
     * marks discrepancies as InAudit, and links them to the audit.
     *
     * @param  array<int>  $discrepancyIds
     * @return Collection<int, AuditConnectionVerification>
     */
    public function importDiscrepanciesAsVerificationItems(Audit $audit, array $discrepancyIds): Collection
    {
        $verifications = collect();

        // Get only discrepancies that can be imported (Open or Acknowledged status)
        $discrepancies = Discrepancy::whereIn('id', $discrepancyIds)
            ->whereIn('status', [DiscrepancyStatus::Open, DiscrepancyStatus::Acknowledged])
            ->with(['sourcePort.device', 'destPort.device', 'expectedConnection', 'connection'])
            ->get();

        if ($discrepancies->isEmpty()) {
            return $verifications;
        }

        DB::transaction(function () use ($audit, $discrepancies, &$verifications) {
            foreach ($discrepancies as $discrepancy) {
                // Create verification record from discrepancy data
                $verification = AuditConnectionVerification::create([
                    'audit_id' => $audit->id,
                    'expected_connection_id' => $discrepancy->expected_connection_id,
                    'connection_id' => $discrepancy->connection_id,
                    'comparison_status' => $discrepancy->discrepancy_type,
                    'verification_status' => VerificationStatus::Pending,
                    'discrepancy_type' => null,
                    'notes' => $discrepancy->description,
                    'verified_by' => null,
                    'verified_at' => null,
                    'locked_by' => null,
                    'locked_at' => null,
                ]);

                // Mark discrepancy as InAudit and link to this audit
                $discrepancy->update([
                    'status' => DiscrepancyStatus::InAudit,
                    'audit_id' => $audit->id,
                ]);

                $verifications->push($verification);
            }
        });

        return $verifications;
    }

    /**
     * Get verification items for an audit with optional filtering and sorting.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getVerificationItems(Audit $audit, array $filters = []): LengthAwarePaginator
    {
        $query = $audit->verifications()
            ->with([
                'expectedConnection.sourcePort.device',
                'expectedConnection.destPort.device',
                'expectedConnection.sourceDevice',
                'expectedConnection.destDevice',
                'connection.sourcePort.device',
                'connection.destinationPort.device',
                'verifiedBy',
                'lockedBy',
            ]);

        // Filter by comparison status (discrepancy type)
        if (! empty($filters['comparison_status'])) {
            $statuses = is_array($filters['comparison_status'])
                ? $filters['comparison_status']
                : [$filters['comparison_status']];

            $query->whereIn('comparison_status', $statuses);
        }

        // Filter by verification status
        if (! empty($filters['verification_status'])) {
            $statuses = is_array($filters['verification_status'])
                ? $filters['verification_status']
                : [$filters['verification_status']];

            $query->whereIn('verification_status', $statuses);
        }

        // Search by device name or port label
        if (! empty($filters['search'])) {
            $searchTerm = '%'.$filters['search'].'%';

            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('expectedConnection.sourceDevice', function ($dq) use ($searchTerm) {
                    $dq->where('name', 'like', $searchTerm);
                })
                    ->orWhereHas('expectedConnection.destDevice', function ($dq) use ($searchTerm) {
                        $dq->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('expectedConnection.sourcePort', function ($pq) use ($searchTerm) {
                        $pq->where('label', 'like', $searchTerm);
                    })
                    ->orWhereHas('expectedConnection.destPort', function ($pq) use ($searchTerm) {
                        $pq->where('label', 'like', $searchTerm);
                    })
                    ->orWhereHas('connection.sourcePort.device', function ($dq) use ($searchTerm) {
                        $dq->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('connection.destinationPort.device', function ($dq) use ($searchTerm) {
                        $dq->where('name', 'like', $searchTerm);
                    });
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        match ($sortBy) {
            'row_number' => $query->orderByRaw(
                '(SELECT row_number FROM expected_connections WHERE id = audit_connection_verifications.expected_connection_id) '.$sortOrder
            ),
            'comparison_status' => $query->orderBy('comparison_status', $sortOrder),
            'verification_status' => $query->orderBy('verification_status', $sortOrder),
            default => $query->orderBy('id', $sortOrder),
        };

        $perPage = $filters['per_page'] ?? 25;

        return $query->paginate($perPage);
    }

    /**
     * Lock a connection for verification by a user.
     *
     * Returns false if already locked by another user.
     */
    public function lockConnection(AuditConnectionVerification $verification, User $user): bool
    {
        // Release expired locks first
        if ($verification->isLocked() && ! $verification->isLockedBy($user)) {
            // Check if the lock is expired
            if (! $verification->isLocked()) {
                $verification->unlock();
            } else {
                return false;
            }
        }

        $locked = $verification->lockFor($user);

        if ($locked) {
            // Broadcast the lock event
            event(new ConnectionLocked($verification, $verification->audit, $user));
        }

        return $locked;
    }

    /**
     * Unlock a connection verification.
     */
    public function unlockConnection(AuditConnectionVerification $verification): void
    {
        $audit = $verification->audit;
        $verification->unlock();

        // Broadcast the unlock event
        event(new ConnectionUnlocked($verification, $audit));
    }

    /**
     * Release all expired locks for an audit.
     *
     * @return int Number of locks released
     */
    public function releaseExpiredLocks(Audit $audit): int
    {
        // Get the verification IDs before updating to broadcast events
        $expiredVerifications = $audit->verifications()
            ->expiredLocks()
            ->get();

        $count = $audit->verifications()
            ->expiredLocks()
            ->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);

        // Broadcast unlock events for each released lock
        foreach ($expiredVerifications as $verification) {
            $verification->refresh();
            event(new ConnectionUnlocked($verification, $audit));
        }

        return $count;
    }

    /**
     * Mark a verification as verified.
     */
    public function markVerified(AuditConnectionVerification $verification, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($verification, $user, $notes) {
            $verification->markVerified($user, $notes);

            $this->updateAuditStatusAfterVerification($verification->audit);
        });

        // Broadcast the verification completed event
        $verification->refresh();
        event(new VerificationCompleted($verification, $verification->audit, $user));
    }

    /**
     * Mark a verification as discrepant and auto-create a Finding.
     */
    public function markDiscrepant(
        AuditConnectionVerification $verification,
        User $user,
        DiscrepancyType $type,
        string $notes,
    ): void {
        DB::transaction(function () use ($verification, $user, $type, $notes) {
            $verification->markDiscrepant($user, $type, $notes);

            // Auto-create Finding and link to discrepancy if applicable
            $finding = $this->createFinding($verification, $type, $notes);

            // Link discrepancy to the finding if this verification came from a discrepancy
            $this->linkDiscrepancyToFinding($verification, $finding);

            $this->updateAuditStatusAfterVerification($verification->audit);
        });

        // Broadcast the verification completed event
        $verification->refresh();
        event(new VerificationCompleted($verification, $verification->audit, $user));
    }

    /**
     * Create a Finding record for a discrepant verification.
     */
    protected function createFinding(
        AuditConnectionVerification $verification,
        DiscrepancyType $type,
        string $notes,
    ): Finding {
        $title = $this->generateFindingTitle($verification, $type);

        return Finding::create([
            'audit_id' => $verification->audit_id,
            'audit_connection_verification_id' => $verification->id,
            'discrepancy_type' => $type,
            'title' => $title,
            'description' => $notes,
            'status' => FindingStatus::Open,
        ]);
    }

    /**
     * Link a discrepancy to a finding if the verification was created from a discrepancy import.
     */
    protected function linkDiscrepancyToFinding(AuditConnectionVerification $verification, Finding $finding): void
    {
        // Build a query to find a discrepancy linked to this audit that matches this verification
        $query = Discrepancy::where('audit_id', $verification->audit_id)
            ->where('status', DiscrepancyStatus::InAudit)
            ->whereNull('finding_id');

        // Match by expected connection or actual connection
        if ($verification->expected_connection_id !== null && $verification->connection_id !== null) {
            $query->where(function ($q) use ($verification) {
                $q->where('expected_connection_id', $verification->expected_connection_id)
                    ->orWhere('connection_id', $verification->connection_id);
            });
        } elseif ($verification->expected_connection_id !== null) {
            $query->where('expected_connection_id', $verification->expected_connection_id);
        } elseif ($verification->connection_id !== null) {
            $query->where('connection_id', $verification->connection_id);
        } else {
            return;
        }

        $discrepancy = $query->first();

        if ($discrepancy) {
            $discrepancy->update(['finding_id' => $finding->id]);
        }
    }

    /**
     * Generate a descriptive title for a finding.
     */
    protected function generateFindingTitle(AuditConnectionVerification $verification, DiscrepancyType $type): string
    {
        $sourceDevice = $verification->expectedConnection?->sourceDevice?->name
            ?? $verification->connection?->sourcePort?->device?->name
            ?? 'Unknown';

        $destDevice = $verification->expectedConnection?->destDevice?->name
            ?? $verification->connection?->destinationPort?->device?->name
            ?? 'Unknown';

        return match ($type) {
            DiscrepancyType::Missing => "Missing connection: {$sourceDevice} to {$destDevice}",
            DiscrepancyType::Unexpected => "Unexpected connection: {$sourceDevice} to {$destDevice}",
            DiscrepancyType::Mismatched => "Mismatched connection: {$sourceDevice} to {$destDevice}",
            DiscrepancyType::Conflicting => "Conflicting connection: {$sourceDevice} to {$destDevice}",
            DiscrepancyType::Matched => "Connection discrepancy: {$sourceDevice} to {$destDevice}",
            DiscrepancyType::ConfigurationMismatch => "Configuration mismatch: {$sourceDevice} to {$destDevice}",
        };
    }

    /**
     * Update audit status after a verification is completed.
     */
    protected function updateAuditStatusAfterVerification(Audit $audit): void
    {
        $audit->refresh();

        // If audit is still pending and this is the first verification, transition to InProgress
        if ($audit->status === AuditStatus::Pending) {
            $audit->update(['status' => AuditStatus::InProgress]);

            return;
        }

        // Check if all verifications are complete (verified or discrepant)
        if ($audit->status === AuditStatus::InProgress) {
            $pendingCount = $audit->pendingVerifications();

            if ($pendingCount === 0 && $audit->totalVerifications() > 0) {
                $audit->update(['status' => AuditStatus::Completed]);
            }
        }
    }

    /**
     * Bulk verify multiple connections.
     *
     * Only verifies matched connections and skips locked items.
     *
     * @param  array<int>  $verificationIds
     * @return array{verified: array<int>, skipped_locked: array<int>, skipped_not_matched: array<int>}
     */
    public function bulkVerify(array $verificationIds, User $user): array
    {
        $results = [
            'verified' => [],
            'skipped_locked' => [],
            'skipped_not_matched' => [],
        ];

        $verifications = AuditConnectionVerification::whereIn('id', $verificationIds)
            ->where('verification_status', VerificationStatus::Pending)
            ->with('audit')
            ->get();

        $verifiedItems = [];

        DB::transaction(function () use ($verifications, $user, &$results, &$verifiedItems) {
            foreach ($verifications as $verification) {
                // Skip if not matched
                if ($verification->comparison_status !== DiscrepancyType::Matched) {
                    $results['skipped_not_matched'][] = $verification->id;

                    continue;
                }

                // Skip if locked by another user
                if ($verification->isLocked() && ! $verification->isLockedBy($user)) {
                    $results['skipped_locked'][] = $verification->id;

                    continue;
                }

                $verification->markVerified($user);
                $results['verified'][] = $verification->id;
                $verifiedItems[] = $verification;
            }

            // Update audit status if needed
            if (! empty($results['verified'])) {
                $firstVerification = $verifications->first();
                if ($firstVerification) {
                    $this->updateAuditStatusAfterVerification($firstVerification->audit);
                }
            }
        });

        // Broadcast verification completed events for each verified item
        foreach ($verifiedItems as $verification) {
            $verification->refresh();
            event(new VerificationCompleted($verification, $verification->audit, $user));
        }

        return $results;
    }

    /**
     * Get progress statistics for an audit.
     *
     * @return array{total: int, verified: int, discrepant: int, pending: int, completed: int, progress_percentage: float}
     */
    public function getProgressStats(Audit $audit): array
    {
        $total = $audit->totalVerifications();
        $verified = $audit->verifications()
            ->where('verification_status', VerificationStatus::Verified)
            ->count();
        $discrepant = $audit->verifications()
            ->where('verification_status', VerificationStatus::Discrepant)
            ->count();
        $pending = $audit->pendingVerifications();
        $completed = $verified + $discrepant;

        $progressPercentage = $total > 0
            ? round(($completed / $total) * 100, 2)
            : 0;

        return [
            'total' => $total,
            'verified' => $verified,
            'discrepant' => $discrepant,
            'pending' => $pending,
            'completed' => $completed,
            'progress_percentage' => $progressPercentage,
        ];
    }

    // ============================================================
    // INVENTORY AUDIT METHODS
    // ============================================================

    /**
     * Prepare device verification items for an inventory audit.
     *
     * Queries devices based on audit scope (datacenter, room, or specific racks/devices)
     * and creates verification records. Also identifies and creates records for empty racks.
     */
    public function prepareDeviceVerificationItems(Audit $audit): void
    {
        // Skip if verification items already exist
        if ($audit->deviceVerifications()->exists()) {
            return;
        }

        DB::transaction(function () use ($audit) {
            // Get devices based on audit scope
            $devices = $this->getDevicesInScope($audit);

            // Create verification records for each device
            foreach ($devices as $device) {
                AuditDeviceVerification::create([
                    'audit_id' => $audit->id,
                    'device_id' => $device->id,
                    'rack_id' => $device->rack_id,
                    'verification_status' => DeviceVerificationStatus::Pending,
                    'notes' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                    'locked_by' => null,
                    'locked_at' => null,
                ]);
            }

            // Get empty racks and create rack verification records
            $emptyRacks = $this->getEmptyRacksInScope($audit);

            foreach ($emptyRacks as $rack) {
                AuditRackVerification::create([
                    'audit_id' => $audit->id,
                    'rack_id' => $rack->id,
                    'verified' => false,
                    'notes' => null,
                    'verified_by' => null,
                    'verified_at' => null,
                ]);
            }
        });
    }

    /**
     * Get all devices within the audit scope.
     *
     * @return Collection<int, Device>
     */
    protected function getDevicesInScope(Audit $audit): Collection
    {
        return match ($audit->scope_type) {
            AuditScopeType::Datacenter => $this->getDevicesForDatacenter($audit),
            AuditScopeType::Room => $this->getDevicesForRoom($audit),
            AuditScopeType::Racks => $this->getDevicesForRacksScope($audit),
        };
    }

    /**
     * Get all devices in a datacenter (via rooms -> rows -> racks -> devices).
     *
     * @return Collection<int, Device>
     */
    protected function getDevicesForDatacenter(Audit $audit): Collection
    {
        return Device::whereHas('rack.row.room', function (Builder $query) use ($audit) {
            $query->where('datacenter_id', $audit->datacenter_id);
        })->get();
    }

    /**
     * Get all devices in a room (via rows -> racks -> devices).
     *
     * @return Collection<int, Device>
     */
    protected function getDevicesForRoom(Audit $audit): Collection
    {
        return Device::whereHas('rack.row', function (Builder $query) use ($audit) {
            $query->where('room_id', $audit->room_id);
        })->get();
    }

    /**
     * Get devices for racks/devices scope using audit pivot tables.
     *
     * @return Collection<int, Device>
     */
    protected function getDevicesForRacksScope(Audit $audit): Collection
    {
        // Get devices explicitly selected via audit_devices pivot
        $explicitDeviceIds = $audit->devices()->pluck('devices.id');

        // Get devices from racks selected via audit_racks pivot
        $rackIds = $audit->racks()->pluck('racks.id');
        $devicesFromRacks = Device::whereIn('rack_id', $rackIds)->get();

        // Combine both sets
        if ($explicitDeviceIds->isNotEmpty()) {
            $explicitDevices = Device::whereIn('id', $explicitDeviceIds)->get();

            return $devicesFromRacks->merge($explicitDevices)->unique('id');
        }

        return $devicesFromRacks;
    }

    /**
     * Get all empty racks within the audit scope.
     *
     * @return Collection<int, Rack>
     */
    protected function getEmptyRacksInScope(Audit $audit): Collection
    {
        return match ($audit->scope_type) {
            AuditScopeType::Datacenter => $this->getEmptyRacksForDatacenter($audit),
            AuditScopeType::Room => $this->getEmptyRacksForRoom($audit),
            AuditScopeType::Racks => $this->getEmptyRacksForRacksScope($audit),
        };
    }

    /**
     * Get empty racks in a datacenter.
     *
     * @return Collection<int, Rack>
     */
    protected function getEmptyRacksForDatacenter(Audit $audit): Collection
    {
        return Rack::whereHas('row.room', function (Builder $query) use ($audit) {
            $query->where('datacenter_id', $audit->datacenter_id);
        })
            ->whereDoesntHave('devices')
            ->get();
    }

    /**
     * Get empty racks in a room.
     *
     * @return Collection<int, Rack>
     */
    protected function getEmptyRacksForRoom(Audit $audit): Collection
    {
        return Rack::whereHas('row', function (Builder $query) use ($audit) {
            $query->where('room_id', $audit->room_id);
        })
            ->whereDoesntHave('devices')
            ->get();
    }

    /**
     * Get empty racks for racks scope using audit pivot tables.
     *
     * @return Collection<int, Rack>
     */
    protected function getEmptyRacksForRacksScope(Audit $audit): Collection
    {
        $rackIds = $audit->racks()->pluck('racks.id');

        return Rack::whereIn('id', $rackIds)
            ->whereDoesntHave('devices')
            ->get();
    }

    /**
     * Get device verification items for an audit with optional filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getDeviceVerificationItems(Audit $audit, array $filters = []): LengthAwarePaginator
    {
        $query = $audit->deviceVerifications()
            ->with([
                'device',
                'rack.row.room',
                'verifiedBy',
                'lockedBy',
            ]);

        // Filter by specific device ID (for QR scan lookup)
        if (! empty($filters['device_id'])) {
            $query->where('device_id', $filters['device_id']);
        }

        // Filter by room
        if (! empty($filters['room_id'])) {
            $query->whereHas('rack.row', function (Builder $q) use ($filters) {
                $q->where('room_id', $filters['room_id']);
            });
        }

        // Filter by rack
        if (! empty($filters['rack_id'])) {
            $query->where('rack_id', $filters['rack_id']);
        }

        // Filter by verification status
        if (! empty($filters['verification_status'])) {
            $statuses = is_array($filters['verification_status'])
                ? $filters['verification_status']
                : [$filters['verification_status']];

            $query->whereIn('verification_status', $statuses);
        }

        // Search by device name, asset tag, or serial number
        if (! empty($filters['search'])) {
            $searchTerm = '%'.$filters['search'].'%';

            $query->whereHas('device', function (Builder $dq) use ($searchTerm) {
                $dq->where('name', 'like', $searchTerm)
                    ->orWhere('asset_tag', 'like', $searchTerm)
                    ->orWhere('serial_number', 'like', $searchTerm);
            });
        }

        // Apply sorting - group by rack by default
        $sortBy = $filters['sort_by'] ?? 'rack';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        match ($sortBy) {
            'rack' => $query->orderBy('rack_id', $sortOrder)->orderBy('id', $sortOrder),
            'verification_status' => $query->orderBy('verification_status', $sortOrder),
            'device_name' => $query->orderBy(
                Device::select('name')->whereColumn('devices.id', 'audit_device_verifications.device_id'),
                $sortOrder
            ),
            default => $query->orderBy('id', $sortOrder),
        };

        $perPage = $filters['per_page'] ?? 25;

        return $query->paginate($perPage);
    }

    /**
     * Mark a device verification as verified.
     */
    public function markDeviceVerified(AuditDeviceVerification $verification, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($verification, $user, $notes) {
            $verification->markVerified($user, $notes);

            $this->updateInventoryAuditStatusAfterVerification($verification->audit);
        });

        // Broadcast the verification completed event
        $verification->refresh();
        event(new DeviceVerificationCompleted($verification, $verification->audit, $user));
    }

    /**
     * Mark a device verification as not found and auto-create a Finding.
     */
    public function markDeviceNotFound(AuditDeviceVerification $verification, User $user, string $notes): void
    {
        DB::transaction(function () use ($verification, $user, $notes) {
            $verification->markNotFound($user, $notes);

            // Auto-create Finding
            $this->createDeviceFinding($verification, 'not_found', $notes);

            $this->updateInventoryAuditStatusAfterVerification($verification->audit);
        });

        // Broadcast the verification completed event
        $verification->refresh();
        event(new DeviceVerificationCompleted($verification, $verification->audit, $user));
    }

    /**
     * Mark a device verification as discrepant and auto-create a Finding.
     */
    public function markDeviceDiscrepant(AuditDeviceVerification $verification, User $user, string $notes): void
    {
        DB::transaction(function () use ($verification, $user, $notes) {
            $verification->markDiscrepant($user, $notes);

            // Auto-create Finding
            $this->createDeviceFinding($verification, 'discrepant', $notes);

            $this->updateInventoryAuditStatusAfterVerification($verification->audit);
        });

        // Broadcast the verification completed event
        $verification->refresh();
        event(new DeviceVerificationCompleted($verification, $verification->audit, $user));
    }

    /**
     * Create a Finding record for a device verification issue.
     */
    protected function createDeviceFinding(
        AuditDeviceVerification $verification,
        string $type,
        string $notes,
    ): Finding {
        $deviceName = $verification->device?->name ?? 'Unknown device';

        $title = match ($type) {
            'not_found' => "Device not found: {$deviceName}",
            'discrepant' => "Device discrepancy: {$deviceName}",
            default => "Device issue: {$deviceName}",
        };

        return Finding::create([
            'audit_id' => $verification->audit_id,
            'audit_device_verification_id' => $verification->id,
            'discrepancy_type' => null, // Device verifications don't use DiscrepancyType enum
            'title' => $title,
            'description' => $notes,
            'status' => FindingStatus::Open,
        ]);
    }

    /**
     * Mark an empty rack as verified.
     */
    public function markEmptyRackVerified(AuditRackVerification $rackVerification, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($rackVerification, $user, $notes) {
            $rackVerification->markVerified($user, $notes);

            $this->updateInventoryAuditStatusAfterVerification($rackVerification->audit);
        });
    }

    /**
     * Lock a device verification for a user.
     *
     * Returns false if already locked by another user.
     */
    public function lockDevice(AuditDeviceVerification $verification, User $user): bool
    {
        // Check if already locked by another user
        if ($verification->isLocked() && ! $verification->isLockedBy($user)) {
            return false;
        }

        $locked = $verification->lockFor($user);

        if ($locked) {
            // Broadcast the lock event
            event(new DeviceLocked($verification, $verification->audit, $user));
        }

        return $locked;
    }

    /**
     * Unlock a device verification.
     */
    public function unlockDevice(AuditDeviceVerification $verification): void
    {
        $audit = $verification->audit;
        $verification->unlock();

        // Broadcast the unlock event
        event(new DeviceUnlocked($verification, $audit));
    }

    /**
     * Release all expired device locks for an audit.
     *
     * @return int Number of locks released
     */
    public function releaseExpiredDeviceLocks(Audit $audit): int
    {
        // Get the verification IDs before updating to broadcast events
        $expiredVerifications = $audit->deviceVerifications()
            ->expiredLocks()
            ->get();

        $count = $audit->deviceVerifications()
            ->expiredLocks()
            ->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);

        // Broadcast unlock events for each released lock
        foreach ($expiredVerifications as $verification) {
            $verification->refresh();
            event(new DeviceUnlocked($verification, $audit));
        }

        return $count;
    }

    /**
     * Bulk verify multiple device verifications.
     *
     * Verifies pending devices and skips locked items.
     *
     * @param  array<int>  $verificationIds
     * @return array{verified: array<int>, skipped_locked: array<int>}
     */
    public function bulkVerifyDevices(array $verificationIds, User $user): array
    {
        $results = [
            'verified' => [],
            'skipped_locked' => [],
        ];

        $verifications = AuditDeviceVerification::whereIn('id', $verificationIds)
            ->where('verification_status', DeviceVerificationStatus::Pending)
            ->with('audit')
            ->get();

        $verifiedItems = [];

        DB::transaction(function () use ($verifications, $user, &$results, &$verifiedItems) {
            foreach ($verifications as $verification) {
                // Skip if locked by another user
                if ($verification->isLocked() && ! $verification->isLockedBy($user)) {
                    $results['skipped_locked'][] = $verification->id;

                    continue;
                }

                $verification->markVerified($user);
                $results['verified'][] = $verification->id;
                $verifiedItems[] = $verification;
            }

            // Update audit status if needed
            if (! empty($results['verified'])) {
                $firstVerification = $verifications->first();
                if ($firstVerification) {
                    $this->updateInventoryAuditStatusAfterVerification($firstVerification->audit);
                }
            }
        });

        // Broadcast verification completed events for each verified item
        foreach ($verifiedItems as $verification) {
            $verification->refresh();
            event(new DeviceVerificationCompleted($verification, $verification->audit, $user));
        }

        return $results;
    }

    /**
     * Get progress statistics for an inventory audit.
     *
     * @return array{
     *     total: int,
     *     total_devices: int,
     *     verified: int,
     *     not_found: int,
     *     discrepant: int,
     *     pending_devices: int,
     *     empty_racks_total: int,
     *     empty_racks_verified: int,
     *     empty_racks_pending: int,
     *     completed: int,
     *     pending: int,
     *     progress_percentage: float
     * }
     */
    public function getInventoryProgressStats(Audit $audit): array
    {
        // Device verification stats
        $totalDevices = $audit->deviceVerifications()->count();
        $verified = $audit->deviceVerifications()
            ->where('verification_status', DeviceVerificationStatus::Verified)
            ->count();
        $notFound = $audit->deviceVerifications()
            ->where('verification_status', DeviceVerificationStatus::NotFound)
            ->count();
        $discrepant = $audit->deviceVerifications()
            ->where('verification_status', DeviceVerificationStatus::Discrepant)
            ->count();
        $pendingDevices = $audit->deviceVerifications()
            ->where('verification_status', DeviceVerificationStatus::Pending)
            ->count();

        // Empty rack stats
        $emptyRacksTotal = $audit->rackVerifications()->count();
        $emptyRacksVerified = $audit->rackVerifications()
            ->where('verified', true)
            ->count();
        $emptyRacksPending = $emptyRacksTotal - $emptyRacksVerified;

        // Combined stats
        $total = $totalDevices + $emptyRacksTotal;
        $completed = $verified + $notFound + $discrepant + $emptyRacksVerified;
        $pending = $pendingDevices + $emptyRacksPending;

        $progressPercentage = $total > 0
            ? round(($completed / $total) * 100, 2)
            : 0;

        return [
            'total' => $total,
            'total_devices' => $totalDevices,
            'verified' => $verified,
            'not_found' => $notFound,
            'discrepant' => $discrepant,
            'pending_devices' => $pendingDevices,
            'empty_racks_total' => $emptyRacksTotal,
            'empty_racks_verified' => $emptyRacksVerified,
            'empty_racks_pending' => $emptyRacksPending,
            'completed' => $completed,
            'pending' => $pending,
            'progress_percentage' => $progressPercentage,
        ];
    }

    /**
     * Update inventory audit status after a verification is completed.
     */
    protected function updateInventoryAuditStatusAfterVerification(Audit $audit): void
    {
        $audit->refresh();

        // If audit is still pending and this is the first verification, transition to InProgress
        if ($audit->status === AuditStatus::Pending) {
            $audit->update(['status' => AuditStatus::InProgress]);

            return;
        }

        // Check if all verifications are complete
        if ($audit->status === AuditStatus::InProgress) {
            $stats = $this->getInventoryProgressStats($audit);

            if ($stats['pending'] === 0 && $stats['total'] > 0) {
                $audit->update(['status' => AuditStatus::Completed]);
            }
        }
    }
}
