<?php

namespace App\Http\Controllers;

use App\Actions\ExpectedConnections\ParseConnectionsAction;
use App\Models\ImplementationFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for parsing implementation files to extract expected connections.
 *
 * Provides API endpoint for initiating the parsing process on approved
 * Excel/CSV implementation files.
 */
class ParseConnectionsController extends Controller
{
    /**
     * Roles that can parse implementation files.
     *
     * @var array<string>
     */
    private const ALLOWED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Parse connections from an implementation file.
     */
    public function parse(Request $request, ImplementationFile $implementationFile): JsonResponse
    {
        // Authorize the user
        $this->authorizeAccess();

        // Execute the parsing action
        $action = new ParseConnectionsAction;
        $result = $action->execute($implementationFile);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to parse file.',
                'parse_errors' => $result['parse_errors'] ?? [],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'File parsed successfully.',
            'data' => [
                'connections' => $result['connections'],
                'statistics' => $result['statistics'],
                'row_errors' => $result['row_errors'] ?? [],
            ],
        ]);
    }

    /**
     * Authorize the current user to parse files.
     */
    private function authorizeAccess(): void
    {
        $user = request()->user();

        if (! $user || ! $user->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403, 'You do not have permission to parse implementation files.');
        }
    }
}
