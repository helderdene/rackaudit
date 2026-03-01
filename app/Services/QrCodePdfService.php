<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Rack;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

/**
 * Service for generating PDF documents with QR code labels.
 *
 * Supports Avery 5160 format (30 labels per page, 3 columns x 10 rows).
 * Each label includes a QR code, name, and secondary label.
 */
class QrCodePdfService
{
    /**
     * Avery 5160 layout specifications (in inches).
     */
    private const LABELS_PER_ROW = 3;

    private const LABELS_PER_COLUMN = 10;

    private const LABELS_PER_PAGE = 30;

    /**
     * Generate a PDF with QR code labels for the given entity type and filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function generatePdf(string $entityType, array $filters = []): Response
    {
        $items = $this->getItems($entityType, $filters);
        $labels = $this->prepareLabels($items, $entityType);

        $html = $this->generateHtml($labels);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('letter', 'portrait')
            ->setOption('margin-top', '12.7mm')
            ->setOption('margin-left', '4.8mm')
            ->setOption('margin-right', '4.8mm')
            ->setOption('margin-bottom', '12.7mm');

        $filename = $this->generateFilename($entityType);

        return $pdf->download($filename);
    }

    /**
     * Get items based on entity type and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Collection<int, Device|Rack>
     */
    protected function getItems(string $entityType, array $filters): \Illuminate\Database\Eloquent\Collection
    {
        if ($entityType === 'device') {
            $query = Device::query()->with(['rack.row.room.datacenter']);
        } else {
            $query = Rack::query()->with(['row.room.datacenter']);
        }

        $this->applyFilters($query, $filters, $entityType);

        return $query->orderBy('name')->get();
    }

    /**
     * Apply hierarchical filters to the query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters, string $entityType): void
    {
        $datacenterId = $filters['datacenter_id'] ?? null;
        $roomId = $filters['room_id'] ?? null;
        $rowId = $filters['row_id'] ?? null;
        $rackId = $filters['rack_id'] ?? null;

        if ($entityType === 'device') {
            if ($rackId !== null) {
                $query->where('rack_id', $rackId);
            } elseif ($rowId !== null) {
                $query->whereHas('rack', function (Builder $rackQuery) use ($rowId) {
                    $rackQuery->where('row_id', $rowId);
                });
            } elseif ($roomId !== null) {
                $query->whereHas('rack.row', function (Builder $rowQuery) use ($roomId) {
                    $rowQuery->where('room_id', $roomId);
                });
            } elseif ($datacenterId !== null) {
                $query->whereHas('rack.row.room', function (Builder $roomQuery) use ($datacenterId) {
                    $roomQuery->where('datacenter_id', $datacenterId);
                });
            }
        } else {
            // Rack entity type
            if ($rackId !== null) {
                $query->where('id', $rackId);
            } elseif ($rowId !== null) {
                $query->where('row_id', $rowId);
            } elseif ($roomId !== null) {
                $query->whereHas('row', function (Builder $rowQuery) use ($roomId) {
                    $rowQuery->where('room_id', $roomId);
                });
            } elseif ($datacenterId !== null) {
                $query->whereHas('row.room', function (Builder $roomQuery) use ($datacenterId) {
                    $roomQuery->where('datacenter_id', $datacenterId);
                });
            }
        }
    }

    /**
     * Prepare label data for each item.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Device|Rack>  $items
     * @return array<int, array{name: string, secondaryLabel: string|null, url: string, qrCode: string}>
     */
    protected function prepareLabels(\Illuminate\Database\Eloquent\Collection $items, string $entityType): array
    {
        $labels = [];

        foreach ($items as $item) {
            $url = $this->generateUrl($item, $entityType);
            $qrCode = $this->generateQrCode($url);

            $labels[] = [
                'name' => $item->name,
                'secondaryLabel' => $entityType === 'device'
                    ? $item->asset_tag
                    : $item->serial_number,
                'url' => $url,
                'qrCode' => $qrCode,
            ];
        }

        return $labels;
    }

    /**
     * Generate the URL for an item.
     */
    protected function generateUrl(Device|Rack $item, string $entityType): string
    {
        $baseUrl = config('app.url');

        if ($entityType === 'device') {
            return "{$baseUrl}/devices/{$item->id}";
        }

        // For racks, generate the nested URL
        $rack = $item;
        $row = $rack->row;
        $room = $row?->room;
        $datacenter = $room?->datacenter;

        if ($datacenter && $room && $row) {
            return "{$baseUrl}/datacenters/{$datacenter->id}/rooms/{$room->id}/rows/{$row->id}/racks/{$rack->id}";
        }

        // Fallback if relationships are missing
        return "{$baseUrl}/racks/{$rack->id}";
    }

    /**
     * Generate a QR code SVG string for the given URL.
     */
    protected function generateQrCode(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(100, 1),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Generate the HTML for the PDF document.
     *
     * @param  array<int, array{name: string, secondaryLabel: string|null, url: string, qrCode: string}>  $labels
     */
    protected function generateHtml(array $labels): string
    {
        $pages = array_chunk($labels, self::LABELS_PER_PAGE);

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter portrait;
            margin: 12.7mm 4.8mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
        }

        .page {
            page-break-after: always;
            width: 100%;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        .label-grid {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .label-row {
            display: table-row;
            height: 25.4mm;
        }

        .label {
            display: table-cell;
            width: 66.7mm;
            height: 25.4mm;
            padding: 2mm;
            vertical-align: middle;
            text-align: center;
            overflow: hidden;
        }

        .label-content {
            display: inline-block;
            text-align: center;
        }

        .qr-code {
            display: inline-block;
            width: 18mm;
            height: 18mm;
        }

        .qr-code svg {
            width: 100%;
            height: 100%;
        }

        .label-text {
            margin-top: 1mm;
        }

        .item-name {
            font-weight: bold;
            font-size: 7pt;
            line-height: 1.2;
            max-height: 3em;
            overflow: hidden;
            word-wrap: break-word;
        }

        .secondary-label {
            font-size: 6pt;
            color: #333;
            margin-top: 0.5mm;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
HTML;

        foreach ($pages as $pageIndex => $pageLabels) {
            $html .= '<div class="page"><div class="label-grid">';

            $rows = array_chunk($pageLabels, self::LABELS_PER_ROW);

            foreach ($rows as $rowLabels) {
                $html .= '<div class="label-row">';

                foreach ($rowLabels as $label) {
                    $name = htmlspecialchars($this->truncateText($label['name'], 30), ENT_QUOTES, 'UTF-8');
                    $secondary = $label['secondaryLabel']
                        ? htmlspecialchars($this->truncateText($label['secondaryLabel'], 25), ENT_QUOTES, 'UTF-8')
                        : '';

                    $html .= <<<HTML
                    <div class="label">
                        <div class="label-content">
                            <div class="qr-code">{$label['qrCode']}</div>
                            <div class="label-text">
                                <div class="item-name">{$name}</div>
                                <div class="secondary-label">{$secondary}</div>
                            </div>
                        </div>
                    </div>
HTML;
                }

                // Fill remaining cells in the row if needed
                $remaining = self::LABELS_PER_ROW - count($rowLabels);
                for ($i = 0; $i < $remaining; $i++) {
                    $html .= '<div class="label"></div>';
                }

                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Truncate text to a maximum length.
     */
    protected function truncateText(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 3).'...';
    }

    /**
     * Generate the filename for the PDF.
     */
    protected function generateFilename(string $entityType): string
    {
        $timestamp = now()->format('Ymd-His');

        return "qr-codes-{$entityType}-{$timestamp}.pdf";
    }
}
