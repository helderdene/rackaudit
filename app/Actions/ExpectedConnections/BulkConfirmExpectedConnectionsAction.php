<?php

namespace App\Actions\ExpectedConnections;

use App\Enums\ExpectedConnectionStatus;
use App\Models\ExpectedConnection;
use Illuminate\Support\Facades\DB;

/**
 * Action to bulk confirm expected connections.
 *
 * Updates the status to confirmed for multiple expected connections
 * that have been reviewed and approved during the review process.
 */
class BulkConfirmExpectedConnectionsAction
{
    /**
     * Execute the bulk confirm action.
     *
     * @param  array<int>  $connectionIds
     * @return array{confirmed_count: int, implementation_file_id: int|null}
     */
    public function execute(array $connectionIds): array
    {
        if (empty($connectionIds)) {
            return [
                'confirmed_count' => 0,
                'implementation_file_id' => null,
            ];
        }

        return DB::transaction(function () use ($connectionIds) {
            // Get the first connection to determine the implementation file ID
            $firstConnection = ExpectedConnection::find($connectionIds[0]);
            $implementationFileId = $firstConnection?->implementation_file_id;

            // Update all specified connections to confirmed status
            $confirmedCount = ExpectedConnection::query()
                ->whereIn('id', $connectionIds)
                ->update([
                    'status' => ExpectedConnectionStatus::Confirmed,
                    'updated_at' => now(),
                ]);

            return [
                'confirmed_count' => $confirmedCount,
                'implementation_file_id' => $implementationFileId,
            ];
        });
    }

    /**
     * Confirm all matched connections for an implementation file.
     *
     * Confirms all connections that have all devices and ports matched
     * (i.e., no null IDs for source/dest device/port).
     *
     * @return array{confirmed_count: int}
     */
    public function confirmAllMatched(int $implementationFileId): array
    {
        return DB::transaction(function () use ($implementationFileId) {
            $confirmedCount = ExpectedConnection::query()
                ->where('implementation_file_id', $implementationFileId)
                ->where('status', ExpectedConnectionStatus::PendingReview)
                ->whereNotNull('source_device_id')
                ->whereNotNull('source_port_id')
                ->whereNotNull('dest_device_id')
                ->whereNotNull('dest_port_id')
                ->update([
                    'status' => ExpectedConnectionStatus::Confirmed,
                    'updated_at' => now(),
                ]);

            return [
                'confirmed_count' => $confirmedCount,
            ];
        });
    }
}
