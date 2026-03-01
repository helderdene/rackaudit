<?php

namespace App\Jobs;

use App\Enums\BulkExportStatus;
use App\Enums\BulkImportEntityType;
use App\Exports\Templates\DatacenterTemplateExport;
use App\Exports\Templates\DeviceTemplateExport;
use App\Exports\Templates\PortTemplateExport;
use App\Exports\Templates\RackTemplateExport;
use App\Exports\Templates\RoomTemplateExport;
use App\Exports\Templates\RowTemplateExport;
use App\Models\BulkExport;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Port;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Row;
use App\Services\BulkExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Job for processing bulk export files asynchronously.
 *
 * Processes export queries in chunks, updating progress after each chunk,
 * and generates export files in the requested format (CSV/XLSX).
 */
class ProcessBulkExportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of rows to process per chunk.
     */
    protected int $chunkSize = 1000;

    /**
     * Create a new job instance.
     */
    public function __construct(public BulkExport $bulkExport) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->markAsProcessing();

        try {
            $this->processExport();
        } catch (\Exception $e) {
            $this->markAsFailed($e);

            throw $e;
        }
    }

    /**
     * Mark the export as processing.
     */
    protected function markAsProcessing(): void
    {
        $this->bulkExport->update([
            'status' => BulkExportStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * Process the export and generate the file.
     */
    protected function processExport(): void
    {
        $service = app(BulkExportService::class);
        $query = $service->buildExportQuery(
            $this->bulkExport->entity_type,
            $this->bulkExport->filters ?? []
        );

        // Get column headings from the template
        $headings = $this->getHeadings();

        // Build spreadsheet
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->getSheetTitle());

        // Write header row
        $colIndex = 1;
        foreach ($headings as $heading) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $heading);
            $colIndex++;
        }

        // Apply header styling
        $this->applyHeaderStyles($sheet, count($headings));

        // Process data in chunks
        $currentRow = 2;
        $processedRows = 0;
        $totalRows = $this->bulkExport->total_rows;

        $query->chunk($this->chunkSize, function ($records) use ($sheet, $headings, &$currentRow, &$processedRows) {
            foreach ($records as $record) {
                $rowData = $this->transformRecordToRow($record, $headings);
                $colIndex = 1;

                foreach ($rowData as $value) {
                    $sheet->setCellValueByColumnAndRow($colIndex, $currentRow, $value);
                    $colIndex++;
                }

                $currentRow++;
                $processedRows++;
            }

            // Update progress after each chunk
            $this->updateProgress($processedRows);
        });

        // Auto-size columns
        foreach (range(1, count($headings)) as $colIndex) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Write the file
        $this->writeFile($spreadsheet);

        $this->markAsCompleted();
    }

    /**
     * Get column headings based on entity type.
     *
     * @return array<string>
     */
    protected function getHeadings(): array
    {
        $templateExport = match ($this->bulkExport->entity_type) {
            BulkImportEntityType::Datacenter => new DatacenterTemplateExport,
            BulkImportEntityType::Room => new RoomTemplateExport,
            BulkImportEntityType::Row => new RowTemplateExport,
            BulkImportEntityType::Rack => new RackTemplateExport,
            BulkImportEntityType::Device => new DeviceTemplateExport,
            BulkImportEntityType::Port => new PortTemplateExport,
            BulkImportEntityType::Mixed => throw new \InvalidArgumentException('Mixed entity type is not supported for export.'),
        };

        return $templateExport->headings();
    }

    /**
     * Get the sheet title based on entity type.
     */
    protected function getSheetTitle(): string
    {
        return match ($this->bulkExport->entity_type) {
            BulkImportEntityType::Datacenter => 'Datacenters',
            BulkImportEntityType::Room => 'Rooms',
            BulkImportEntityType::Row => 'Rows',
            BulkImportEntityType::Rack => 'Racks',
            BulkImportEntityType::Device => 'Devices',
            BulkImportEntityType::Port => 'Ports',
            BulkImportEntityType::Mixed => 'Data',
        };
    }

    /**
     * Transform a model record to an array row matching the headings.
     *
     * @param  Datacenter|Room|Row|Rack|Device|Port  $record
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformRecordToRow(mixed $record, array $headings): array
    {
        return match ($this->bulkExport->entity_type) {
            BulkImportEntityType::Datacenter => $this->transformDatacenter($record, $headings),
            BulkImportEntityType::Room => $this->transformRoom($record, $headings),
            BulkImportEntityType::Row => $this->transformRow($record, $headings),
            BulkImportEntityType::Rack => $this->transformRack($record, $headings),
            BulkImportEntityType::Device => $this->transformDevice($record, $headings),
            BulkImportEntityType::Port => $this->transformPort($record, $headings),
            BulkImportEntityType::Mixed => throw new \InvalidArgumentException('Mixed entity type is not supported for export.'),
        };
    }

    /**
     * Transform a Datacenter model to a row array.
     *
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformDatacenter(Datacenter $datacenter, array $headings): array
    {
        $data = [];
        foreach ($headings as $heading) {
            $data[] = match ($heading) {
                'name' => $datacenter->name,
                'address_line_1' => $datacenter->address_line_1,
                'address_line_2' => $datacenter->address_line_2,
                'city' => $datacenter->city,
                'state_province' => $datacenter->state_province,
                'postal_code' => $datacenter->postal_code,
                'country' => $datacenter->country,
                'company_name' => $datacenter->company_name,
                'primary_contact_name' => $datacenter->primary_contact_name,
                'primary_contact_email' => $datacenter->primary_contact_email,
                'primary_contact_phone' => $datacenter->primary_contact_phone,
                'secondary_contact_name' => $datacenter->secondary_contact_name,
                'secondary_contact_email' => $datacenter->secondary_contact_email,
                'secondary_contact_phone' => $datacenter->secondary_contact_phone,
                default => null,
            };
        }

        return $data;
    }

    /**
     * Transform a Room model to a row array.
     *
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformRoom(Room $room, array $headings): array
    {
        $data = [];
        foreach ($headings as $heading) {
            $data[] = match ($heading) {
                'datacenter_name' => $room->datacenter?->name,
                'name' => $room->name,
                'description' => $room->description,
                'square_footage' => $room->square_footage,
                'type' => $room->type?->label(),
                default => null,
            };
        }

        return $data;
    }

    /**
     * Transform a Row model to a row array.
     *
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformRow(Row $row, array $headings): array
    {
        $data = [];
        foreach ($headings as $heading) {
            $data[] = match ($heading) {
                'datacenter_name' => $row->room?->datacenter?->name,
                'room_name' => $row->room?->name,
                'name' => $row->name,
                'position' => $row->position,
                'orientation' => $row->orientation?->label(),
                'status' => $row->status?->label(),
                default => null,
            };
        }

        return $data;
    }

    /**
     * Transform a Rack model to a row array.
     *
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformRack(Rack $rack, array $headings): array
    {
        $data = [];
        foreach ($headings as $heading) {
            $data[] = match ($heading) {
                'datacenter_name' => $rack->row?->room?->datacenter?->name,
                'room_name' => $rack->row?->room?->name,
                'row_name' => $rack->row?->name,
                'name' => $rack->name,
                'position' => $rack->position,
                'u_height' => $rack->u_height?->value,
                'serial_number' => $rack->serial_number,
                'status' => $rack->status?->label(),
                default => null,
            };
        }

        return $data;
    }

    /**
     * Transform a Device model to a row array.
     *
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformDevice(Device $device, array $headings): array
    {
        $data = [];
        foreach ($headings as $heading) {
            $data[] = match ($heading) {
                'datacenter_name' => $device->rack?->row?->room?->datacenter?->name,
                'room_name' => $device->rack?->row?->room?->name,
                'row_name' => $device->rack?->row?->name,
                'rack_name' => $device->rack?->name,
                'name' => $device->name,
                'device_type_name' => $device->deviceType?->name,
                'lifecycle_status' => $device->lifecycle_status?->label(),
                'serial_number' => $device->serial_number,
                'manufacturer' => $device->manufacturer,
                'model' => $device->model,
                'purchase_date' => $device->purchase_date?->format('Y-m-d'),
                'warranty_start_date' => $device->warranty_start_date?->format('Y-m-d'),
                'warranty_end_date' => $device->warranty_end_date?->format('Y-m-d'),
                'u_height' => $device->u_height,
                'depth' => $device->depth?->label(),
                'width_type' => $device->width_type?->label(),
                'rack_face' => $device->rack_face?->label(),
                'start_u' => $device->start_u,
                'notes' => $device->notes,
                default => null,
            };
        }

        return $data;
    }

    /**
     * Transform a Port model to a row array.
     *
     * @param  array<string>  $headings
     * @return array<mixed>
     */
    protected function transformPort(Port $port, array $headings): array
    {
        $data = [];
        foreach ($headings as $heading) {
            $data[] = match ($heading) {
                'datacenter_name' => $port->device?->rack?->row?->room?->datacenter?->name,
                'room_name' => $port->device?->rack?->row?->room?->name,
                'row_name' => $port->device?->rack?->row?->name,
                'rack_name' => $port->device?->rack?->name,
                'device_name' => $port->device?->name,
                'label' => $port->label,
                'type' => $port->type?->label(),
                'subtype' => $port->subtype?->label(),
                'status' => $port->status?->label(),
                'direction' => $port->direction?->label(),
                'position_slot' => $port->position_slot,
                'position_row' => $port->position_row,
                'position_column' => $port->position_column,
                default => null,
            };
        }

        return $data;
    }

    /**
     * Apply header styling to the spreadsheet.
     */
    protected function applyHeaderStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $columnCount): void
    {
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        $headerRange = "A1:{$lastColumn}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ]);
    }

    /**
     * Write the spreadsheet to a file.
     */
    protected function writeFile(Spreadsheet $spreadsheet): void
    {
        $filePath = Storage::disk('local')->path($this->bulkExport->file_path);

        // Ensure the exports directory exists
        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($this->bulkExport->format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(',');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\n");
        } else {
            $writer = new Xlsx($spreadsheet);
        }

        $writer->save($filePath);
    }

    /**
     * Update the progress of the export.
     */
    protected function updateProgress(int $processedRows): void
    {
        $this->bulkExport->update([
            'processed_rows' => $processedRows,
        ]);
    }

    /**
     * Mark the export as completed.
     */
    protected function markAsCompleted(): void
    {
        $this->bulkExport->update([
            'status' => BulkExportStatus::Completed,
            'processed_rows' => $this->bulkExport->total_rows,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the export as failed.
     */
    protected function markAsFailed(\Exception $e): void
    {
        Log::error('Bulk export job failed', [
            'bulk_export_id' => $this->bulkExport->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->bulkExport->update([
            'status' => BulkExportStatus::Failed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk export job completely failed', [
            'bulk_export_id' => $this->bulkExport->id,
            'error' => $exception->getMessage(),
        ]);

        $this->bulkExport->update([
            'status' => BulkExportStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
