<?php

namespace App\Services;

use App\DTOs\ComparisonResult;
use App\DTOs\ComparisonResultCollection;
use App\Enums\DiscrepancyStatus;
use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Discrepancy;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for detecting and persisting connection discrepancies.
 *
 * Wraps the ConnectionComparisonService to provide discrepancy detection
 * with persistent storage. Supports detection at datacenter, room, and
 * implementation file scopes, with upsert logic to prevent duplicates.
 */
class DiscrepancyDetectionService
{
    /**
     * Create a new DiscrepancyDetectionService instance.
     */
    public function __construct(
        protected ConnectionComparisonService $comparisonService
    ) {}

    /**
     * Detect discrepancies for an entire datacenter.
     *
     * Compares all confirmed expected connections from approved implementation
     * files against all actual connections in the datacenter.
     *
     * @return Collection<int, Discrepancy>
     */
    public function detectForDatacenter(Datacenter $datacenter): Collection
    {
        $comparisonResults = $this->comparisonService->compareForDatacenter($datacenter);

        $discrepancies = $this->processComparisonResults(
            $comparisonResults,
            $datacenter->id,
            roomId: null,
            implementationFileId: null
        );

        // Mark resolved discrepancies for matched connections
        $this->markResolvedDiscrepancies($comparisonResults, $datacenter->id);

        return $discrepancies;
    }

    /**
     * Detect discrepancies for a specific room.
     *
     * Filters expected connections to those involving devices in the room
     * and compares against actual connections for those devices.
     *
     * @return Collection<int, Discrepancy>
     */
    public function detectForRoom(Room $room): Collection
    {
        $datacenter = $room->datacenter;

        // Get port IDs for devices in this room
        $roomPortIds = $this->getRoomPortIds($room);

        if ($roomPortIds->isEmpty()) {
            return collect();
        }

        // Get approved implementation files for the datacenter
        $approvedFileIds = $datacenter->implementationFiles()
            ->where('approval_status', 'approved')
            ->pluck('id');

        // Get expected connections involving room's ports
        $expectedConnections = ExpectedConnection::query()
            ->whereIn('implementation_file_id', $approvedFileIds)
            ->confirmed()
            ->where(function ($query) use ($roomPortIds) {
                $query->whereIn('source_port_id', $roomPortIds)
                    ->orWhereIn('dest_port_id', $roomPortIds);
            })
            ->with([
                'sourcePort.device.rack',
                'destPort.device.rack',
                'sourceDevice',
                'destDevice',
                'implementationFile',
            ])
            ->get();

        // Get actual connections involving room's ports
        $actualConnections = Connection::query()
            ->where(function ($query) use ($roomPortIds) {
                $query->whereIn('source_port_id', $roomPortIds)
                    ->orWhereIn('destination_port_id', $roomPortIds);
            })
            ->with([
                'sourcePort.device.rack',
                'destinationPort.device.rack',
            ])
            ->get();

        // Build comparison results manually
        $comparisonResults = $this->buildRoomComparisonResults($expectedConnections, $actualConnections, $roomPortIds);

        $discrepancies = $this->processComparisonResults(
            $comparisonResults,
            $datacenter->id,
            roomId: $room->id,
            implementationFileId: null
        );

        // Mark resolved discrepancies for matched connections
        $this->markResolvedDiscrepancies($comparisonResults, $datacenter->id, $room->id);

        return $discrepancies;
    }

    /**
     * Detect discrepancies for a specific implementation file.
     *
     * Compares all confirmed expected connections in the file against
     * actual connections and links discrepancies to the file.
     *
     * @return Collection<int, Discrepancy>
     */
    public function detectForImplementationFile(ImplementationFile $file): Collection
    {
        $comparisonResults = $this->comparisonService->compareForImplementationFile($file);

        $discrepancies = $this->processComparisonResults(
            $comparisonResults,
            $file->datacenter_id,
            roomId: null,
            implementationFileId: $file->id
        );

        // Mark resolved discrepancies for matched connections
        $this->markResolvedDiscrepancies($comparisonResults, $file->datacenter_id, null, $file->id);

        return $discrepancies;
    }

    /**
     * Run incremental detection based on last run timestamp.
     *
     * Only processes connections and expected connections modified
     * since the last run, optimized for large datacenters.
     *
     * @return Collection<int, Discrepancy>
     */
    public function incrementalDetection(Datacenter $datacenter, Carbon $lastRunAt): Collection
    {
        // Get approved implementation files
        $approvedFileIds = $datacenter->implementationFiles()
            ->where('approval_status', 'approved')
            ->pluck('id');

        // Get expected connections modified since last run
        $modifiedExpectedConnections = ExpectedConnection::query()
            ->whereIn('implementation_file_id', $approvedFileIds)
            ->confirmed()
            ->where('updated_at', '>', $lastRunAt)
            ->with([
                'sourcePort.device.rack',
                'destPort.device.rack',
                'sourceDevice',
                'destDevice',
                'implementationFile',
            ])
            ->get();

        // Get actual connections modified since last run
        $modifiedConnections = Connection::query()
            ->whereHas('sourcePort.device.rack.row.room', function ($query) use ($datacenter) {
                $query->where('datacenter_id', $datacenter->id);
            })
            ->where('updated_at', '>', $lastRunAt)
            ->with([
                'sourcePort.device.rack',
                'destinationPort.device.rack',
            ])
            ->get();

        // Get port IDs from modified records
        $portIds = collect();
        foreach ($modifiedExpectedConnections as $expected) {
            if ($expected->source_port_id) {
                $portIds->push($expected->source_port_id);
            }
            if ($expected->dest_port_id) {
                $portIds->push($expected->dest_port_id);
            }
        }
        foreach ($modifiedConnections as $connection) {
            $portIds->push($connection->source_port_id);
            $portIds->push($connection->destination_port_id);
        }
        $portIds = $portIds->unique();

        if ($portIds->isEmpty()) {
            return collect();
        }

        // Get all expected connections involving these ports
        $expectedConnections = ExpectedConnection::query()
            ->whereIn('implementation_file_id', $approvedFileIds)
            ->confirmed()
            ->where(function ($query) use ($portIds) {
                $query->whereIn('source_port_id', $portIds)
                    ->orWhereIn('dest_port_id', $portIds);
            })
            ->with([
                'sourcePort.device.rack',
                'destPort.device.rack',
                'sourceDevice',
                'destDevice',
                'implementationFile',
            ])
            ->get();

        // Get all actual connections involving these ports
        $actualConnections = Connection::query()
            ->where(function ($query) use ($portIds) {
                $query->whereIn('source_port_id', $portIds)
                    ->orWhereIn('destination_port_id', $portIds);
            })
            ->with([
                'sourcePort.device.rack',
                'destinationPort.device.rack',
            ])
            ->get();

        // Build comparison results
        $comparisonResults = $this->buildIncrementalComparisonResults(
            $expectedConnections,
            $actualConnections,
            $modifiedExpectedConnections,
            $modifiedConnections
        );

        $discrepancies = $this->processComparisonResults(
            $comparisonResults,
            $datacenter->id,
            roomId: null,
            implementationFileId: null
        );

        // Mark resolved discrepancies
        $this->markResolvedDiscrepancies($comparisonResults, $datacenter->id);

        return $discrepancies;
    }

    /**
     * Mark discrepancies as resolved when connections now match.
     *
     * Finds open discrepancies for matched connections and updates
     * their status to resolved.
     */
    public function markResolvedDiscrepancies(
        ComparisonResultCollection $results,
        int $datacenterId,
        ?int $roomId = null,
        ?int $implementationFileId = null
    ): void {
        // Get matched results
        $matchedResults = $results->filterByDiscrepancyType(DiscrepancyType::Matched);

        foreach ($matchedResults as $result) {
            if (! $result->sourcePort || ! $result->destPort) {
                continue;
            }

            // Find open discrepancies for this port pair
            $query = Discrepancy::query()
                ->where('datacenter_id', $datacenterId)
                ->where(function ($q) use ($result) {
                    // Match both directions since connections can be bidirectional
                    $q->where(function ($inner) use ($result) {
                        $inner->where('source_port_id', $result->sourcePort->id)
                            ->where('dest_port_id', $result->destPort->id);
                    })->orWhere(function ($inner) use ($result) {
                        $inner->where('source_port_id', $result->destPort->id)
                            ->where('dest_port_id', $result->sourcePort->id);
                    });
                })
                ->whereIn('status', [DiscrepancyStatus::Open, DiscrepancyStatus::Acknowledged]);

            if ($roomId !== null) {
                $query->where('room_id', $roomId);
            }

            if ($implementationFileId !== null) {
                $query->where('implementation_file_id', $implementationFileId);
            }

            $query->update([
                'status' => DiscrepancyStatus::Resolved,
                'resolved_at' => now(),
            ]);
        }
    }

    /**
     * Process comparison results and convert to discrepancy records.
     *
     * @return Collection<int, Discrepancy>
     */
    protected function processComparisonResults(
        ComparisonResultCollection $results,
        int $datacenterId,
        ?int $roomId,
        ?int $implementationFileId
    ): Collection {
        $discrepancies = collect();

        foreach ($results as $result) {
            // Skip matched results - they don't need discrepancies
            if ($result->discrepancyType === DiscrepancyType::Matched) {
                // Check for configuration mismatches even on matched connections
                $configMismatch = $this->detectConfigurationMismatch($result);
                if ($configMismatch !== null) {
                    $discrepancy = $this->upsertDiscrepancy(
                        $result,
                        DiscrepancyType::ConfigurationMismatch,
                        $datacenterId,
                        $roomId,
                        $implementationFileId,
                        $configMismatch
                    );
                    $discrepancies->push($discrepancy);
                }

                continue;
            }

            // Detect port type mismatches for mismatched results
            $mismatchDetails = null;
            if ($result->discrepancyType === DiscrepancyType::Mismatched) {
                $mismatchDetails = $this->detectPortTypeMismatch($result);
            }

            $discrepancy = $this->upsertDiscrepancy(
                $result,
                $result->discrepancyType,
                $datacenterId,
                $roomId,
                $implementationFileId,
                $mismatchDetails
            );

            $discrepancies->push($discrepancy);
        }

        return $discrepancies;
    }

    /**
     * Upsert a discrepancy record.
     *
     * Finds existing discrepancy by source_port + dest_port + type and updates it,
     * or creates a new one if not found.
     *
     * @param  array<string, mixed>|null  $mismatchDetails
     */
    protected function upsertDiscrepancy(
        ComparisonResult $result,
        DiscrepancyType $type,
        int $datacenterId,
        ?int $roomId,
        ?int $implementationFileId,
        ?array $mismatchDetails = null
    ): Discrepancy {
        $sourcePortId = $result->sourcePort?->id;
        $destPortId = $result->destPort?->id;

        // Find existing discrepancy
        $existing = Discrepancy::query()
            ->where('source_port_id', $sourcePortId)
            ->where('dest_port_id', $destPortId)
            ->where('discrepancy_type', $type)
            ->where('datacenter_id', $datacenterId)
            ->whereIn('status', [DiscrepancyStatus::Open, DiscrepancyStatus::Acknowledged])
            ->first();

        if ($existing) {
            // Update detected_at timestamp
            $existing->update([
                'detected_at' => now(),
                'mismatch_details' => $mismatchDetails ?? $existing->mismatch_details,
                'expected_config' => $this->buildExpectedConfig($result),
                'actual_config' => $this->buildActualConfig($result),
            ]);

            return $existing;
        }

        // Create new discrepancy
        return Discrepancy::create([
            'datacenter_id' => $datacenterId,
            'room_id' => $roomId,
            'implementation_file_id' => $implementationFileId ?? $result->expectedConnection?->implementation_file_id,
            'discrepancy_type' => $type,
            'status' => DiscrepancyStatus::Open,
            'source_port_id' => $sourcePortId,
            'dest_port_id' => $destPortId,
            'connection_id' => $result->actualConnection?->id,
            'expected_connection_id' => $result->expectedConnection?->id,
            'expected_config' => $this->buildExpectedConfig($result),
            'actual_config' => $this->buildActualConfig($result),
            'mismatch_details' => $mismatchDetails,
            'title' => $this->generateTitle($type, $result),
            'detected_at' => now(),
        ]);
    }

    /**
     * Detect configuration mismatches between expected and actual connections.
     *
     * Compares cable_type and cable_length between expected and actual.
     *
     * @return array<string, mixed>|null Mismatch details or null if no mismatch
     */
    protected function detectConfigurationMismatch(ComparisonResult $result): ?array
    {
        if (! $result->expectedConnection || ! $result->actualConnection) {
            return null;
        }

        $mismatches = [];

        // Check cable type
        $expectedCableType = $result->expectedConnection->cable_type;
        $actualCableType = $result->actualConnection->cable_type;

        if ($expectedCableType && $actualCableType && $expectedCableType !== $actualCableType) {
            $mismatches['cable_type'] = [
                'expected' => $expectedCableType->value,
                'actual' => $actualCableType->value,
            ];
        }

        // Check cable length
        $expectedLength = $result->expectedConnection->cable_length;
        $actualLength = $result->actualConnection->cable_length;

        if ($expectedLength !== null && $actualLength !== null && (float) $expectedLength !== (float) $actualLength) {
            $mismatches['cable_length'] = [
                'expected' => (float) $expectedLength,
                'actual' => (float) $actualLength,
            ];
        }

        return empty($mismatches) ? null : $mismatches;
    }

    /**
     * Detect port type mismatches between expected and actual connections.
     *
     * Compares source and destination port types.
     *
     * @return array<string, mixed>|null Mismatch details or null if no mismatch
     */
    protected function detectPortTypeMismatch(ComparisonResult $result): ?array
    {
        $mismatches = [];

        // Compare expected vs actual destination port types
        if ($result->expectedDestPort && $result->actualDestPort) {
            $expectedDestType = $result->expectedDestPort->type;
            $actualDestType = $result->actualDestPort->type;

            if ($expectedDestType && $actualDestType && $expectedDestType !== $actualDestType) {
                $mismatches['dest_port_type'] = [
                    'expected' => $expectedDestType->value,
                    'actual' => $actualDestType->value,
                ];
            }
        }

        return empty($mismatches) ? null : $mismatches;
    }

    /**
     * Build expected configuration from comparison result.
     *
     * @return array<string, mixed>|null
     */
    protected function buildExpectedConfig(ComparisonResult $result): ?array
    {
        if (! $result->expectedConnection) {
            return null;
        }

        return [
            'cable_type' => $result->expectedConnection->cable_type?->value,
            'cable_length' => $result->expectedConnection->cable_length,
            'source_port_type' => $result->sourcePort?->type?->value,
            'dest_port_type' => $result->expectedDestPort?->type?->value,
        ];
    }

    /**
     * Build actual configuration from comparison result.
     *
     * @return array<string, mixed>|null
     */
    protected function buildActualConfig(ComparisonResult $result): ?array
    {
        if (! $result->actualConnection) {
            return null;
        }

        return [
            'cable_type' => $result->actualConnection->cable_type?->value,
            'cable_length' => $result->actualConnection->cable_length,
            'source_port_type' => $result->actualConnection->sourcePort?->type?->value,
            'dest_port_type' => $result->actualConnection->destinationPort?->type?->value,
        ];
    }

    /**
     * Generate a title for the discrepancy based on type.
     */
    protected function generateTitle(DiscrepancyType $type, ComparisonResult $result): string
    {
        $sourceLabel = $result->sourcePort?->label ?? 'Unknown';
        $destLabel = $result->destPort?->label ?? 'Unknown';

        return match ($type) {
            DiscrepancyType::Missing => "Missing Connection: {$sourceLabel} -> {$destLabel}",
            DiscrepancyType::Unexpected => "Unexpected Connection: {$sourceLabel} -> {$destLabel}",
            DiscrepancyType::Mismatched => "Mismatched Connection: {$sourceLabel} -> {$destLabel}",
            DiscrepancyType::Conflicting => "Conflicting Connection: {$sourceLabel} -> {$destLabel}",
            DiscrepancyType::ConfigurationMismatch => "Configuration Mismatch: {$sourceLabel} -> {$destLabel}",
            default => "Discrepancy: {$sourceLabel} -> {$destLabel}",
        };
    }

    /**
     * Get port IDs for devices in a specific room.
     *
     * @return Collection<int, int>
     */
    protected function getRoomPortIds(Room $room): Collection
    {
        return Port::query()
            ->whereHas('device', function ($deviceQuery) use ($room) {
                $deviceQuery->whereHas('rack', function ($rackQuery) use ($room) {
                    $rackQuery->whereHas('row', function ($rowQuery) use ($room) {
                        $rowQuery->where('room_id', $room->id);
                    });
                });
            })
            ->pluck('id');
    }

    /**
     * Build comparison results for room-scoped detection.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExpectedConnection>  $expectedConnections
     * @param  \Illuminate\Database\Eloquent\Collection<int, Connection>  $actualConnections
     * @param  Collection<int, int>  $roomPortIds
     */
    protected function buildRoomComparisonResults(
        $expectedConnections,
        $actualConnections,
        Collection $roomPortIds
    ): ComparisonResultCollection {
        $collection = new ComparisonResultCollection;
        $processedActualIds = collect();

        // Index actual connections by ports
        $actualBySource = $actualConnections->keyBy('source_port_id');
        $actualByDest = $actualConnections->keyBy('destination_port_id');

        foreach ($expectedConnections as $expected) {
            if (! $expected->source_port_id || ! $expected->dest_port_id) {
                continue;
            }

            // Only process if at least one port is in the room
            if (! $roomPortIds->contains($expected->source_port_id) &&
                ! $roomPortIds->contains($expected->dest_port_id)) {
                continue;
            }

            // Find matching actual connection
            $actual = $this->findMatchingActual($actualConnections, $expected->source_port_id, $expected->dest_port_id);

            if ($actual) {
                $processedActualIds->push($actual->id);

                if ($actual->source_port_id === $expected->source_port_id &&
                    $actual->destination_port_id === $expected->dest_port_id) {
                    $collection->add(ComparisonResult::matched($expected, $actual));
                } elseif ($actual->source_port_id === $expected->dest_port_id &&
                    $actual->destination_port_id === $expected->source_port_id) {
                    // Bidirectional match
                    $collection->add(ComparisonResult::matched($expected, $actual));
                } else {
                    $collection->add(ComparisonResult::mismatched($expected, $actual));
                }
            } else {
                $collection->add(ComparisonResult::missing($expected));
            }
        }

        // Find unexpected actual connections
        foreach ($actualConnections as $actual) {
            if ($processedActualIds->contains($actual->id)) {
                continue;
            }

            if ($roomPortIds->contains($actual->source_port_id) ||
                $roomPortIds->contains($actual->destination_port_id)) {
                $collection->add(ComparisonResult::unexpected($actual));
            }
        }

        return $collection;
    }

    /**
     * Build comparison results for incremental detection.
     *
     * Only includes results for modified expected connections and actual connections.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExpectedConnection>  $expectedConnections
     * @param  \Illuminate\Database\Eloquent\Collection<int, Connection>  $actualConnections
     * @param  \Illuminate\Database\Eloquent\Collection<int, ExpectedConnection>  $modifiedExpected
     * @param  \Illuminate\Database\Eloquent\Collection<int, Connection>  $modifiedActual
     */
    protected function buildIncrementalComparisonResults(
        $expectedConnections,
        $actualConnections,
        $modifiedExpected,
        $modifiedActual
    ): ComparisonResultCollection {
        $collection = new ComparisonResultCollection;
        $modifiedExpectedIds = $modifiedExpected->pluck('id');
        $modifiedActualIds = $modifiedActual->pluck('id');

        // Process modified expected connections
        foreach ($expectedConnections as $expected) {
            if (! $modifiedExpectedIds->contains($expected->id)) {
                continue;
            }

            if (! $expected->source_port_id || ! $expected->dest_port_id) {
                continue;
            }

            $actual = $this->findMatchingActual($actualConnections, $expected->source_port_id, $expected->dest_port_id);

            if ($actual) {
                if ($this->isExactMatch($actual, $expected->source_port_id, $expected->dest_port_id)) {
                    $collection->add(ComparisonResult::matched($expected, $actual));
                } else {
                    $collection->add(ComparisonResult::mismatched($expected, $actual));
                }
            } else {
                $collection->add(ComparisonResult::missing($expected));
            }
        }

        // Process modified actual connections
        foreach ($actualConnections as $actual) {
            if (! $modifiedActualIds->contains($actual->id)) {
                continue;
            }

            // Check if this actual connection matches any expected
            $matchingExpected = $expectedConnections->first(function ($expected) use ($actual) {
                return $this->isExactMatch($actual, $expected->source_port_id, $expected->dest_port_id);
            });

            if (! $matchingExpected) {
                $collection->add(ComparisonResult::unexpected($actual));
            }
        }

        return $collection;
    }

    /**
     * Find a matching actual connection for given port IDs.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Connection>  $actualConnections
     */
    protected function findMatchingActual($actualConnections, int $sourcePortId, int $destPortId): ?Connection
    {
        // Direct match
        $direct = $actualConnections->first(function ($actual) use ($sourcePortId, $destPortId) {
            return $actual->source_port_id === $sourcePortId && $actual->destination_port_id === $destPortId;
        });

        if ($direct) {
            return $direct;
        }

        // Bidirectional match
        return $actualConnections->first(function ($actual) use ($sourcePortId, $destPortId) {
            return $actual->source_port_id === $destPortId && $actual->destination_port_id === $sourcePortId;
        });
    }

    /**
     * Check if actual connection is an exact match for given port IDs.
     */
    protected function isExactMatch(Connection $actual, int $sourcePortId, int $destPortId): bool
    {
        // Direct match
        if ($actual->source_port_id === $sourcePortId && $actual->destination_port_id === $destPortId) {
            return true;
        }

        // Bidirectional match
        if ($actual->source_port_id === $destPortId && $actual->destination_port_id === $sourcePortId) {
            return true;
        }

        return false;
    }
}
