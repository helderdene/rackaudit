<?php

namespace App\Http\Requests;

use App\Models\Finding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Form request for validating evidence uploads to findings.
 *
 * Validates both file uploads and text note content. Enforces
 * file type and size restrictions (max 10MB, allowed types: images, PDFs, documents).
 * Authorization checks that user can edit the parent finding.
 */
class StoreEvidenceRequest extends FormRequest
{
    /**
     * Roles that have admin-level access to findings.
     *
     * @var array<string>
     */
    private const ADMIN_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Allowed MIME types for evidence files.
     *
     * @var array<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * Users can add evidence if they are:
     * - An Administrator or IT Manager
     * - The user assigned to the finding
     * - Assigned to the audit that the finding belongs to
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Admins and IT Managers can always add evidence
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        /** @var Finding $finding */
        $finding = $this->route('finding');

        // User is assigned to this finding
        if ($finding->assigned_to === $user->id) {
            return true;
        }

        // User is assigned to the parent audit
        if ($finding->audit && $finding->audit->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:file,text'],
            'file' => [
                'required_if:type,file',
                'file',
                'max:10240', // 10MB in kilobytes
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx',
                File::types(self::ALLOWED_MIME_TYPES),
            ],
            'content' => [
                'required_if:type,text',
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please specify the evidence type (file or text).',
            'type.in' => 'Evidence type must be either file or text.',
            'file.required_if' => 'A file is required when adding file evidence.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => 'The file size must not exceed 10MB.',
            'file.mimes' => 'Only images (jpg, png, gif), PDFs, and Word documents (doc, docx) are accepted.',
            'content.required_if' => 'Content is required when adding text evidence.',
            'content.string' => 'The content must be text.',
            'content.max' => 'The content must not exceed 10,000 characters.',
        ];
    }
}
