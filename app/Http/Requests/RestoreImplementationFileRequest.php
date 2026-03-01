<?php

namespace App\Http\Requests;

use App\Models\ImplementationFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Form request for validating implementation file version restore operations.
 *
 * Validates that the target file exists in storage and can be restored.
 * Authorization is handled via the ImplementationFilePolicy restore method.
 */
class RestoreImplementationFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Uses the ImplementationFilePolicy restore method for authorization.
     */
    public function authorize(): bool
    {
        $implementationFile = $this->route('implementation_file');

        if (! $implementationFile instanceof ImplementationFile) {
            return false;
        }

        return Gate::allows('restore', $implementationFile);
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
            $this->validateFileExistsInStorage($validator);
            $this->validateFileBelongsToVersionGroup($validator);
        });
    }

    /**
     * Validate that the source file exists in storage.
     */
    private function validateFileExistsInStorage(\Illuminate\Validation\Validator $validator): void
    {
        $implementationFile = $this->route('implementation_file');

        if (! $implementationFile instanceof ImplementationFile) {
            $validator->errors()->add('file', 'The specified file does not exist.');

            return;
        }

        if (! Storage::disk('local')->exists($implementationFile->file_path)) {
            $validator->errors()->add('file', 'The source file is missing from storage and cannot be restored.');
        }
    }

    /**
     * Validate that the file belongs to a version group.
     */
    private function validateFileBelongsToVersionGroup(\Illuminate\Validation\Validator $validator): void
    {
        $implementationFile = $this->route('implementation_file');

        if (! $implementationFile instanceof ImplementationFile) {
            return;
        }

        if ($implementationFile->version_group_id === null) {
            $validator->errors()->add('file', 'The file does not belong to a version group and cannot be restored.');
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
            'file.required' => 'The file to restore is required.',
        ];
    }
}
