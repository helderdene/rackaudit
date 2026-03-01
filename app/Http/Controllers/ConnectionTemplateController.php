<?php

namespace App\Http\Controllers;

use App\Exports\Templates\ConnectionTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for downloading connection import templates.
 *
 * Provides endpoints to download Excel and CSV templates for
 * importing expected connections from implementation files.
 */
class ConnectionTemplateController extends Controller
{
    /**
     * Roles that can download connection templates.
     *
     * @var array<string>
     */
    private const ALLOWED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Download the Excel template for connections.
     */
    public function excel(): BinaryFileResponse
    {
        $this->authorizeDownload();

        return Excel::download(
            new ConnectionTemplateExport,
            'connection_import_template.xlsx'
        );
    }

    /**
     * Download the CSV template for connections.
     */
    public function csv(): StreamedResponse
    {
        $this->authorizeDownload();

        $headers = [
            'Source Device',
            'Source Port',
            'Dest Device',
            'Dest Port',
            'Cable Type',
            'Cable Length',
        ];

        $filename = 'connection_import_template.csv';

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');

            // Write headers
            fputcsv($output, $headers);

            // Write sample data row
            fputcsv($output, [
                'Server-001',
                'eth0',
                'Switch-001',
                'port-1',
                'Cat6',
                '3.5',
            ]);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Authorize the current user to download templates.
     */
    private function authorizeDownload(): void
    {
        $user = request()->user();

        if (! $user || ! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403, 'You do not have permission to download connection templates.');
        }
    }
}
