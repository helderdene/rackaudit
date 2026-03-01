<?php

namespace App\DTOs;

use App\Enums\DiscrepancyType;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection class for ComparisonResult DTOs.
 *
 * Provides filtering, statistics, and pagination capabilities
 * for comparison results.
 *
 * @implements IteratorAggregate<int, ComparisonResult>
 */
class ComparisonResultCollection implements Countable, IteratorAggregate
{
    /**
     * The collection of comparison results.
     *
     * @var array<int, ComparisonResult>
     */
    protected array $items;

    /**
     * Create a new ComparisonResultCollection instance.
     *
     * @param  array<int, ComparisonResult>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Get the iterator for the collection.
     *
     * @return Traversable<int, ComparisonResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Get the count of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get all items in the collection.
     *
     * @return array<int, ComparisonResult>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Add a result to the collection.
     */
    public function add(ComparisonResult $result): self
    {
        $this->items[] = $result;

        return $this;
    }

    /**
     * Merge another collection into this one.
     */
    public function merge(ComparisonResultCollection $other): self
    {
        $this->items = array_merge($this->items, $other->all());

        return $this;
    }

    /**
     * Filter results by discrepancy type.
     *
     * @param  DiscrepancyType|array<int, DiscrepancyType>  $types
     */
    public function filterByDiscrepancyType(DiscrepancyType|array $types): self
    {
        $types = is_array($types) ? $types : [$types];

        $filtered = array_filter(
            $this->items,
            fn (ComparisonResult $result) => in_array($result->discrepancyType, $types, true)
        );

        return new self($filtered);
    }

    /**
     * Filter results by device ID.
     *
     * Matches results where either source or destination device matches.
     */
    public function filterByDevice(int $deviceId): self
    {
        $filtered = array_filter(
            $this->items,
            function (ComparisonResult $result) use ($deviceId) {
                $sourceDevice = $result->getSourceDevice();
                $destDevice = $result->getDestDevice();

                return ($sourceDevice && $sourceDevice->id === $deviceId)
                    || ($destDevice && $destDevice->id === $deviceId);
            }
        );

        return new self($filtered);
    }

    /**
     * Filter results by rack ID.
     *
     * Matches results where either source or destination device's rack matches.
     */
    public function filterByRack(int $rackId): self
    {
        $filtered = array_filter(
            $this->items,
            function (ComparisonResult $result) use ($rackId) {
                $sourceDevice = $result->getSourceDevice();
                $destDevice = $result->getDestDevice();

                return ($sourceDevice && $sourceDevice->rack_id === $rackId)
                    || ($destDevice && $destDevice->rack_id === $rackId);
            }
        );

        return new self($filtered);
    }

    /**
     * Filter to show or exclude acknowledged results.
     */
    public function filterByAcknowledged(bool $showAcknowledged): self
    {
        if ($showAcknowledged) {
            return $this;
        }

        $filtered = array_filter(
            $this->items,
            fn (ComparisonResult $result) => ! $result->isAcknowledged()
        );

        return new self($filtered);
    }

    /**
     * Get a paginated subset of the collection.
     *
     * @return array{items: array<int, ComparisonResult>, total: int, offset: int, limit: int}
     */
    public function paginate(int $offset = 0, int $limit = 50): array
    {
        $total = count($this->items);
        $items = array_slice($this->items, $offset, $limit);

        return [
            'items' => $items,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    /**
     * Get statistics for the collection.
     *
     * @return array{total: int, matched: int, missing: int, unexpected: int, mismatched: int, conflicting: int, acknowledged: int}
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => count($this->items),
            'matched' => 0,
            'missing' => 0,
            'unexpected' => 0,
            'mismatched' => 0,
            'conflicting' => 0,
            'acknowledged' => 0,
        ];

        foreach ($this->items as $result) {
            $key = strtolower($result->discrepancyType->value);
            if (isset($stats[$key])) {
                $stats[$key]++;
            }

            if ($result->isAcknowledged()) {
                $stats['acknowledged']++;
            }
        }

        return $stats;
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the first item in the collection.
     */
    public function first(): ?ComparisonResult
    {
        return $this->items[0] ?? null;
    }

    /**
     * Map over each item in the collection.
     *
     * @template T
     *
     * @param  callable(ComparisonResult): T  $callback
     * @return array<int, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * Convert the collection to an array of arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (ComparisonResult $result) => $result->toArray(),
            $this->items
        );
    }
}
