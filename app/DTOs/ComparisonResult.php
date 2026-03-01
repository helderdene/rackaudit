<?php

namespace App\DTOs;

use App\Enums\DiscrepancyType;
use App\Models\Connection;
use App\Models\DiscrepancyAcknowledgment;
use App\Models\ExpectedConnection;
use App\Models\Port;

/**
 * Data Transfer Object representing a single comparison result.
 *
 * Encapsulates the result of comparing an expected connection against
 * actual connections, including the discrepancy type, related entities,
 * and any acknowledgment that may exist.
 */
readonly class ComparisonResult
{
    /**
     * Create a new ComparisonResult instance.
     *
     * @param  array<string, mixed>|null  $conflictInfo  Information about conflicting expectations
     */
    public function __construct(
        public DiscrepancyType $discrepancyType,
        public ?ExpectedConnection $expectedConnection,
        public ?Connection $actualConnection,
        public ?Port $sourcePort,
        public ?Port $destPort,
        public ?Port $expectedDestPort,
        public ?Port $actualDestPort,
        public ?array $conflictInfo = null,
        public ?DiscrepancyAcknowledgment $acknowledgment = null,
    ) {}

    /**
     * Create a ComparisonResult for a matched connection.
     *
     * Both expected and actual connections exist with matching ports.
     */
    public static function matched(
        ExpectedConnection $expectedConnection,
        Connection $actualConnection,
        ?DiscrepancyAcknowledgment $acknowledgment = null,
    ): self {
        return new self(
            discrepancyType: DiscrepancyType::Matched,
            expectedConnection: $expectedConnection,
            actualConnection: $actualConnection,
            sourcePort: $expectedConnection->sourcePort,
            destPort: $expectedConnection->destPort,
            expectedDestPort: $expectedConnection->destPort,
            actualDestPort: $actualConnection->destinationPort,
            conflictInfo: null,
            acknowledgment: $acknowledgment,
        );
    }

    /**
     * Create a ComparisonResult for an expected but missing connection.
     *
     * Expected connection exists but no corresponding actual connection found.
     */
    public static function missing(
        ExpectedConnection $expectedConnection,
        ?DiscrepancyAcknowledgment $acknowledgment = null,
    ): self {
        return new self(
            discrepancyType: DiscrepancyType::Missing,
            expectedConnection: $expectedConnection,
            actualConnection: null,
            sourcePort: $expectedConnection->sourcePort,
            destPort: $expectedConnection->destPort,
            expectedDestPort: $expectedConnection->destPort,
            actualDestPort: null,
            conflictInfo: null,
            acknowledgment: $acknowledgment,
        );
    }

    /**
     * Create a ComparisonResult for an actual but unexpected connection.
     *
     * Actual connection exists but is not specified in any approved implementation file.
     */
    public static function unexpected(
        Connection $actualConnection,
        ?DiscrepancyAcknowledgment $acknowledgment = null,
    ): self {
        return new self(
            discrepancyType: DiscrepancyType::Unexpected,
            expectedConnection: null,
            actualConnection: $actualConnection,
            sourcePort: $actualConnection->sourcePort,
            destPort: $actualConnection->destinationPort,
            expectedDestPort: null,
            actualDestPort: $actualConnection->destinationPort,
            conflictInfo: null,
            acknowledgment: $acknowledgment,
        );
    }

    /**
     * Create a ComparisonResult for a mismatched (partial match) connection.
     *
     * Source port matches but destination port differs between expected and actual.
     */
    public static function mismatched(
        ExpectedConnection $expectedConnection,
        Connection $actualConnection,
        ?DiscrepancyAcknowledgment $acknowledgment = null,
    ): self {
        return new self(
            discrepancyType: DiscrepancyType::Mismatched,
            expectedConnection: $expectedConnection,
            actualConnection: $actualConnection,
            sourcePort: $expectedConnection->sourcePort,
            destPort: $expectedConnection->destPort,
            expectedDestPort: $expectedConnection->destPort,
            actualDestPort: $actualConnection->destinationPort,
            conflictInfo: null,
            acknowledgment: $acknowledgment,
        );
    }

    /**
     * Create a ComparisonResult for a conflicting expectation.
     *
     * Multiple approved implementation files specify different destinations
     * for the same source port.
     *
     * @param  array<string, mixed>  $conflictInfo  Details about the conflicting expectations
     */
    public static function conflicting(
        ExpectedConnection $expectedConnection,
        ?Connection $actualConnection,
        array $conflictInfo,
        ?DiscrepancyAcknowledgment $acknowledgment = null,
    ): self {
        return new self(
            discrepancyType: DiscrepancyType::Conflicting,
            expectedConnection: $expectedConnection,
            actualConnection: $actualConnection,
            sourcePort: $expectedConnection->sourcePort,
            destPort: $expectedConnection->destPort,
            expectedDestPort: $expectedConnection->destPort,
            actualDestPort: $actualConnection?->destinationPort,
            conflictInfo: $conflictInfo,
            acknowledgment: $acknowledgment,
        );
    }

    /**
     * Check if this result has been acknowledged.
     */
    public function isAcknowledged(): bool
    {
        return $this->acknowledgment !== null;
    }

    /**
     * Get the source device from the result.
     */
    public function getSourceDevice(): ?\App\Models\Device
    {
        return $this->sourcePort?->device;
    }

    /**
     * Get the destination device from the result.
     */
    public function getDestDevice(): ?\App\Models\Device
    {
        return $this->destPort?->device;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'discrepancy_type' => $this->discrepancyType->value,
            'discrepancy_type_label' => $this->discrepancyType->label(),
            'expected_connection_id' => $this->expectedConnection?->id,
            'actual_connection_id' => $this->actualConnection?->id,
            'source_port_id' => $this->sourcePort?->id,
            'dest_port_id' => $this->destPort?->id,
            'expected_dest_port_id' => $this->expectedDestPort?->id,
            'actual_dest_port_id' => $this->actualDestPort?->id,
            'conflict_info' => $this->conflictInfo,
            'is_acknowledged' => $this->isAcknowledged(),
            'acknowledgment_id' => $this->acknowledgment?->id,
        ];
    }
}
