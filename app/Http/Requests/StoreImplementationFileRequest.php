<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Form request for validating implementation file uploads.
 *
 * Validates the uploaded file format, size (max 10MB), and optional description.
 * Server-side MIME type validation is performed in addition to extension validation.
 */
class StoreImplementationFileRequest extends FormRequest
{
    /**
     * Roles that can upload implementation files.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Allowed MIME types for implementation files.
     *
     * @var array<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can upload implementation files.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,xlsx,xls,csv,docx,txt',
                'max:10240', // 10MB in kilobytes
                File::types(self::ALLOWED_MIME_TYPES), // Server-side MIME type validation
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
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
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'Only PDF, Excel (xlsx, xls), CSV, Word (docx), and text files are accepted.',
            'file.max' => 'The file size must not exceed 10MB.',
            'description.string' => 'The description must be text.',
            'description.max' => 'The description must not exceed 500 characters.',
        ];
    }
}
