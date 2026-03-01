<?php

namespace App\Services;

use App\DTOs\ComparisonResult;
use App\DTOs\ComparisonResultCollection;
use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\DiscrepancyAcknowledgment;
use App\Models\ExpectedConnection;
use App\Models\ImplementationFile;
use App\Models\Port;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Service for comparing expected connections against actual connections.
 *
 * Provides methods to compare connections for a single implementation file
 * or aggregated across all approved implementation files in a datacenter.
 * Supports bidirectional matching and conflict detection.
 */
class ConnectionComparisonService
{
    /**
     * Compare connections for a specific implementation file.
     *
     * Returns comparison results for all confirmed expected connections
     * in the file, plus any unexpected actual connections involving
     * the same ports.
     */
    public function compareForImplementationFile(ImplementationFile $file): ComparisonResultCollection
    {
        // Get confirmed expected connections with eager loading
        $expectedConnections = $file->expectedConnections()
            ->confirmed()
            ->with([
                'sourcePort.device.rack',
                'destPort.device.rack',
                'sourceDevice',
                'destDevice',
            ])
            ->get();

        // Get all port IDs from expected connections
        $portIds = $this->extractPortIds($expectedConnections);

        // Get actual connections involving those ports with eager loading
        $actualConnections = $this->getActualConnectionsForPorts($portIds);

        // Get acknowledgments for these expected connections
        $acknowledgments = $this->getAcknowledgmentsForExpected($expectedConnections);

        // Build comparison results
        return $this->buildComparisonResults(
            $expectedConnections,
            $actualConnections,
            $acknowledgments,
            detectConflicts: false,
        );
    }

    /**
     * Compare connections for an entire datacenter.
     *
     * Aggregates confirmed expected connections from all approved
     * implementation files and compares against all actual connections.
     * Includes conflict detection for overlapping expectations.
     */
    public function compareForDatacenter(Datacenter $datacenter): ComparisonResultCollection
    {
        // Get all approved implementation files for the datacenter
        $approvedFiles = $datacenter->implementationFiles()
            ->where('approval_status', 'approved')
            ->pluck('id');

        // Get all confirmed expected connections from approved files
        $expectedConnections = ExpectedConnection::query()
            ->whereIn('implementation_file_id', $approvedFiles)
            ->confirmed()
            ->with([
                'sourcePort.device.rack',
                'destPort.device.rack',
                'sourceDevice',
                'destDevice',
                'implementationFile',
            ])
            ->get();

        // Detect conflicts (same source port, different destinations)
        $conflicts = $this->detectConflicts($expectedConnections);

        // Get all port IDs from expected connections
        $portIds = $this->extractPortIds($expectedConnections);

        // Get ports belonging to devices in this datacenter (for unexpected detection)
        $datacenterPortIds = $this->getDatacenterPortIds($datacenter);

        // Merge port IDs for actual connection query
        $allRelevantPortIds = $portIds->merge($datacenterPortIds)->unique();

        // Get actual connections involving those ports
        $actualConnections = $this->getActualConnectionsForPorts($allRelevantPortIds);

        // Get acknowledgments
        $acknowledgments = $this->getAcknowledgmentsForDatacenter($expectedConnections, $actualConnections);

        // Build comparison results with conflict detection
        return $this->buildComparisonResults(
            $expectedConnections,
            $actualConnections,
            $acknowledgments,
            detectConflicts: true,
            conflicts: $conflicts,
            datacenterPortIds: $datacenterPortIds,
        );
    }

    /**
     * Check if a bidirectional match exists for given port IDs.
     *
     * Looks for an actual connection where (A->B) matches the expected
     * ports or (B->A) matches (treating them as equivalent).
     */
    public function checkBidirectionalMatch(int $sourcePortId, int $destPortId): ?Connection
    {
        return Connection::query()
            ->where(function ($query) use ($sourcePortId, $destPortId) {
                // Direct match: source -> dest
                $query->where(function ($q) use ($sourcePortId, $destPortId) {
                    $q->where('source_port_id', $sourcePortId)
                        ->where('destination_port_id', $destPortId);
                });
                // Reverse match: dest -> source (bidirectional)
                $query->orWhere(function ($q) use ($sourcePortId, $destPortId) {
                    $q->where('source_port_id', $destPortId)
                        ->where('destination_port_id', $sourcePortId);
                });
            })
            ->with(['sourcePort.device', 'destinationPort.device'])
            ->first();
    }

    /**
     * Extract all port IDs from expected connections.
     *
     * @param  EloquentCollection<int, ExpectedConnection>  $expectedConnections
     * @return Collection<int, int>
     */
    protected function extractPortIds(EloquentCollection $expectedConnections): Collection
    {
        $portIds = collect();

        foreach ($expectedConnections as $expected) {
            if ($expected->source_port_id) {
                $portIds->push($expected->source_port_id);
            }
            if ($expected->dest_port_id) {
                $portIds->push($expected->dest_port_id);
            }
        }

        return $portIds->unique();
    }

    /**
     * Get actual connections involving the given port IDs.
     *
     * @param  Collection<int, int>  $portIds
     * @return EloquentCollection<int, Connection>
     */
    protected function getActualConnectionsForPorts(Collection $portIds): EloquentCollection
    {
        if ($portIds->isEmpty()) {
            return new EloquentCollection;
        }

        return Connection::query()
            ->where(function ($query) use ($portIds) {
                $query->whereIn('source_port_id', $portIds)
                    ->orWhereIn('destination_port_id', $portIds);
            })
            ->with([
                'sourcePort.device.rack',
                'destinationPort.device.rack',
            ])
            ->get();
    }

    /**
     * Get all port IDs for devices in the datacenter.
     *
     * Hierarchy: Datacenter -> Room -> Row -> Rack -> Device -> Port
     *
     * @return Collection<int, int>
     */
    protected function getDatacenterPortIds(Datacenter $datacenter): Collection
    {
        return Port::query()
            ->whereHas('device', function ($deviceQuery) use ($datacenter) {
                $deviceQuery->whereHas('rack', function ($rackQuery) use ($datacenter) {
                    $rackQuery->whereHas('row', function ($rowQuery) use ($datacenter) {
                        $rowQuery->whereHas('room', function ($roomQuery) use ($datacenter) {
                            $roomQuery->where('datacenter_id', $datacenter->id);
                        });
                    });
                });
            })
            ->pluck('id');
    }

    /**
     * Detect conflicts where same source port has different destinations across files.
     *
     * @param  EloquentCollection<int, ExpectedConnection>  $expectedConnections
     * @return array<int, array<int, ExpectedConnection>>  Keyed by source_port_id
     */
    protected function detectConflicts(EloquentCollection $expectedConnections): array
    {
        $bySourcePort = [];

        foreach ($expectedConnections as $expected) {
            if (! $expected->source_port_id) {
                continue;
            }

            $sourcePortId = $expected->source_port_id;

            if (! isset($bySourcePort[$sourcePortId])) {
                $bySourcePort[$sourcePortId] = [];
            }

            $bySourcePort[$sourcePortId][] = $expected;
        }

        // Filter to only conflicting entries (different dest_port_id values)
        $conflicts = [];
        foreach ($bySourcePort as $sourcePortId => $expectations) {
            if (count($expectations) <= 1) {
                continue;
            }

            // Check if destinations differ
            $destPorts = collect($expectations)->pluck('dest_port_id')->unique();

            if ($destPorts->count() > 1) {
                $conflicts[$sourcePortId] = $expectations;
            }
        }

        return $conflicts;
    }

    /**
     * Get acknowledgments for expected connections.
     *
     * @param  EloquentCollection<int, ExpectedConnection>  $expectedConnections
     * @return Collection<string, DiscrepancyAcknowledgment>
     */
    protected function getAcknowledgmentsForExpected(EloquentCollection $expectedConnections): Collection
    {
        $expectedIds = $expectedConnections->pluck('id');

        if ($expectedIds->isEmpty()) {
            return collect();
        }

        return DiscrepancyAcknowledgment::query()
            ->whereIn('expected_connection_id', $expectedIds)
            ->get()
            ->keyBy(function ($ack) {
                return $this->makeAcknowledgmentKey($ack->expected_connection_id, $ack->connection_id, $ack->discrepancy_type);
            });
    }

    /**
     * Get acknowledgments for datacenter comparison.
     *
     * @param  EloquentCollection<int, ExpectedConnection>  $expectedConnections
     * @param  EloquentCollection<int, Connection>  $actualConnections
     * @return Collection<string, DiscrepancyAcknowledgment>
     */
    protected function getAcknowledgmentsForDatacenter(
        EloquentCollection $expectedConnections,
        EloquentCollection $actualConnections,
    ): Collection {
        $expectedIds = $expectedConnections->pluck('id');
        $connectionIds = $actualConnections->pluck('id');

        if ($expectedIds->isEmpty() && $connectionIds->isEmpty()) {
            return collect();
        }

        return DiscrepancyAcknowledgment::query()
            ->where(function ($query) use ($expectedIds, $connectionIds) {
                if ($expectedIds->isNotEmpty()) {
                    $query->whereIn('expected_connection_id', $expectedIds);
                }
                if ($connectionIds->isNotEmpty()) {
                    $query->orWhereIn('connection_id', $connectionIds);
                }
            })
            ->get()
            ->keyBy(function ($ack) {
                return $this->makeAcknowledgmentKey($ack->expected_connection_id, $ack->connection_id, $ack->discrepancy_type);
            });
    }

    /**
     * Build comparison results from expected and actual connections.
     *
     * @param  EloquentCollection<int, ExpectedConnection>  $expectedConnections
     * @param  EloquentCollection<int, Connection>  $actualConnections
     * @param  Collection<string, DiscrepancyAcknowledgment>  $acknowledgments
     * @param  array<int, array<int, ExpectedConnection>>  $conflicts
     * @param  Collection<int, int>|null  $datacenterPortIds
     */
    protected function buildComparisonResults(
        EloquentCollection $expectedConnections,
        EloquentCollection $actualConnections,
        Collection $acknowledgments,
        bool $detectConflicts,
        array $conflicts = [],
        ?Collection $datacenterPortIds = null,
    ): ComparisonResultCollection {
        $collection = new ComparisonResultCollection;

        // Track processed actual connections to identify unexpected ones
        $processedActualIds = collect();

        // Index actual connections by port pairs for efficient lookup
        $actualByPorts = $this->indexActualConnectionsByPorts($actualConnections);

        // Process each expected connection
        foreach ($expectedConnections as $expected) {
            // Skip if ports are null (unmatched expected connection)
            if (! $expected->source_port_id || ! $expected->dest_port_id) {
                continue;
            }

            $sourcePortId = $expected->source_port_id;
            $destPortId = $expected->dest_port_id;

            // Check for conflicts first
            if ($detectConflicts && isset($conflicts[$sourcePortId])) {
                $conflictInfo = $this->buildConflictInfo($conflicts[$sourcePortId], $expected);
                $actual = $this->findMatchingActual($actualByPorts, $sourcePortId, $destPortId);

                if ($actual) {
                    $processedActualIds->push($actual->id);
                }

                $ack = $this->findAcknowledgment($acknowledgments, $expected->id, $actual?->id, DiscrepancyType::Conflicting);
                $collection->add(ComparisonResult::conflicting($expected, $actual, $conflictInfo, $ack));

                continue;
            }

            // Check for exact match (direct or bidirectional)
            $actual = $this->findMatchingActual($actualByPorts, $sourcePortId, $destPortId);

            if ($actual) {
                $processedActualIds->push($actual->id);

                // Check if it's an exact match or a mismatch
                if ($this->isExactMatch($actual, $sourcePortId, $destPortId)) {
                    $ack = $this->findAcknowledgment($acknowledgments, $expected->id, $actual->id, DiscrepancyType::Matched);
                    $collection->add(ComparisonResult::matched($expected, $actual, $ack));
                } else {
                    // Partial match - source matches but destination differs
                    $ack = $this->findAcknowledgment($acknowledgments, $expected->id, $actual->id, DiscrepancyType::Mismatched);
                    $collection->add(ComparisonResult::mismatched($expected, $actual, $ack));
                }

                continue;
            }

            // Check for partial match (source port has a connection but to different dest)
            $partialActual = $this->findPartialMatch($actualByPorts, $sourcePortId);

            if ($partialActual) {
                $processedActualIds->push($partialActual->id);
                $ack = $this->findAcknowledgment($acknowledgments, $expected->id, $partialActual->id, DiscrepancyType::Mismatched);
                $collection->add(ComparisonResult::mismatched($expected, $partialActual, $ack));

                continue;
            }

            // No actual connection found - missing
            $ack = $this->findAcknowledgment($acknowledgments, $expected->id, null, DiscrepancyType::Missing);
            $collection->add(ComparisonResult::missing($expected, $ack));
        }

        // Find unexpected actual connections
        $unexpectedActuals = $actualConnections->filter(function ($actual) use ($processedActualIds, $datacenterPortIds) {
            // Skip if already processed
            if ($processedActualIds->contains($actual->id)) {
                return false;
            }

            // If we have datacenter port IDs, only include if at least one port is in datacenter
            if ($datacenterPortIds !== null) {
                return $datacenterPortIds->contains($actual->source_port_id)
                    || $datacenterPortIds->contains($actual->destination_port_id);
            }

            return true;
        });

        foreach ($unexpectedActuals as $actual) {
            $ack = $this->findAcknowledgment($acknowledgments, null, $actual->id, DiscrepancyType::Unexpected);
            $collection->add(ComparisonResult::unexpected($actual, $ack));
        }

        return $collection;
    }

    /**
     * Index actual connections by source and destination port pairs.
     *
     * Creates a lookup structure for efficient matching:
     * - 'source_{id}' => array of connections with that source
     * - 'dest_{id}' => array of connections with that destination
     *
     * @param  EloquentCollection<int, Connection>  $actualConnections
     * @return array<string, array<int, Connection>>
     */
    protected function indexActualConnectionsByPorts(EloquentCollection $actualConnections): array
    {
        $index = [];

        foreach ($actualConnections as $actual) {
            $sourceKey = 'source_'.$actual->source_port_id;
            $destKey = 'dest_'.$actual->destination_port_id;

            if (! isset($index[$sourceKey])) {
                $index[$sourceKey] = [];
            }
            $index[$sourceKey][] = $actual;

            if (! isset($index[$destKey])) {
                $index[$destKey] = [];
            }
            $index[$destKey][] = $actual;
        }

        return $index;
    }

    /**
     * Find a matching actual connection (direct or bidirectional).
     *
     * @param  array<string, array<int, Connection>>  $actualByPorts
     */
    protected function findMatchingActual(array $actualByPorts, int $sourcePortId, int $destPortId): ?Connection
    {
        // Check for direct match (source -> dest)
        $sourceKey = 'source_'.$sourcePortId;
        if (isset($actualByPorts[$sourceKey])) {
            foreach ($actualByPorts[$sourceKey] as $actual) {
                if ($actual->destination_port_id === $destPortId) {
                    return $actual;
                }
            }
        }

        // Check for bidirectional match (dest -> source, treating A->B as B->A)
        $destKey = 'source_'.$destPortId;
        if (isset($actualByPorts[$destKey])) {
            foreach ($actualByPorts[$destKey] as $actual) {
                if ($actual->destination_port_id === $sourcePortId) {
                    return $actual;
                }
            }
        }

        return null;
    }

    /**
     * Check if actual connection is an exact match (considering bidirectional).
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

    /**
     * Find a partial match where source port has a connection but to different destination.
     *
     * @param  array<string, array<int, Connection>>  $actualByPorts
     */
    protected function findPartialMatch(array $actualByPorts, int $sourcePortId): ?Connection
    {
        // Check if source port has any connection
        $sourceKey = 'source_'.$sourcePortId;
        if (isset($actualByPorts[$sourceKey]) && ! empty($actualByPorts[$sourceKey])) {
            return $actualByPorts[$sourceKey][0];
        }

        // Also check if source port is the destination of any connection (bidirectional consideration)
        $destKey = 'dest_'.$sourcePortId;
        if (isset($actualByPorts[$destKey]) && ! empty($actualByPorts[$destKey])) {
            return $actualByPorts[$destKey][0];
        }

        return null;
    }

    /**
     * Build conflict info array for display.
     *
     * @param  array<int, ExpectedConnection>  $conflictingExpectations
     * @return array<string, mixed>
     */
    protected function buildConflictInfo(array $conflictingExpectations, ExpectedConnection $current): array
    {
        $otherExpectations = [];

        foreach ($conflictingExpectations as $exp) {
            if ($exp->id === $current->id) {
                continue;
            }

            $otherExpectations[] = [
                'expected_connection_id' => $exp->id,
                'dest_port_id' => $exp->dest_port_id,
                'dest_port_label' => $exp->destPort?->label,
                'dest_device_name' => $exp->destDevice?->name,
                'implementation_file_id' => $exp->implementation_file_id,
                'implementation_file_name' => $exp->implementationFile?->original_name,
            ];
        }

        return [
            'conflict_count' => count($conflictingExpectations),
            'other_expectations' => $otherExpectations,
        ];
    }

    /**
     * Make a unique key for acknowledgment lookup.
     */
    protected function makeAcknowledgmentKey(?int $expectedId, ?int $connectionId, DiscrepancyType $type): string
    {
        return ($expectedId ?? 'null').'_'.($connectionId ?? 'null').'_'.$type->value;
    }

    /**
     * Find an acknowledgment for the given IDs and type.
     *
     * @param  Collection<string, DiscrepancyAcknowledgment>  $acknowledgments
     */
    protected function findAcknowledgment(
        Collection $acknowledgments,
        ?int $expectedId,
        ?int $connectionId,
        DiscrepancyType $type,
    ): ?DiscrepancyAcknowledgment {
        $key = $this->makeAcknowledgmentKey($expectedId, $connectionId, $type);

        return $acknowledgments->get($key);
    }
}
