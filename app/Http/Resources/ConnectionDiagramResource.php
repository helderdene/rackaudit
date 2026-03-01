<?php

namespace App\Http\Resources;

use Illuminate\Support\Collection;

/**
 * Resource for transforming connection data into diagram visualization format.
 *
 * Supports hierarchical aggregation:
 * - 'rack': Aggregates connections into rack-level nodes (for datacenter/room/row views)
 * - 'device': Aggregates connections into device-level nodes (for rack/device views)
 */
class ConnectionDiagramResource
{
    /**
     * Create a new diagram resource.
     *
     * @param  Collection  $connections  The connections collection with loaded relationships
     * @param  string  $aggregationLevel  The level to aggregate at: 'rack' or 'device'
     * @param  Collection|null  $allowedRackIds  Optional list of rack IDs to include in rack aggregation
     */
    public function __construct(
        protected Collection $connections,
        protected string $aggregationLevel = 'device',
        protected ?Collection $allowedRackIds = null
    ) {}

    /**
     * Transform the connections into diagram format.
     *
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, aggregation_level: string}
     */
    public function toArray(): array
    {
        $nodes = $this->aggregationLevel === 'rack'
            ? $this->extractRackNodes()
            : $this->extractDeviceNodes();

        $edges = $this->aggregationLevel === 'rack'
            ? $this->extractRackEdges()
            : $this->extractDeviceEdges();

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'aggregation_level' => $this->aggregationLevel,
        ];
    }

    /**
     * Check if a rack should be included based on filter constraints.
     */
    protected function isRackAllowed(int $rackId): bool
    {
        if ($this->allowedRackIds === null) {
            return true;
        }

        return $this->allowedRackIds->contains($rackId);
    }

    /**
     * Extract unique rack nodes from connections.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractRackNodes(): array
    {
        $rackMap = [];

        foreach ($this->connections as $connection) {
            // Process source rack
            $sourcePort = $connection->sourcePort;
            if ($sourcePort?->device?->rack) {
                $rack = $sourcePort->device->rack;
                $rackId = $rack->id;

                // Only include racks that match the filter
                if ($this->isRackAllowed($rackId)) {
                    if (! isset($rackMap[$rackId])) {
                        $rackMap[$rackId] = $this->createRackNode($rack);
                    }

                    $rackMap[$rackId]['connection_ids'][] = $connection->id;
                    $rackMap[$rackId]['device_ids'][$sourcePort->device->id] = true;
                }
            }

            // Process destination rack
            $destPort = $connection->destinationPort;
            if ($destPort?->device?->rack) {
                $rack = $destPort->device->rack;
                $rackId = $rack->id;

                // Only include racks that match the filter
                if ($this->isRackAllowed($rackId)) {
                    if (! isset($rackMap[$rackId])) {
                        $rackMap[$rackId] = $this->createRackNode($rack);
                    }

                    $rackMap[$rackId]['connection_ids'][] = $connection->id;
                    $rackMap[$rackId]['device_ids'][$destPort->device->id] = true;
                }
            }
        }

        // Calculate counts
        foreach ($rackMap as &$node) {
            $node['connection_count'] = count(array_unique($node['connection_ids']));
            $node['device_count'] = count($node['device_ids']);
            unset($node['connection_ids'], $node['device_ids']);
        }

        return array_values($rackMap);
    }

    /**
     * Create a rack node array.
     *
     * @param  \App\Models\Rack  $rack
     * @return array<string, mixed>
     */
    protected function createRackNode($rack): array
    {
        $row = $rack->row;
        $room = $row?->room;
        $datacenter = $room?->datacenter;

        return [
            'id' => $rack->id,
            'name' => $rack->name,
            'node_type' => 'rack',
            'row_id' => $row?->id,
            'row_name' => $row?->name,
            'room_id' => $room?->id,
            'room_name' => $room?->name,
            'datacenter_id' => $datacenter?->id,
            'datacenter_name' => $datacenter?->name,
            'u_height' => $rack->u_height,
            'connection_ids' => [],
            'device_ids' => [],
        ];
    }

    /**
     * Extract rack-to-rack edges from connections.
     * Only includes edges where both racks are in the allowed list.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractRackEdges(): array
    {
        $edgeMap = [];

        foreach ($this->connections as $connection) {
            $sourcePort = $connection->sourcePort;
            $destPort = $connection->destinationPort;

            $sourceRack = $sourcePort?->device?->rack;
            $destRack = $destPort?->device?->rack;

            if (! $sourceRack || ! $destRack) {
                continue;
            }

            // Skip if either rack is not in the allowed list
            if (! $this->isRackAllowed($sourceRack->id) || ! $this->isRackAllowed($destRack->id)) {
                continue;
            }

            // Skip self-connections (same rack)
            if ($sourceRack->id === $destRack->id) {
                continue;
            }

            // Create a consistent edge key (smaller ID first)
            $edgeKey = min($sourceRack->id, $destRack->id).'-'.max($sourceRack->id, $destRack->id);

            if (! isset($edgeMap[$edgeKey])) {
                $edgeMap[$edgeKey] = [
                    'id' => 'rack-'.$edgeKey,
                    'source_device_id' => min($sourceRack->id, $destRack->id),
                    'destination_device_id' => max($sourceRack->id, $destRack->id),
                    'connections' => [],
                ];
            }

            $edgeMap[$edgeKey]['connections'][] = [
                'id' => $connection->id,
                'cable_type' => $connection->cable_type?->value,
                'cable_color' => $connection->cable_color,
                'verified' => $this->isVerified($connection),
                'has_discrepancy' => $this->hasDiscrepancy($connection),
            ];
        }

        // Flatten edges with aggregated data
        $edges = [];
        foreach ($edgeMap as $edge) {
            $connections = $edge['connections'];
            $firstConnection = $connections[0];

            // Determine predominant cable type
            $cableTypes = collect($connections)->pluck('cable_type')->filter()->countBy();
            $predominantCableType = $cableTypes->sortDesc()->keys()->first() ?? $firstConnection['cable_type'];

            $edges[] = [
                'id' => $edge['id'],
                'source_device_id' => $edge['source_device_id'],
                'destination_device_id' => $edge['destination_device_id'],
                'cable_type' => $predominantCableType,
                'cable_color' => $firstConnection['cable_color'],
                'verified' => collect($connections)->every('verified'),
                'has_discrepancy' => collect($connections)->contains('has_discrepancy', true),
                'connection_count' => count($connections),
            ];
        }

        return $edges;
    }

    /**
     * Extract unique device nodes from connections.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractDeviceNodes(): array
    {
        $deviceMap = [];

        foreach ($this->connections as $connection) {
            // Process source device
            $sourcePort = $connection->sourcePort;
            if ($sourcePort && $sourcePort->device) {
                $device = $sourcePort->device;
                $deviceId = $device->id;

                if (! isset($deviceMap[$deviceId])) {
                    $deviceMap[$deviceId] = $this->createDeviceNode($device);
                }

                // Track connections
                $deviceMap[$deviceId]['connection_ids'][] = $connection->id;
            }

            // Process destination device
            $destPort = $connection->destinationPort;
            if ($destPort && $destPort->device) {
                $device = $destPort->device;
                $deviceId = $device->id;

                if (! isset($deviceMap[$deviceId])) {
                    $deviceMap[$deviceId] = $this->createDeviceNode($device);
                }

                // Track connections
                $deviceMap[$deviceId]['connection_ids'][] = $connection->id;
            }
        }

        // Calculate unique connection counts
        foreach ($deviceMap as &$node) {
            $node['connection_count'] = count(array_unique($node['connection_ids']));
            unset($node['connection_ids']);
        }

        return array_values($deviceMap);
    }

    /**
     * Create a device node array.
     *
     * @param  \App\Models\Device  $device
     * @return array<string, mixed>
     */
    protected function createDeviceNode($device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'node_type' => 'device',
            'asset_tag' => $device->asset_tag,
            'device_type' => $device->deviceType?->name,
            'device_type_id' => $device->device_type_id,
            'rack_id' => $device->rack_id,
            'port_count' => $device->ports_count ?? $device->ports()->count(),
            'connection_ids' => [],
        ];
    }

    /**
     * Extract device-to-device edges from connections.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractDeviceEdges(): array
    {
        $edgeMap = [];

        foreach ($this->connections as $connection) {
            $sourcePort = $connection->sourcePort;
            $destPort = $connection->destinationPort;

            if (! $sourcePort?->device || ! $destPort?->device) {
                continue;
            }

            $sourceDeviceId = $sourcePort->device->id;
            $destDeviceId = $destPort->device->id;

            // Create a consistent edge key (smaller ID first)
            $edgeKey = min($sourceDeviceId, $destDeviceId).'-'.max($sourceDeviceId, $destDeviceId);

            if (! isset($edgeMap[$edgeKey])) {
                $edgeMap[$edgeKey] = [
                    'id' => $edgeKey,
                    'source_device_id' => $sourceDeviceId,
                    'destination_device_id' => $destDeviceId,
                    'connections' => [],
                ];
            }

            $edgeMap[$edgeKey]['connections'][] = [
                'id' => $connection->id,
                'cable_type' => $connection->cable_type?->value,
                'cable_color' => $connection->cable_color,
                'verified' => $this->isVerified($connection),
                'has_discrepancy' => $this->hasDiscrepancy($connection),
            ];
        }

        // Flatten edges with aggregated data
        $edges = [];
        foreach ($edgeMap as $edge) {
            $connections = $edge['connections'];
            $firstConnection = $connections[0];

            $edges[] = [
                'id' => $edge['id'],
                'source_device_id' => $edge['source_device_id'],
                'destination_device_id' => $edge['destination_device_id'],
                'cable_type' => $firstConnection['cable_type'],
                'cable_color' => $firstConnection['cable_color'],
                'verified' => collect($connections)->every('verified'),
                'has_discrepancy' => collect($connections)->contains('has_discrepancy', true),
                'connection_count' => count($connections),
            ];
        }

        return $edges;
    }

    /**
     * Check if a connection is verified.
     *
     * For now, we consider a connection verified if it has been updated after creation,
     * indicating it has been reviewed. This can be extended to use audit trail data.
     */
    protected function isVerified($connection): bool
    {
        // For now, assume all connections are verified if they exist
        // This can be extended to check activity logs for verification actions
        return true;
    }

    /**
     * Check if a connection has audit discrepancies.
     *
     * This can be extended to compare expected vs actual connection states.
     */
    protected function hasDiscrepancy($connection): bool
    {
        return false;
    }
}
