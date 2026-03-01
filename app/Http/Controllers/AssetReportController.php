<?php

namespace App\Http\Controllers;

use App\Enums\DeviceLifecycleStatus;
use App\Exports\AssetReportExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Room;
use App\Services\AssetCalculationService;
use App\Services\AssetReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing asset reports.
 *
 * Provides device inventory reports, warranty status breakdown,
 * lifecycle distribution, asset counts, and export capabilities (PDF/CSV).
 */
class AssetReportController extends Controller
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
     * Number of devices per page in the inventory table.
     */
    private const DEVICES_PER_PAGE = 25;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AssetCalculationService $calculationService,
        protected AssetReportService $reportService
    ) {}

    /**
     * Display the asset reports page.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        // Get rooms for the selected datacenter (for cascading filter)
        $roomOptions = $this->getRoomOptions($datacenterId);
        $roomId = $this->validateRoomId(
            $request->input('room_id'),
            $datacenterId,
            $roomOptions->pluck('id')->toArray()
        );

        // Get device type options
        $deviceTypeOptions = $this->getDeviceTypeOptions();
        $deviceTypeId = $this->validateDeviceTypeId(
            $request->input('device_type_id'),
            $deviceTypeOptions->pluck('id')->toArray()
        );

        // Get lifecycle status options
        $lifecycleStatusOptions = $this->getLifecycleStatusOptions();
        $lifecycleStatus = $this->validateLifecycleStatus($request->input('lifecycle_status'));

        // Get manufacturer options and validate
        $manufacturerOptions = $this->getManufacturerOptions();
        $manufacturer = $this->validateManufacturer(
            $request->input('manufacturer'),
            $manufacturerOptions
        );

        // Get warranty date range
        $warrantyStart = $this->validateDateString($request->input('warranty_start'));
        $warrantyEnd = $this->validateDateString($request->input('warranty_end'));

        // Get asset metrics via service
        $metrics = $this->calculationService->getAssetMetrics(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd
        );

        // Get paginated device inventory
        $devicesPaginated = $this->getDeviceInventory(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd,
            $request->input('page', 1)
        );

        // Transform devices for frontend
        $devices = collect($devicesPaginated->items())->map(function (Device $device) {
            return [
                'id' => $device->id,
                'asset_tag' => $device->asset_tag,
                'name' => $device->name,
                'serial_number' => $device->serial_number,
                'manufacturer' => $device->manufacturer,
                'model' => $device->model,
                'device_type' => $device->deviceType ? [
                    'id' => $device->deviceType->id,
                    'name' => $device->deviceType->name,
                ] : null,
                'lifecycle_status' => $device->lifecycle_status?->value,
                'lifecycle_status_label' => $device->lifecycle_status?->label() ?? 'Unknown',
                'datacenter_name' => $device->rack?->row?->room?->datacenter?->name,
                'room_name' => $device->rack?->row?->room?->name,
                'rack_name' => $device->rack?->name,
                'start_u' => $device->start_u,
                'warranty_end_date' => $device->warranty_end_date?->format('Y-m-d'),
            ];
        })->toArray();

        // Add devices and pagination to metrics
        $metrics['devices'] = $devices;
        $metrics['pagination'] = [
            'current_page' => $devicesPaginated->currentPage(),
            'last_page' => $devicesPaginated->lastPage(),
            'per_page' => $devicesPaginated->perPage(),
            'total' => $devicesPaginated->total(),
        ];

        return Inertia::render('AssetReports/Index', [
            'metrics' => $metrics,
            'datacenterOptions' => $datacenterOptions->values()->toArray(),
            'roomOptions' => $roomOptions->values()->toArray(),
            'deviceTypeOptions' => $deviceTypeOptions->values()->toArray(),
            'lifecycleStatusOptions' => $lifecycleStatusOptions,
            'manufacturerOptions' => $manufacturerOptions,
            'filters' => [
                'datacenter_id' => $datacenterId,
                'room_id' => $roomId,
                'device_type_id' => $deviceTypeId,
                'lifecycle_status' => $lifecycleStatus,
                'manufacturer' => $manufacturer,
                'warranty_start' => $warrantyStart,
                'warranty_end' => $warrantyEnd,
            ],
        ]);
    }

    /**
     * Export asset report as PDF.
     */
    public function exportPdf(Request $request): StreamedResponse|BinaryFileResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        $roomOptions = $this->getRoomOptions($datacenterId);
        $roomId = $this->validateRoomId(
            $request->input('room_id'),
            $datacenterId,
            $roomOptions->pluck('id')->toArray()
        );

        $deviceTypeOptions = $this->getDeviceTypeOptions();
        $deviceTypeId = $this->validateDeviceTypeId(
            $request->input('device_type_id'),
            $deviceTypeOptions->pluck('id')->toArray()
        );

        $lifecycleStatus = $this->validateLifecycleStatus($request->input('lifecycle_status'));
        $manufacturer = $this->validateManufacturer(
            $request->input('manufacturer'),
            $this->getManufacturerOptions()
        );
        $warrantyStart = $this->validateDateString($request->input('warranty_start'));
        $warrantyEnd = $this->validateDateString($request->input('warranty_end'));

        // Generate the PDF report
        $filePath = $this->reportService->generatePdfReport(
            [
                'datacenter_id' => $datacenterId,
                'room_id' => $roomId,
                'device_type_id' => $deviceTypeId,
                'lifecycle_status' => $lifecycleStatus,
                'manufacturer' => $manufacturer,
                'warranty_start' => $warrantyStart,
                'warranty_end' => $warrantyEnd,
            ],
            $user
        );

        $filename = basename($filePath);

        return Storage::disk('local')->download($filePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Export asset report as CSV.
     */
    public function exportCsv(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        // Get accessible datacenters based on user role
        $datacenterOptions = $this->getAccessibleDatacenters($user);
        $accessibleDatacenterIds = $datacenterOptions->pluck('id')->toArray();

        // Get and validate filter values
        $datacenterId = $this->validateDatacenterId(
            $request->input('datacenter_id'),
            $accessibleDatacenterIds
        );

        $roomOptions = $this->getRoomOptions($datacenterId);
        $roomId = $this->validateRoomId(
            $request->input('room_id'),
            $datacenterId,
            $roomOptions->pluck('id')->toArray()
        );

        $deviceTypeOptions = $this->getDeviceTypeOptions();
        $deviceTypeId = $this->validateDeviceTypeId(
            $request->input('device_type_id'),
            $deviceTypeOptions->pluck('id')->toArray()
        );

        $lifecycleStatus = $this->validateLifecycleStatus($request->input('lifecycle_status'));
        $manufacturer = $this->validateManufacturer(
            $request->input('manufacturer'),
            $this->getManufacturerOptions()
        );
        $warrantyStart = $this->validateDateString($request->input('warranty_start'));
        $warrantyEnd = $this->validateDateString($request->input('warranty_end'));

        $filters = [
            'datacenter_id' => $datacenterId,
            'room_id' => $roomId,
            'device_type_id' => $deviceTypeId,
            'lifecycle_status' => $lifecycleStatus,
            'manufacturer' => $manufacturer,
            'warranty_start' => $warrantyStart,
            'warranty_end' => $warrantyEnd,
        ];

        $timestamp = now()->format('Y-m-d-His');
        $filename = "asset-report-{$timestamp}.csv";

        return Excel::download(new AssetReportExport($filters), $filename);
    }

    /**
     * Get paginated device inventory with eager loading.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Device>
     */
    private function getDeviceInventory(
        ?int $datacenterId,
        ?int $roomId,
        ?int $deviceTypeId,
        ?string $lifecycleStatus,
        ?string $manufacturer,
        ?string $warrantyStart,
        ?string $warrantyEnd,
        int $page = 1
    ) {
        $query = $this->calculationService->buildFilteredDeviceQuery(
            $datacenterId,
            $roomId,
            $deviceTypeId,
            $lifecycleStatus,
            $manufacturer,
            $warrantyStart,
            $warrantyEnd
        );

        return $query
            ->with(['deviceType', 'rack.row.room.datacenter'])
            ->orderBy('asset_tag')
            ->paginate(self::DEVICES_PER_PAGE, ['*'], 'page', $page);
    }

    /**
     * Get datacenters accessible by the user.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getAccessibleDatacenters($user): Collection
    {
        $query = Datacenter::query()->orderBy('name');

        if (! $user->hasAnyRole(self::ADMIN_ROLES)) {
            $assignedDatacenterIds = $user->datacenters()->pluck('datacenters.id');
            $query->whereIn('id', $assignedDatacenterIds);
        }

        return $query->get()->map(fn (Datacenter $datacenter) => [
            'id' => $datacenter->id,
            'name' => $datacenter->name,
        ]);
    }

    /**
     * Get rooms for a datacenter (for cascading filter).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getRoomOptions(?int $datacenterId): Collection
    {
        if ($datacenterId === null) {
            return collect();
        }

        return Room::query()
            ->where('datacenter_id', $datacenterId)
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->name,
            ]);
    }

    /**
     * Get device type options for filter dropdown.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function getDeviceTypeOptions(): Collection
    {
        return DeviceType::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DeviceType $type) => [
                'id' => $type->id,
                'name' => $type->name,
            ]);
    }

    /**
     * Get lifecycle status options for filter dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getLifecycleStatusOptions(): array
    {
        return array_map(
            fn (DeviceLifecycleStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            DeviceLifecycleStatus::cases()
        );
    }

    /**
     * Get distinct manufacturer options from devices.
     *
     * @return array<string>
     */
    private function getManufacturerOptions(): array
    {
        return Device::query()
            ->whereNotNull('manufacturer')
            ->distinct()
            ->orderBy('manufacturer')
            ->pluck('manufacturer')
            ->toArray();
    }

    /**
     * Validate and return datacenter ID if it's in the accessible list.
     *
     * @param  array<int>  $accessibleIds
     */
    private function validateDatacenterId(mixed $datacenterId, array $accessibleIds): ?int
    {
        if ($datacenterId === null || $datacenterId === '') {
            return null;
        }

        $id = (int) $datacenterId;

        return in_array($id, $accessibleIds, true) ? $id : null;
    }

    /**
     * Validate and return room ID if it belongs to the selected datacenter.
     *
     * @param  array<int>  $validRoomIds
     */
    private function validateRoomId(mixed $roomId, ?int $datacenterId, array $validRoomIds): ?int
    {
        if ($datacenterId === null || $roomId === null || $roomId === '') {
            return null;
        }

        $id = (int) $roomId;

        return in_array($id, $validRoomIds, true) ? $id : null;
    }

    /**
     * Validate and return device type ID if it exists.
     *
     * @param  array<int>  $validTypeIds
     */
    private function validateDeviceTypeId(mixed $deviceTypeId, array $validTypeIds): ?int
    {
        if ($deviceTypeId === null || $deviceTypeId === '') {
            return null;
        }

        $id = (int) $deviceTypeId;

        return in_array($id, $validTypeIds, true) ? $id : null;
    }

    /**
     * Validate and return lifecycle status if it's a valid enum value.
     */
    private function validateLifecycleStatus(mixed $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $enumStatus = DeviceLifecycleStatus::tryFrom($status);

        return $enumStatus?->value;
    }

    /**
     * Validate and return manufacturer if it exists in the options.
     *
     * @param  array<string>  $validManufacturers
     */
    private function validateManufacturer(mixed $manufacturer, array $validManufacturers): ?string
    {
        if ($manufacturer === null || $manufacturer === '') {
            return null;
        }

        return in_array($manufacturer, $validManufacturers, true) ? $manufacturer : null;
    }

    /**
     * Validate and return a date string in YYYY-MM-DD format.
     */
    private function validateDateString(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        // Validate date format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return null;
    }
}
