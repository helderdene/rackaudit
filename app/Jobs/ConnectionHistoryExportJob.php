<?php

namespace App\Jobs;

use App\Enums\BulkExportStatus;
use App\Models\ActivityLog;
use App\Models\BulkExport;
use App\Models\Connection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * Job for processing connection history exports asynchronously.
 *
 * Supports both CSV and PDF formats. Applies filters stored in the BulkExport
 * record and tracks progress during export generation.
 */
class ConnectionHistoryExportJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of rows to process per chunk.
     */
    protected int $chunkSize = 500;

    /**
     * CSV column headers.
     *
     * @var array<string>
     */
    protected const CSV_HEADERS = [
        'Timestamp',
        'User',
        'Role',
        'IP Address',
        'Action',
        'Connection ID',
        'Old Values Summary',
        'New Values Summary',
    ];

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
            if ($this->bulkExport->format === 'pdf') {
                $this->generatePdfExport();
            } else {
                $this->generateCsvExport();
            }

            $this->markAsCompleted();
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
     * Generate the CSV export file.
     */
    protected function generateCsvExport(): void
    {
        $query = $this->buildQuery();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Connection History');

        // Write header row
        $colIndex = 1;
        foreach (self::CSV_HEADERS as $heading) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $heading);
            $colIndex++;
        }

        // Apply header styling
        $this->applyHeaderStyles($sheet, count(self::CSV_HEADERS));

        // Process data in chunks
        $currentRow = 2;
        $processedRows = 0;

        $query->chunk($this->chunkSize, function ($logs) use ($sheet, &$currentRow, &$processedRows) {
            foreach ($logs as $log) {
                $rowData = $this->transformActivityLogToRow($log);
                $colIndex = 1;

                foreach ($rowData as $value) {
                    $sheet->setCellValueByColumnAndRow($colIndex, $currentRow, $value);
                    $colIndex++;
                }

                $currentRow++;
                $processedRows++;
            }

            $this->updateProgress($processedRows);
        });

        // Auto-size columns
        foreach (range(1, count(self::CSV_HEADERS)) as $colIndex) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Write the CSV file
        $this->writeFile($spreadsheet);
    }

    /**
     * Generate the PDF export file.
     */
    protected function generatePdfExport(): void
    {
        $query = $this->buildQuery();
        $logs = $query->get();

        $processedRows = 0;
        $rows = [];

        foreach ($logs as $log) {
            $rows[] = $this->transformActivityLogToRow($log);
            $processedRows++;

            // Update progress every 100 rows
            if ($processedRows % 100 === 0) {
                $this->updateProgress($processedRows);
            }
        }

        // Final progress update
        $this->updateProgress($processedRows);

        // Generate PDF using Blade template
        $html = $this->generatePdfHtml($rows);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('letter', 'landscape')
            ->setOption('margin-top', '15mm')
            ->setOption('margin-left', '10mm')
            ->setOption('margin-right', '10mm')
            ->setOption('margin-bottom', '15mm');

        // Save PDF file
        $filePath = Storage::disk('local')->path($this->bulkExport->file_path);
        $directory = dirname($filePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->save($filePath);
    }

    /**
     * Generate HTML content for PDF export.
     *
     * @param  array<int, array<mixed>>  $rows
     */
    protected function generatePdfHtml(array $rows): string
    {
        $user = $this->bulkExport->user;
        $filters = $this->bulkExport->filters ?? [];
        $exportDate = now()->format('F j, Y g:i A');

        $filtersSummary = $this->buildFiltersSummary($filters);

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter landscape;
            margin: 15mm 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.4;
        }

        .header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4472C4;
        }

        .header h1 {
            font-size: 18pt;
            color: #333;
            margin-bottom: 5px;
        }

        .header-info {
            font-size: 8pt;
            color: #666;
        }

        .header-info span {
            margin-right: 20px;
        }

        .filters {
            margin-bottom: 10px;
            padding: 8px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 8pt;
        }

        .filters strong {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #4472C4;
            color: white;
            font-weight: bold;
            padding: 8px 6px;
            text-align: left;
            font-size: 8pt;
            border: 1px solid #3366B3;
        }

        td {
            padding: 6px;
            border: 1px solid #ddd;
            font-size: 8pt;
            vertical-align: top;
        }

        tr:nth-child(even) {
            background: #f9f9f9;
        }

        .action-created { color: #16a34a; font-weight: bold; }
        .action-updated { color: #ca8a04; font-weight: bold; }
        .action-deleted { color: #dc2626; font-weight: bold; }
        .action-restored { color: #2563eb; font-weight: bold; }

        .values-cell {
            max-width: 150px;
            word-wrap: break-word;
            font-size: 7pt;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7pt;
            color: #999;
            padding: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Connection History Export</h1>
        <div class="header-info">
            <span><strong>Export Date:</strong> {$exportDate}</span>
            <span><strong>Generated By:</strong> {$user->name}</span>
            <span><strong>Total Records:</strong> {$this->bulkExport->total_rows}</span>
        </div>
    </div>

    {$filtersSummary}

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Timestamp</th>
                <th style="width: 10%;">User</th>
                <th style="width: 8%;">Role</th>
                <th style="width: 10%;">IP Address</th>
                <th style="width: 8%;">Action</th>
                <th style="width: 8%;">Connection</th>
                <th style="width: 22%;">Old Values</th>
                <th style="width: 22%;">New Values</th>
            </tr>
        </thead>
        <tbody>
HTML;

        foreach ($rows as $row) {
            $actionClass = 'action-'.strtolower($row[4]);
            $html .= <<<HTML
            <tr>
                <td>{$row[0]}</td>
                <td>{$row[1]}</td>
                <td>{$row[2]}</td>
                <td>{$row[3]}</td>
                <td class="{$actionClass}">{$row[4]}</td>
                <td>{$row[5]}</td>
                <td class="values-cell">{$row[6]}</td>
                <td class="values-cell">{$row[7]}</td>
            </tr>
HTML;
        }

        $html .= <<<'HTML'
        </tbody>
    </table>

    <div class="footer">
        Generated by RackAudit Connection History Export
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Build filters summary for PDF header.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildFiltersSummary(array $filters): string
    {
        if (empty($filters)) {
            return '<div class="filters"><strong>Filters:</strong> None (all records)</div>';
        }

        $parts = [];

        if (! empty($filters['start_date'])) {
            $parts[] = 'Start Date: '.$filters['start_date'];
        }

        if (! empty($filters['end_date'])) {
            $parts[] = 'End Date: '.$filters['end_date'];
        }

        if (! empty($filters['action'])) {
            $parts[] = 'Action: '.ucfirst($filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $parts[] = 'User ID: '.$filters['user_id'];
        }

        if (! empty($filters['search'])) {
            $parts[] = 'Search: "'.htmlspecialchars($filters['search']).'"';
        }

        $filterText = implode(' | ', $parts);

        return '<div class="filters"><strong>Filters:</strong> '.$filterText.'</div>';
    }

    /**
     * Build the query for connection history with filters applied.
     *
     * @return \Illuminate\Database\Eloquent\Builder<ActivityLog>
     */
    protected function buildQuery()
    {
        $query = ActivityLog::with(['causer'])
            ->where('subject_type', Connection::class)
            ->orderByDesc('created_at');

        $filters = $this->bulkExport->filters ?? [];

        // Apply date range filter
        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $query->inDateRange(
                $filters['start_date'] ?? null,
                $filters['end_date'] ?? null
            );
        }

        // Apply action filter
        if (! empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        // Apply user filter
        if (! empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }

        // Apply search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('old_values', 'like', '%'.$search.'%')
                    ->orWhere('new_values', 'like', '%'.$search.'%');
            });
        }

        return $query;
    }

    /**
     * Transform an ActivityLog to a row array for export.
     *
     * @return array<mixed>
     */
    protected function transformActivityLogToRow(ActivityLog $log): array
    {
        $causer = $log->causer;
        $causerName = $causer?->name ?? 'System';
        $causerRole = '';

        if ($causer) {
            $roles = $causer->getRoleNames();
            $causerRole = $roles->first() ?? '';
        }

        return [
            $log->created_at->format('Y-m-d H:i:s'),
            $causerName,
            $causerRole,
            $log->ip_address ?? '',
            ucfirst($log->action),
            $log->subject_id,
            $this->summarizeValues($log->old_values),
            $this->summarizeValues($log->new_values),
        ];
    }

    /**
     * Summarize JSON values for export column.
     *
     * @param  array<string, mixed>|null  $values
     */
    protected function summarizeValues(?array $values): string
    {
        if ($values === null || empty($values)) {
            return '';
        }

        $summary = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $summary[] = $key.': '.$value;
        }

        return implode(', ', $summary);
    }

    /**
     * Apply header styling to the spreadsheet.
     */
    protected function applyHeaderStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $headerRange = "A1:{$lastColumn}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
    }

    /**
     * Write the spreadsheet to a CSV file.
     */
    protected function writeFile(Spreadsheet $spreadsheet): void
    {
        $filePath = Storage::disk('local')->path($this->bulkExport->file_path);

        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setLineEnding("\n");
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
        Log::error('Connection history export job failed', [
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
        Log::error('Connection history export job completely failed', [
            'bulk_export_id' => $this->bulkExport->id,
            'error' => $exception->getMessage(),
        ]);

        $this->bulkExport->update([
            'status' => BulkExportStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
