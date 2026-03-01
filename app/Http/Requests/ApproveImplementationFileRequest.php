<?php

namespace App\Http\Requests;

use App\Models\ImplementationFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Form request for validating implementation file approval operations.
 *
 * Validates that the file is in "pending_approval" status and can be approved.
 * Authorization is handled via the ImplementationFilePolicy approve method.
 */
class ApproveImplementationFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Uses the ImplementationFilePolicy approve method for authorization.
     */
    public function authorize(): bool
    {
        $implementationFile = $this->route('implementation_file');

        if (! $implementationFile instanceof ImplementationFile) {
            return false;
        }

        return Gate::allows('approve', $implementationFile);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $this->validateFileIsPendingApproval($validator);
        });
    }

    /**
     * Validate that the file is in pending_approval status.
     */
    private function validateFileIsPendingApproval(\Illuminate\Validation\Validator $validator): void
    {
        $implementationFile = $this->route('implementation_file');

        if (! $implementationFile instanceof ImplementationFile) {
            $validator->errors()->add('file', 'The specified file does not exist.');

            return;
        }

        if ($implementationFile->isApproved()) {
            $validator->errors()->add('file', 'This file has already been approved.');
        }
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'The file to approve is required.',
        ];
    }
}
