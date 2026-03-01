<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\ImplementationFile;
use App\Models\Rack;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for providing cascading dropdown data for audit creation.
 *
 * Provides endpoints for fetching rooms, racks, devices, and assignable users
 * to populate the audit creation form's cascading dropdowns.
 */
class AuditDataController extends Controller
{
    /**
     * Roles that can be assigned to execute audits.
     *
     * @var array<string>
     */
    private const ASSIGNABLE_ROLES = [
        'Operator',
        'Auditor',
    ];

    /**
     * Get rooms for a datacenter with rack counts.
     *
     * Returns all rooms belonging to the specified datacenter,
     * including a count of racks in each room for display purposes.
     */
    public function rooms(Datacenter $datacenter): JsonResponse
    {
        $rooms = $datacenter->rooms()
            ->withCount([
                'rows as rack_count' => function ($query) {
                    $query->selectRaw('COUNT(racks.id)')
                        ->join('racks', 'rows.id', '=', 'racks.row_id');
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'rack_count' => (int) $room->rack_count,
            ]);

        return response()->json([
            'data' => $rooms,
        ]);
    }

    /**
     * Get racks for a room.
     *
     * Returns all racks belonging to the specified room through its rows,
     * including position and row name for display purposes.
     */
    public function racks(Room $room): JsonResponse
    {
        $racks = Rack::query()
            ->whereHas('row', function ($query) use ($room) {
                $query->where('room_id', $room->id);
            })
            ->with('row')
            ->orderBy('name')
            ->get()
            ->map(fn (Rack $rack) => [
                'id' => $rack->id,
                'name' => $rack->name,
                'position' => $rack->position,
                'row_name' => $rack->row?->name,
            ]);

        return response()->json([
            'data' => $racks,
        ]);
    }

    /**
     * Get devices for specified rack(s).
     *
     * Accepts an array of rack IDs and returns all devices placed in those racks,
     * including asset_tag and start_u for identification purposes.
     */
    public function devices(Request $request): JsonResponse
    {
        $request->validate([
            'rack_ids' => ['required', 'array', 'min:1'],
            'rack_ids.*' => ['required', 'integer', 'exists:racks,id'],
        ]);

        $rackIds = $request->input('rack_ids');

        $devices = Device::query()
            ->whereIn('rack_id', $rackIds)
            ->with('rack')
            ->orderBy('rack_id')
            ->orderBy('start_u')
            ->get()
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'asset_tag' => $device->asset_tag,
                'start_u' => $device->start_u,
                'rack_id' => $device->rack_id,
                'rack_name' => $device->rack?->name,
            ]);

        return response()->json([
            'data' => $devices,
        ]);
    }

    /**
     * Get users who can be assigned to execute audits.
     *
     * Returns active users with Operator or Auditor roles,
     * who are eligible to be assigned as audit executors.
     */
    public function assignableUsers(): JsonResponse
    {
        $users = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', self::ASSIGNABLE_ROLES);
            })
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * Get the implementation file status for a datacenter.
     *
     * Returns the latest approved implementation file if one exists,
     * or an error message indicating that one needs to be uploaded/approved.
     * Includes a link to the Implementation Files page for the datacenter.
     */
    public function implementationFileStatus(Datacenter $datacenter): JsonResponse
    {
        $approvedFile = ImplementationFile::where('datacenter_id', $datacenter->id)
            ->where('approval_status', 'approved')
            ->orderByDesc('created_at')
            ->first();

        $hasApprovedFile = $approvedFile !== null;

        return response()->json([
            'has_approved_file' => $hasApprovedFile,
            'implementation_file' => $approvedFile ? [
                'id' => $approvedFile->id,
                'original_name' => $approvedFile->original_name,
                'version_number' => $approvedFile->version_number,
            ] : null,
            'error_message' => ! $hasApprovedFile
                ? 'No approved implementation file exists for this datacenter. Please upload and approve an implementation file before creating a connection audit.'
                : null,
            'implementation_files_url' => route('datacenters.implementation-files.index', $datacenter),
        ]);
    }
}
