<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service for searching across datacenter entities.
 *
 * Provides unified search functionality across datacenters, racks, devices,
 * ports, and connections with RBAC filtering based on user permissions.
 */
class SearchService
{
    /**
     * Roles that have full access to all datacenters.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Maximum results per entity type for quick search.
     */
    private const QUICK_SEARCH_LIMIT = 5;

    /**
     * Default pagination limit for full search.
     */
    private const DEFAULT_LIMIT = 20;

    /**
     * Perform a unified search across all entity types.
     *
     * @param  array<string, mixed>  $filters  Optional filters (datacenter_id, room_id, row_id, rack_id, type)
     * @return array<string, array{items: array<int, array<string, mixed>>, total: int}>
     */
    public function search(string $query, User $user, array $filters = [], int $limit = self::DEFAULT_LIMIT): array
    {
        if (empty(trim($query))) {
            return $this->getEmptyResultsStructure();
        }

        $accessibleDatacenterIds = $this->getAccessibleDatacenterIds($user);

        return [
            'datacenters' => $this->searchDatacenters($query, $accessibleDatacenterIds, $filters, $limit),
            'racks' => $this->searchRacks($query, $accessibleDatacenterIds, $filters, $limit),
            'devices' => $this->searchDevices($query, $accessibleDatacenterIds, $filters, $limit),
            'ports' => $this->searchPorts($query, $accessibleDatacenterIds, $filters, $limit),
            'connections' => $this->searchConnections($query, $accessibleDatacenterIds, $filters, $limit),
        ];
    }

    /**
     * Perform a quick search for dropdown/typeahead with limited results.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array{items: array<int, array<string, mixed>>, total: int}>
     */
    public function quickSearch(string $query, User $user, array $filters = []): array
    {
        return $this->search($query, $user, $filters, self::QUICK_SEARCH_LIMIT);
    }

    /**
     * Search within a specific entity type only.
     *
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function searchByEntityType(string $query, string $entityType, User $user, array $filters = [], int $limit = self::DEFAULT_LIMIT): array
    {
        if (empty(trim($query))) {
            return ['items' => [], 'total' => 0];
        }

        $accessibleDatacenterIds = $this->getAccessibleDatacenterIds($user);

        return match ($entityType) {
            'datacenters' => $this->searchDatacenters($query, $accessibleDatacenterIds, $filters, $limit),
            'racks' => $this->searchRacks($query, $accessibleDatacenterIds, $filters, $limit),
            'devices' => $this->searchDevices($query, $accessibleDatacenterIds, $filters, $limit),
            'ports' => $this->searchPorts($query, $accessibleDatacenterIds, $filters, $limit),
            'connections' => $this->searchConnections($query, $accessibleDatacenterIds, $filters, $limit),
            default => ['items' => [], 'total' => 0],
        };
    }

    /**
     * Search for connections between two devices by their names.
     *
     * Supports queries like "connections between Server-01 and Switch-A".
     * Returns connections where one device matches deviceName1 and the other matches deviceName2,
     * regardless of which is source and which is destination.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function searchConnectionsBetween(string $deviceName1, string $deviceName2, User $user, int $limit = self::DEFAULT_LIMIT): array
    {
        $accessibleDatacenterIds = $this->getAccessibleDatacenterIds($user);

        $queryBuilder = Connection::query()
            ->with([
                'sourcePort.device.rack.row.room.datacenter',
                'destinationPort.device.rack.row.room.datacenter',
            ])
            ->where(function (Builder $q) use ($deviceName1, $deviceName2) {
                // Match connections where source is device1 and destination is device2
                $q->where(function (Builder $subQ) use ($deviceName1, $deviceName2) {
                    $subQ->whereHas('sourcePort.device', function (Builder $devQ) use ($deviceName1) {
                        $devQ->where('name', 'LIKE', "%{$deviceName1}%");
                    })
                    ->whereHas('destinationPort.device', function (Builder $devQ) use ($deviceName2) {
                        $devQ->where('name', 'LIKE', "%{$deviceName2}%");
                    });
                })
                // Or match connections where source is device2 and destination is device1 (reversed)
                ->orWhere(function (Builder $subQ) use ($deviceName1, $deviceName2) {
                    $subQ->whereHas('sourcePort.device', function (Builder $devQ) use ($deviceName2) {
                        $devQ->where('name', 'LIKE', "%{$deviceName2}%");
                    })
                    ->whereHas('destinationPort.device', function (Builder $devQ) use ($deviceName1) {
                        $devQ->where('name', 'LIKE', "%{$deviceName1}%");
                    });
                });
            });

        // Apply RBAC filtering - connection is visible if either endpoint is in accessible datacenter
        if ($accessibleDatacenterIds !== null) {
            $queryBuilder->where(function (Builder $q) use ($accessibleDatacenterIds) {
                $q->whereHas('sourcePort.device.rack.row.room.datacenter', function (Builder $subQ) use ($accessibleDatacenterIds) {
                    $subQ->whereIn('datacenters.id', $accessibleDatacenterIds);
                })
                ->orWhereHas('destinationPort.device.rack.row.room.datacenter', function (Builder $subQ) use ($accessibleDatacenterIds) {
                    $subQ->whereIn('datacenters.id', $accessibleDatacenterIds);
                })
                // Include connections where devices are not in racks
                ->orWhereHas('sourcePort.device', function (Builder $subQ) {
                    $subQ->whereNull('rack_id');
                })
                ->orWhereHas('destinationPort.device', function (Builder $subQ) {
                    $subQ->whereNull('rack_id');
                });
            });
        }

        $total = $queryBuilder->count();
        $connections = $queryBuilder->limit($limit)->get();

        $items = $connections->map(function (Connection $connection) {
            return $this->formatConnectionResult($connection, null);
        })->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get the IDs of datacenters the user can access.
     *
     * @return Collection<int, int>|null  Null means full access (all datacenters)
     */
    private function getAccessibleDatacenterIds(User $user): ?Collection
    {
        // Admins and IT Managers have access to all datacenters
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return null;
        }

        // Other users can only access their assigned datacenters
        return $user->datacenters()->pluck('datacenters.id');
    }

    /**
     * Search datacenters by name, city, country, company_name, contacts.
     *
     * @param  Collection<int, int>|null  $accessibleDatacenterIds
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchDatacenters(string $query, ?Collection $accessibleDatacenterIds, array $filters, int $limit): array
    {
        $queryBuilder = Datacenter::query()
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('city', 'LIKE', "%{$query}%")
                    ->orWhere('country', 'LIKE', "%{$query}%")
                    ->orWhere('company_name', 'LIKE', "%{$query}%")
                    ->orWhere('primary_contact_name', 'LIKE', "%{$query}%")
                    ->orWhere('secondary_contact_name', 'LIKE', "%{$query}%");
            });

        // Apply RBAC filtering
        if ($accessibleDatacenterIds !== null) {
            $queryBuilder->whereIn('id', $accessibleDatacenterIds);
        }

        $total = $queryBuilder->count();
        $datacenters = $queryBuilder->limit($limit)->get();

        $items = $datacenters->map(function (Datacenter $datacenter) use ($query) {
            return [
                'id' => $datacenter->id,
                'name' => $datacenter->name,
                'entity_type' => 'datacenter',
                'breadcrumb' => $datacenter->name,
                'datacenter_id' => $datacenter->id,
                'datacenter_name' => $datacenter->name,
                'city' => $datacenter->city,
                'country' => $datacenter->country,
                'matched_fields' => $this->getMatchedFields($datacenter, $query, [
                    'name', 'city', 'country', 'company_name',
                    'primary_contact_name', 'secondary_contact_name',
                ]),
            ];
        })->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search racks by name, serial_number.
     *
     * @param  Collection<int, int>|null  $accessibleDatacenterIds
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchRacks(string $query, ?Collection $accessibleDatacenterIds, array $filters, int $limit): array
    {
        $queryBuilder = Rack::query()
            ->with(['row.room.datacenter'])
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('serial_number', 'LIKE', "%{$query}%");
            });

        // Apply RBAC filtering through hierarchy
        if ($accessibleDatacenterIds !== null) {
            $queryBuilder->whereHas('row.room.datacenter', function (Builder $q) use ($accessibleDatacenterIds) {
                $q->whereIn('datacenters.id', $accessibleDatacenterIds);
            });
        }

        // Apply hierarchical filters
        $this->applyHierarchicalFilters($queryBuilder, $filters, 'rack');

        $total = $queryBuilder->count();
        $racks = $queryBuilder->limit($limit)->get();

        $items = $racks->map(function (Rack $rack) use ($query) {
            $datacenter = $rack->row?->room?->datacenter;
            $room = $rack->row?->room;
            $row = $rack->row;

            return [
                'id' => $rack->id,
                'name' => $rack->name,
                'entity_type' => 'rack',
                'breadcrumb' => $this->buildRackBreadcrumb($rack),
                'datacenter_id' => $datacenter?->id,
                'datacenter_name' => $datacenter?->name,
                'room_id' => $room?->id,
                'room_name' => $room?->name,
                'row_id' => $row?->id,
                'row_name' => $row?->name,
                'serial_number' => $rack->serial_number,
                'status' => $rack->status?->value,
                'matched_fields' => $this->getMatchedFields($rack, $query, ['name', 'serial_number']),
            ];
        })->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search devices by name, asset_tag, serial_number, manufacturer, model.
     *
     * @param  Collection<int, int>|null  $accessibleDatacenterIds
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchDevices(string $query, ?Collection $accessibleDatacenterIds, array $filters, int $limit): array
    {
        $queryBuilder = Device::query()
            ->with(['rack.row.room.datacenter'])
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('asset_tag', 'LIKE', "%{$query}%")
                    ->orWhere('serial_number', 'LIKE', "%{$query}%")
                    ->orWhere('manufacturer', 'LIKE', "%{$query}%")
                    ->orWhere('model', 'LIKE', "%{$query}%");
            });

        // Apply RBAC filtering through hierarchy
        if ($accessibleDatacenterIds !== null) {
            $queryBuilder->where(function (Builder $q) use ($accessibleDatacenterIds) {
                // Include devices in racks within accessible datacenters
                $q->whereHas('rack.row.room.datacenter', function (Builder $subQ) use ($accessibleDatacenterIds) {
                    $subQ->whereIn('datacenters.id', $accessibleDatacenterIds);
                })
                // Also include unracked devices (they have no RBAC restriction based on current policy)
                ->orWhereNull('rack_id');
            });
        }

        // Apply hierarchical filters
        $this->applyHierarchicalFilters($queryBuilder, $filters, 'device');

        // Apply entity-specific filters
        if (isset($filters['lifecycle_status'])) {
            $queryBuilder->where('lifecycle_status', $filters['lifecycle_status']);
        }

        $total = $queryBuilder->count();
        $devices = $queryBuilder->limit($limit)->get();

        $items = $devices->map(function (Device $device) use ($query) {
            $rack = $device->rack;
            $datacenter = $rack?->row?->room?->datacenter;
            $room = $rack?->row?->room;
            $row = $rack?->row;

            return [
                'id' => $device->id,
                'name' => $device->name,
                'entity_type' => 'device',
                'breadcrumb' => $this->buildDeviceBreadcrumb($device),
                'datacenter_id' => $datacenter?->id,
                'datacenter_name' => $datacenter?->name,
                'room_id' => $room?->id,
                'room_name' => $room?->name,
                'row_id' => $row?->id,
                'row_name' => $row?->name,
                'rack_id' => $rack?->id,
                'rack_name' => $rack?->name,
                'asset_tag' => $device->asset_tag,
                'serial_number' => $device->serial_number,
                'manufacturer' => $device->manufacturer,
                'model' => $device->model,
                'lifecycle_status' => $device->lifecycle_status?->value,
                'matched_fields' => $this->getMatchedFields($device, $query, [
                    'name', 'asset_tag', 'serial_number', 'manufacturer', 'model',
                ]),
            ];
        })->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search ports by label.
     *
     * @param  Collection<int, int>|null  $accessibleDatacenterIds
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchPorts(string $query, ?Collection $accessibleDatacenterIds, array $filters, int $limit): array
    {
        $queryBuilder = Port::query()
            ->with(['device.rack.row.room.datacenter'])
            ->where('label', 'LIKE', "%{$query}%");

        // Apply RBAC filtering through hierarchy
        if ($accessibleDatacenterIds !== null) {
            $queryBuilder->where(function (Builder $q) use ($accessibleDatacenterIds) {
                $q->whereHas('device.rack.row.room.datacenter', function (Builder $subQ) use ($accessibleDatacenterIds) {
                    $subQ->whereIn('datacenters.id', $accessibleDatacenterIds);
                })
                // Include ports on devices not in racks
                ->orWhereHas('device', function (Builder $subQ) {
                    $subQ->whereNull('rack_id');
                });
            });
        }

        // Apply hierarchical filters
        $this->applyHierarchicalFilters($queryBuilder, $filters, 'port');

        // Apply entity-specific filters
        if (isset($filters['port_type'])) {
            $queryBuilder->where('type', $filters['port_type']);
        }
        if (isset($filters['port_status'])) {
            $queryBuilder->where('status', $filters['port_status']);
        }

        $total = $queryBuilder->count();
        $ports = $queryBuilder->limit($limit)->get();

        $items = $ports->map(function (Port $port) use ($query) {
            $device = $port->device;
            $rack = $device?->rack;
            $datacenter = $rack?->row?->room?->datacenter;
            $room = $rack?->row?->room;
            $row = $rack?->row;

            return [
                'id' => $port->id,
                'name' => $port->label,
                'entity_type' => 'port',
                'breadcrumb' => $this->buildPortBreadcrumb($port),
                'datacenter_id' => $datacenter?->id,
                'datacenter_name' => $datacenter?->name,
                'room_id' => $room?->id,
                'room_name' => $room?->name,
                'row_id' => $row?->id,
                'row_name' => $row?->name,
                'rack_id' => $rack?->id,
                'rack_name' => $rack?->name,
                'device_id' => $device?->id,
                'device_name' => $device?->name,
                'label' => $port->label,
                'type' => $port->type?->value,
                'status' => $port->status?->value,
                'matched_fields' => $this->getMatchedFields($port, $query, ['label']),
            ];
        })->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search connections by cable_color, path_notes, and connected device/port names.
     *
     * @param  Collection<int, int>|null  $accessibleDatacenterIds
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function searchConnections(string $query, ?Collection $accessibleDatacenterIds, array $filters, int $limit): array
    {
        $queryBuilder = Connection::query()
            ->with([
                'sourcePort.device.rack.row.room.datacenter',
                'destinationPort.device.rack.row.room.datacenter',
            ])
            ->where(function (Builder $q) use ($query) {
                $q->where('cable_color', 'LIKE', "%{$query}%")
                    ->orWhere('path_notes', 'LIKE', "%{$query}%")
                    // Search by source device/port names
                    ->orWhereHas('sourcePort', function (Builder $subQ) use ($query) {
                        $subQ->where('label', 'LIKE', "%{$query}%");
                    })
                    ->orWhereHas('sourcePort.device', function (Builder $subQ) use ($query) {
                        $subQ->where('name', 'LIKE', "%{$query}%");
                    })
                    // Search by destination device/port names
                    ->orWhereHas('destinationPort', function (Builder $subQ) use ($query) {
                        $subQ->where('label', 'LIKE', "%{$query}%");
                    })
                    ->orWhereHas('destinationPort.device', function (Builder $subQ) use ($query) {
                        $subQ->where('name', 'LIKE', "%{$query}%");
                    });
            });

        // Apply RBAC filtering - connection is visible if either endpoint is in accessible datacenter
        if ($accessibleDatacenterIds !== null) {
            $queryBuilder->where(function (Builder $q) use ($accessibleDatacenterIds) {
                $q->whereHas('sourcePort.device.rack.row.room.datacenter', function (Builder $subQ) use ($accessibleDatacenterIds) {
                    $subQ->whereIn('datacenters.id', $accessibleDatacenterIds);
                })
                ->orWhereHas('destinationPort.device.rack.row.room.datacenter', function (Builder $subQ) use ($accessibleDatacenterIds) {
                    $subQ->whereIn('datacenters.id', $accessibleDatacenterIds);
                })
                // Include connections where devices are not in racks
                ->orWhereHas('sourcePort.device', function (Builder $subQ) {
                    $subQ->whereNull('rack_id');
                })
                ->orWhereHas('destinationPort.device', function (Builder $subQ) {
                    $subQ->whereNull('rack_id');
                });
            });
        }

        $total = $queryBuilder->count();
        $connections = $queryBuilder->limit($limit)->get();

        $items = $connections->map(function (Connection $connection) use ($query) {
            return $this->formatConnectionResult($connection, $query);
        })->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Format a connection result with full endpoint information and location context.
     *
     * @return array<string, mixed>
     */
    private function formatConnectionResult(Connection $connection, ?string $query): array
    {
        $sourcePort = $connection->sourcePort;
        $destPort = $connection->destinationPort;
        $sourceDevice = $sourcePort?->device;
        $destDevice = $destPort?->device;

        // Build source endpoint location context
        $sourceRack = $sourceDevice?->rack;
        $sourceRow = $sourceRack?->row;
        $sourceRoom = $sourceRow?->room;
        $sourceDatacenter = $sourceRoom?->datacenter;

        // Build destination endpoint location context
        $destRack = $destDevice?->rack;
        $destRow = $destRack?->row;
        $destRoom = $destRow?->room;
        $destDatacenter = $destRoom?->datacenter;

        $result = [
            'id' => $connection->id,
            'name' => $this->formatConnectionName($connection),
            'entity_type' => 'connection',
            'breadcrumb' => $this->buildConnectionBreadcrumb($connection),

            // Source endpoint information
            'source_port_id' => $sourcePort?->id,
            'source_port_label' => $sourcePort?->label,
            'source_device_id' => $sourceDevice?->id,
            'source_device_name' => $sourceDevice?->name,
            'source_rack_id' => $sourceRack?->id,
            'source_rack_name' => $sourceRack?->name,
            'source_row_id' => $sourceRow?->id,
            'source_row_name' => $sourceRow?->name,
            'source_room_id' => $sourceRoom?->id,
            'source_room_name' => $sourceRoom?->name,
            'source_datacenter_id' => $sourceDatacenter?->id,
            'source_datacenter_name' => $sourceDatacenter?->name,
            'source_location_breadcrumb' => $this->buildEndpointLocationBreadcrumb($sourceDevice),

            // Destination endpoint information
            'destination_port_id' => $destPort?->id,
            'destination_port_label' => $destPort?->label,
            'destination_device_id' => $destDevice?->id,
            'destination_device_name' => $destDevice?->name,
            'destination_rack_id' => $destRack?->id,
            'destination_rack_name' => $destRack?->name,
            'destination_row_id' => $destRow?->id,
            'destination_row_name' => $destRow?->name,
            'destination_room_id' => $destRoom?->id,
            'destination_room_name' => $destRoom?->name,
            'destination_datacenter_id' => $destDatacenter?->id,
            'destination_datacenter_name' => $destDatacenter?->name,
            'destination_location_breadcrumb' => $this->buildEndpointLocationBreadcrumb($destDevice),

            // Cable properties
            'cable_color' => $connection->cable_color,
            'cable_type' => $connection->cable_type?->value,
            'cable_length' => $connection->cable_length,
            'path_notes' => $connection->path_notes,

            // Matched fields for highlighting
            'matched_fields' => $query !== null ? $this->getConnectionMatchedFields($connection, $query) : [],
        ];

        return $result;
    }

    /**
     * Build location breadcrumb for an endpoint device.
     */
    private function buildEndpointLocationBreadcrumb(?Device $device): ?string
    {
        if ($device === null) {
            return null;
        }

        $parts = [];

        $rack = $device->rack;
        $datacenter = $rack?->row?->room?->datacenter;
        $room = $rack?->row?->room;
        $row = $rack?->row;

        if ($datacenter) {
            $parts[] = $datacenter->name;
        }
        if ($room) {
            $parts[] = $room->name;
        }
        if ($row) {
            $parts[] = $row->name;
        }
        if ($rack) {
            $parts[] = $rack->name;
        }

        return count($parts) > 0 ? implode(' > ', $parts) : null;
    }

    /**
     * Apply hierarchical filters (datacenter, room, row, rack) to a query.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $queryBuilder
     * @param  array<string, mixed>  $filters
     */
    private function applyHierarchicalFilters(Builder $queryBuilder, array $filters, string $entityType): void
    {
        $datacenterFilter = $filters['datacenter_id'] ?? null;
        $roomFilter = $filters['room_id'] ?? null;
        $rowFilter = $filters['row_id'] ?? null;
        $rackFilter = $filters['rack_id'] ?? null;

        match ($entityType) {
            'rack' => $this->applyRackHierarchicalFilters($queryBuilder, $datacenterFilter, $roomFilter, $rowFilter),
            'device' => $this->applyDeviceHierarchicalFilters($queryBuilder, $datacenterFilter, $roomFilter, $rowFilter, $rackFilter),
            'port' => $this->applyPortHierarchicalFilters($queryBuilder, $datacenterFilter, $roomFilter, $rowFilter, $rackFilter),
            default => null,
        };
    }

    /**
     * Apply hierarchical filters for rack queries.
     *
     * @param  Builder<Rack>  $queryBuilder
     */
    private function applyRackHierarchicalFilters(Builder $queryBuilder, mixed $datacenterId, mixed $roomId, mixed $rowId): void
    {
        if ($rowId !== null) {
            $queryBuilder->where('row_id', $rowId);
        } elseif ($roomId !== null) {
            $queryBuilder->whereHas('row', function (Builder $q) use ($roomId) {
                $q->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $queryBuilder->whereHas('row.room', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });
        }
    }

    /**
     * Apply hierarchical filters for device queries.
     *
     * @param  Builder<Device>  $queryBuilder
     */
    private function applyDeviceHierarchicalFilters(Builder $queryBuilder, mixed $datacenterId, mixed $roomId, mixed $rowId, mixed $rackId): void
    {
        if ($rackId !== null) {
            $queryBuilder->where('rack_id', $rackId);
        } elseif ($rowId !== null) {
            $queryBuilder->whereHas('rack', function (Builder $q) use ($rowId) {
                $q->where('row_id', $rowId);
            });
        } elseif ($roomId !== null) {
            $queryBuilder->whereHas('rack.row', function (Builder $q) use ($roomId) {
                $q->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $queryBuilder->whereHas('rack.row.room', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });
        }
    }

    /**
     * Apply hierarchical filters for port queries.
     *
     * @param  Builder<Port>  $queryBuilder
     */
    private function applyPortHierarchicalFilters(Builder $queryBuilder, mixed $datacenterId, mixed $roomId, mixed $rowId, mixed $rackId): void
    {
        if ($rackId !== null) {
            $queryBuilder->whereHas('device', function (Builder $q) use ($rackId) {
                $q->where('rack_id', $rackId);
            });
        } elseif ($rowId !== null) {
            $queryBuilder->whereHas('device.rack', function (Builder $q) use ($rowId) {
                $q->where('row_id', $rowId);
            });
        } elseif ($roomId !== null) {
            $queryBuilder->whereHas('device.rack.row', function (Builder $q) use ($roomId) {
                $q->where('room_id', $roomId);
            });
        } elseif ($datacenterId !== null) {
            $queryBuilder->whereHas('device.rack.row.room', function (Builder $q) use ($datacenterId) {
                $q->where('datacenter_id', $datacenterId);
            });
        }
    }

    /**
     * Build breadcrumb for a rack.
     */
    private function buildRackBreadcrumb(Rack $rack): string
    {
        $parts = [];

        $datacenter = $rack->row?->room?->datacenter;
        $room = $rack->row?->room;
        $row = $rack->row;

        if ($datacenter) {
            $parts[] = $datacenter->name;
        }
        if ($room) {
            $parts[] = $room->name;
        }
        if ($row) {
            $parts[] = $row->name;
        }
        $parts[] = $rack->name;

        return implode(' > ', $parts);
    }

    /**
     * Build breadcrumb for a device.
     */
    private function buildDeviceBreadcrumb(Device $device): string
    {
        $parts = [];

        $rack = $device->rack;
        $datacenter = $rack?->row?->room?->datacenter;
        $room = $rack?->row?->room;
        $row = $rack?->row;

        if ($datacenter) {
            $parts[] = $datacenter->name;
        }
        if ($room) {
            $parts[] = $room->name;
        }
        if ($row) {
            $parts[] = $row->name;
        }
        if ($rack) {
            $parts[] = $rack->name;
        }
        $parts[] = $device->name;

        return implode(' > ', $parts);
    }

    /**
     * Build breadcrumb for a port.
     */
    private function buildPortBreadcrumb(Port $port): string
    {
        $parts = [];

        $device = $port->device;
        $rack = $device?->rack;
        $datacenter = $rack?->row?->room?->datacenter;
        $room = $rack?->row?->room;
        $row = $rack?->row;

        if ($datacenter) {
            $parts[] = $datacenter->name;
        }
        if ($room) {
            $parts[] = $room->name;
        }
        if ($row) {
            $parts[] = $row->name;
        }
        if ($rack) {
            $parts[] = $rack->name;
        }
        if ($device) {
            $parts[] = $device->name;
        }
        $parts[] = $port->label;

        return implode(' > ', $parts);
    }

    /**
     * Build breadcrumb for a connection showing both endpoints.
     */
    private function buildConnectionBreadcrumb(Connection $connection): string
    {
        $sourceDevice = $connection->sourcePort?->device;
        $destDevice = $connection->destinationPort?->device;
        $sourceLabel = $connection->sourcePort?->label;
        $destLabel = $connection->destinationPort?->label;

        $sourcePath = $sourceDevice ? "{$sourceDevice->name}:{$sourceLabel}" : $sourceLabel;
        $destPath = $destDevice ? "{$destDevice->name}:{$destLabel}" : $destLabel;

        return "{$sourcePath} <-> {$destPath}";
    }

    /**
     * Format connection name for display.
     */
    private function formatConnectionName(Connection $connection): string
    {
        $sourceDevice = $connection->sourcePort?->device?->name ?? 'Unknown';
        $sourcePort = $connection->sourcePort?->label ?? 'Unknown';
        $destDevice = $connection->destinationPort?->device?->name ?? 'Unknown';
        $destPort = $connection->destinationPort?->label ?? 'Unknown';

        return "{$sourceDevice}:{$sourcePort} to {$destDevice}:{$destPort}";
    }

    /**
     * Get list of fields that matched the search query.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array<string>  $searchableFields
     * @return array<string>
     */
    private function getMatchedFields($model, string $query, array $searchableFields): array
    {
        $matchedFields = [];
        $lowerQuery = strtolower($query);

        foreach ($searchableFields as $field) {
            $value = $model->{$field};
            if ($value !== null && str_contains(strtolower((string) $value), $lowerQuery)) {
                $matchedFields[] = $field;
            }
        }

        return $matchedFields;
    }

    /**
     * Get matched fields for a connection, including related device/port names.
     *
     * @return array<string>
     */
    private function getConnectionMatchedFields(Connection $connection, string $query): array
    {
        $matchedFields = [];
        $lowerQuery = strtolower($query);

        if ($connection->cable_color && str_contains(strtolower($connection->cable_color), $lowerQuery)) {
            $matchedFields[] = 'cable_color';
        }
        if ($connection->path_notes && str_contains(strtolower($connection->path_notes), $lowerQuery)) {
            $matchedFields[] = 'path_notes';
        }

        $sourcePort = $connection->sourcePort;
        $destPort = $connection->destinationPort;

        if ($sourcePort?->label && str_contains(strtolower($sourcePort->label), $lowerQuery)) {
            $matchedFields[] = 'source_port_label';
        }
        if ($sourcePort?->device?->name && str_contains(strtolower($sourcePort->device->name), $lowerQuery)) {
            $matchedFields[] = 'source_device_name';
        }
        if ($destPort?->label && str_contains(strtolower($destPort->label), $lowerQuery)) {
            $matchedFields[] = 'destination_port_label';
        }
        if ($destPort?->device?->name && str_contains(strtolower($destPort->device->name), $lowerQuery)) {
            $matchedFields[] = 'destination_device_name';
        }

        return $matchedFields;
    }

    /**
     * Get the empty results structure.
     *
     * @return array<string, array{items: array<int, array<string, mixed>>, total: int}>
     */
    private function getEmptyResultsStructure(): array
    {
        return [
            'datacenters' => ['items' => [], 'total' => 0],
            'racks' => ['items' => [], 'total' => 0],
            'devices' => ['items' => [], 'total' => 0],
            'ports' => ['items' => [], 'total' => 0],
            'connections' => ['items' => [], 'total' => 0],
        ];
    }
}
