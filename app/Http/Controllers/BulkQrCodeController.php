<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateBulkQrCodesRequest;
use App\Models\Datacenter;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\QrCodePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for generating bulk QR code PDFs.
 *
 * Provides functionality to generate PDF documents containing
 * QR code labels for racks and devices in Avery 5160 format.
 */
class BulkQrCodeController extends Controller
{
    public function __construct(
        protected QrCodePdfService $qrCodePdfService
    ) {}

    /**
     * Show the bulk QR code generation form.
     */
    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('QrCodes/Bulk', [
            'entityTypeOptions' => $this->getEntityTypeOptions(),
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Generate and download the QR code PDF.
     */
    public function generate(GenerateBulkQrCodesRequest $request): Response
    {
        $validated = $request->validated();
        $entityType = $validated['entity_type'];
        $filters = $request->getFilters();

        return $this->qrCodePdfService->generatePdf($entityType, $filters);
    }

    /**
     * Get entity type options for dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getEntityTypeOptions(): array
    {
        return [
            ['value' => 'rack', 'label' => 'Racks'],
            ['value' => 'device', 'label' => 'Devices'],
        ];
    }

    /**
     * Get hierarchical filter options.
     *
     * @return array<string, array<int, array{value: int, label: string}>>
     */
    private function getFilterOptions(): array
    {
        return [
            'datacenters' => Datacenter::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Datacenter $dc) => [
                    'value' => $dc->id,
                    'label' => $dc->name,
                ])
                ->toArray(),
            'rooms' => Room::query()
                ->with('datacenter')
                ->orderBy('name')
                ->get()
                ->map(fn (Room $room) => [
                    'value' => $room->id,
                    'label' => $room->name,
                    'datacenter_id' => $room->datacenter_id,
                ])
                ->toArray(),
            'rows' => Row::query()
                ->with('room')
                ->orderBy('name')
                ->get()
                ->map(fn (Row $row) => [
                    'value' => $row->id,
                    'label' => $row->name,
                    'room_id' => $row->room_id,
                    'datacenter_id' => $row->room?->datacenter_id,
                ])
                ->toArray(),
            'racks' => Rack::query()
                ->with('row.room')
                ->orderBy('name')
                ->get()
                ->map(fn (Rack $rack) => [
                    'value' => $rack->id,
                    'label' => $rack->name,
                    'row_id' => $rack->row_id,
                    'room_id' => $rack->row?->room_id,
                    'datacenter_id' => $rack->row?->room?->datacenter_id,
                ])
                ->toArray(),
        ];
    }
}
