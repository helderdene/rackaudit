<?php

namespace App\Exports\Templates;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Connection template export for expected connections parsing.
 *
 * Generates an XLSX template with two sheets:
 * 1. Data sheet - for entering connection data with columns: Source Device, Source Port, Dest Device, Dest Port, Cable Type, Cable Length
 * 2. Instructions sheet - with column descriptions and sample data
 */
class ConnectionTemplateExport implements WithMultipleSheets
{
    /**
     * Get the column headers for the connection template.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'Source Device',
            'Source Port',
            'Dest Device',
            'Dest Port',
            'Cable Type',
            'Cable Length',
        ];
    }

    /**
     * Get the sheets for the combined template.
     *
     * @return array<ConnectionDataSheet|ConnectionInstructionsSheet>
     */
    public function sheets(): array
    {
        return [
            new ConnectionDataSheet,
            new ConnectionInstructionsSheet,
        ];
    }
}
