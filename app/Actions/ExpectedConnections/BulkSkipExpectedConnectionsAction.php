<?php

namespace App\Actions\ExpectedConnections;

use App\Enums\ExpectedConnectionStatus;
use App\Models\ExpectedConnection;
use Illuminate\Support\Facades\DB;

/**
 * Action to bulk skip expected connections.
 *
 * Updates the status to skipped for multiple expected connections
 * that cannot be matched or are rejected during the review process.
 */
class BulkSkipExpectedConnectionsAction
{
    /**
     * Execute the bulk skip action.
     *
     * @param  array<int>  $connectionIds
     * @return array{skipped_count: int, implementation_file_id: int|null}
     */
    public function execute(array $connectionIds): array
    {
        if (empty($connectionIds)) {
            return [
                'skipped_count' => 0,
                'implementation_file_id' => null,
            ];
        }

        return DB::transaction(function () use ($connectionIds) {
            // Get the first connection to determine the implementation file ID
            $firstConnection = ExpectedConnection::find($connectionIds[0]);
            $implementationFileId = $firstConnection?->implementation_file_id;

            // Update all specified connections to skipped status
            $skippedCount = ExpectedConnection::query()
                ->whereIn('id', $connectionIds)
                ->update([
                    'status' => ExpectedConnectionStatus::Skipped,
                    'updated_at' => now(),
                ]);

            return [
                'skipped_count' => $skippedCount,
                'implementation_file_id' => $implementationFileId,
            ];
        });
    }

    /**
     * Skip all unrecognized connections for an implementation file.
     *
     * Skips all connections that have any null device or port IDs
     * (i.e., couldn't be matched during parsing).
     *
     * @return array{skipped_count: int}
     */
    public function skipAllUnrecognized(int $implementationFileId): array
    {
        return DB::transaction(function () use ($implementationFileId) {
            $skippedCount = ExpectedConnection::query()
                ->where('implementation_file_id', $implementationFileId)
                ->where('status', ExpectedConnectionStatus::PendingReview)
                ->where(function ($query) {
                    $query->whereNull('source_device_id')
                        ->orWhereNull('source_port_id')
                        ->orWhereNull('dest_device_id')
                        ->orWhereNull('dest_port_id');
                })
                ->update([
                    'status' => ExpectedConnectionStatus::Skipped,
                    'updated_at' => now(),
                ]);

            return [
                'skipped_count' => $skippedCount,
            ];
        });
    }
}
