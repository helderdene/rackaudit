<?php

namespace App\Http\Controllers;

use App\Exports\Templates\CombinedTemplateExport;
use App\Exports\Templates\DatacenterTemplateExport;
use App\Exports\Templates\DeviceTemplateExport;
use App\Exports\Templates\PortTemplateExport;
use App\Exports\Templates\RackTemplateExport;
use App\Exports\Templates\RoomTemplateExport;
use App\Exports\Templates\RowTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for downloading bulk import templates.
 *
 * Provides endpoints to download XLSX templates for each entity type
 * or a combined template with all entity types in separate sheets.
 */
class TemplateDownloadController extends Controller
{
    /**
     * Roles that can download import templates.
     *
     * @var array<string>
     */
    private const ALLOWED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Map of entity types to their export classes.
     *
     * @var array<string, class-string>
     */
    private const ENTITY_EXPORTS = [
        'datacenter' => DatacenterTemplateExport::class,
        'room' => RoomTemplateExport::class,
        'row' => RowTemplateExport::class,
        'rack' => RackTemplateExport::class,
        'device' => DeviceTemplateExport::class,
        'port' => PortTemplateExport::class,
    ];

    /**
     * Download a template for a specific entity type.
     */
    public function download(string $entityType): BinaryFileResponse
    {
        $this->authorizeDownload();

        if (! array_key_exists($entityType, self::ENTITY_EXPORTS)) {
            abort(404, "Unknown entity type: {$entityType}");
        }

        $exportClass = self::ENTITY_EXPORTS[$entityType];
        $filename = "{$entityType}_import_template.xlsx";

        return Excel::download(new $exportClass, $filename);
    }

    /**
     * Download the combined template with all entity types.
     */
    public function downloadCombined(): BinaryFileResponse
    {
        $this->authorizeDownload();

        return Excel::download(
            new CombinedTemplateExport,
            'bulk_import_template.xlsx'
        );
    }

    /**
     * Authorize the current user to download templates.
     */
    private function authorizeDownload(): void
    {
        $user = request()->user();

        if (! $user || ! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403, 'You do not have permission to download import templates.');
        }
    }
}
