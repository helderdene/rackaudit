<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Port;
use Illuminate\Support\Collection;

/**
 * Service for fuzzy matching device and port names.
 *
 * Uses Levenshtein distance algorithm to match parsed device/port names
 * against existing database records, providing confidence scores.
 */
class FuzzyMatchingService
{
    /**
     * Match confidence threshold (percentage).
     * Matches below this threshold are considered unrecognized.
     */
    public const MATCH_THRESHOLD = 75;

    /**
     * Cache of devices for matching.
     *
     * @var Collection<int, Device>|null
     */
    protected ?Collection $deviceCache = null;

    /**
     * Cache of ports grouped by device ID.
     *
     * @var Collection<int, Collection<int, Port>>|null
     */
    protected ?Collection $portCache = null;

    /**
     * Match a device name against existing devices.
     *
     * @return array{device_id: int|null, confidence: int, match_type: string, original: string, matched_name: string|null}
     */
    public function matchDevice(string $deviceName): array
    {
        $this->loadDeviceCache();

        $deviceName = trim($deviceName);
        $normalizedSearch = $this->normalize($deviceName);

        $bestMatch = null;
        $bestConfidence = 0;

        foreach ($this->deviceCache as $device) {
            $normalizedName = $this->normalize($device->name);

            // Check for exact match first
            if ($normalizedSearch === $normalizedName) {
                return [
                    'device_id' => $device->id,
                    'confidence' => 100,
                    'match_type' => 'exact',
                    'original' => $deviceName,
                    'matched_name' => $device->name,
                ];
            }

            // Calculate similarity
            $confidence = $this->calculateSimilarity($normalizedSearch, $normalizedName);

            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestMatch = $device;
            }
        }

        // Determine match type based on confidence
        if ($bestConfidence >= self::MATCH_THRESHOLD) {
            return [
                'device_id' => $bestMatch?->id,
                'confidence' => $bestConfidence,
                'match_type' => 'suggested',
                'original' => $deviceName,
                'matched_name' => $bestMatch?->name,
            ];
        }

        return [
            'device_id' => null,
            'confidence' => $bestConfidence,
            'match_type' => 'unrecognized',
            'original' => $deviceName,
            'matched_name' => $bestMatch?->name,
        ];
    }

    /**
     * Match a port label against ports on a specific device.
     *
     * @return array{port_id: int|null, confidence: int, match_type: string, original: string, matched_label: string|null}
     */
    public function matchPort(string $portLabel, ?int $deviceId = null): array
    {
        $this->loadPortCache();

        $portLabel = trim($portLabel);
        $normalizedSearch = $this->normalize($portLabel);

        $bestMatch = null;
        $bestConfidence = 0;

        // Get ports to search
        $portsToSearch = $deviceId
            ? ($this->portCache->get($deviceId) ?? collect())
            : $this->portCache->flatten(1);

        foreach ($portsToSearch as $port) {
            $normalizedLabel = $this->normalize($port->label);

            // Check for exact match first
            if ($normalizedSearch === $normalizedLabel) {
                return [
                    'port_id' => $port->id,
                    'confidence' => 100,
                    'match_type' => 'exact',
                    'original' => $portLabel,
                    'matched_label' => $port->label,
                    'device_id' => $port->device_id,
                ];
            }

            // Calculate similarity
            $confidence = $this->calculateSimilarity($normalizedSearch, $normalizedLabel);

            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestMatch = $port;
            }
        }

        // Determine match type based on confidence
        if ($bestConfidence >= self::MATCH_THRESHOLD) {
            return [
                'port_id' => $bestMatch?->id,
                'confidence' => $bestConfidence,
                'match_type' => 'suggested',
                'original' => $portLabel,
                'matched_label' => $bestMatch?->label,
                'device_id' => $bestMatch?->device_id,
            ];
        }

        return [
            'port_id' => null,
            'confidence' => $bestConfidence,
            'match_type' => 'unrecognized',
            'original' => $portLabel,
            'matched_label' => $bestMatch?->label,
            'device_id' => $bestMatch?->device_id ?? null,
        ];
    }

    /**
     * Match an entire connection row (source and destination devices/ports).
     *
     * @param  array{source_device: string, source_port: string, dest_device: string, dest_port: string}  $row
     * @return array{
     *   source_device: array{device_id: int|null, confidence: int, match_type: string, original: string, matched_name: string|null},
     *   source_port: array{port_id: int|null, confidence: int, match_type: string, original: string, matched_label: string|null},
     *   dest_device: array{device_id: int|null, confidence: int, match_type: string, original: string, matched_name: string|null},
     *   dest_port: array{port_id: int|null, confidence: int, match_type: string, original: string, matched_label: string|null},
     *   overall_match_type: string
     * }
     */
    public function matchConnection(array $row): array
    {
        // Match source device first
        $sourceDeviceMatch = $this->matchDevice($row['source_device'] ?? '');

        // Match source port (scoped to matched device if available)
        $sourcePortMatch = $this->matchPort(
            $row['source_port'] ?? '',
            $sourceDeviceMatch['device_id']
        );

        // Match destination device
        $destDeviceMatch = $this->matchDevice($row['dest_device'] ?? '');

        // Match destination port (scoped to matched device if available)
        $destPortMatch = $this->matchPort(
            $row['dest_port'] ?? '',
            $destDeviceMatch['device_id']
        );

        // Determine overall match type
        $matchTypes = [
            $sourceDeviceMatch['match_type'],
            $sourcePortMatch['match_type'],
            $destDeviceMatch['match_type'],
            $destPortMatch['match_type'],
        ];

        $overallMatchType = 'exact';
        if (in_array('unrecognized', $matchTypes, true)) {
            $overallMatchType = 'unrecognized';
        } elseif (in_array('suggested', $matchTypes, true)) {
            $overallMatchType = 'suggested';
        }

        return [
            'source_device' => $sourceDeviceMatch,
            'source_port' => $sourcePortMatch,
            'dest_device' => $destDeviceMatch,
            'dest_port' => $destPortMatch,
            'overall_match_type' => $overallMatchType,
        ];
    }

    /**
     * Calculate similarity percentage using Levenshtein distance.
     */
    protected function calculateSimilarity(string $search, string $target): int
    {
        if (empty($search) || empty($target)) {
            return 0;
        }

        // Calculate Levenshtein distance
        $distance = levenshtein($search, $target);

        // Calculate maximum possible distance (length of longer string)
        $maxLength = max(strlen($search), strlen($target));

        if ($maxLength === 0) {
            return 100;
        }

        // Calculate similarity as percentage
        $similarity = (1 - ($distance / $maxLength)) * 100;

        return (int) round($similarity);
    }

    /**
     * Normalize a string for comparison.
     */
    protected function normalize(string $value): string
    {
        // Convert to lowercase
        $value = strtolower(trim($value));

        // Remove common separators and normalize
        $value = preg_replace('/[\s\-_\.]+/', '', $value);

        return $value ?? '';
    }

    /**
     * Load and cache all devices.
     */
    protected function loadDeviceCache(): void
    {
        if ($this->deviceCache === null) {
            $this->deviceCache = Device::query()
                ->select(['id', 'name', 'asset_tag'])
                ->get();
        }
    }

    /**
     * Load and cache all ports grouped by device.
     */
    protected function loadPortCache(): void
    {
        if ($this->portCache === null) {
            $ports = Port::query()
                ->select(['id', 'device_id', 'label'])
                ->get();

            $this->portCache = $ports->groupBy('device_id');
        }
    }

    /**
     * Clear the caches (useful for testing or after bulk operations).
     */
    public function clearCache(): void
    {
        $this->deviceCache = null;
        $this->portCache = null;
    }

    /**
     * Get match statistics for a set of matched connections.
     *
     * @param  array<int, array{overall_match_type: string}>  $matches
     * @return array{total: int, exact: int, suggested: int, unrecognized: int}
     */
    public function getMatchStatistics(array $matches): array
    {
        $stats = [
            'total' => count($matches),
            'exact' => 0,
            'suggested' => 0,
            'unrecognized' => 0,
        ];

        foreach ($matches as $match) {
            $type = $match['overall_match_type'] ?? 'unrecognized';
            if (isset($stats[$type])) {
                $stats[$type]++;
            }
        }

        return $stats;
    }
}
