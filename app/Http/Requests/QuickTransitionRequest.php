<?php

namespace App\Http\Requests;

use App\Enums\FindingStatus;
use App\Models\Finding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuickTransitionRequest extends FormRequest
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
     * Minimum length for resolution notes when resolving a finding.
     */
    private const MIN_RESOLUTION_NOTES_LENGTH = 10;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Users can transition findings if they are:
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

        // Admins and IT Managers can always update
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
            'target_status' => ['required', Rule::enum(FindingStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateStatusTransition($validator);
            $this->validateResolutionNotes($validator);
        });
    }

    /**
     * Validate status transitions follow the workflow.
     * Admins can bypass workflow restrictions.
     */
    protected function validateStatusTransition(Validator $validator): void
    {
        $targetStatusValue = $this->input('target_status');

        if (! $targetStatusValue) {
            return;
        }

        $targetStatus = FindingStatus::tryFrom($targetStatusValue);

        if (! $targetStatus) {
            return; // Invalid status will be caught by enum rule
        }

        /** @var Finding $finding */
        $finding = $this->route('finding');
        $currentStatus = $finding->status;

        // Same status, no transition needed
        if ($currentStatus === $targetStatus) {
            return;
        }

        // Admins can bypass workflow restrictions
        if ($this->user()->hasAnyRole(self::ADMIN_ROLES)) {
            return;
        }

        // Check if transition is allowed
        if (! $currentStatus->canTransitionTo($targetStatus)) {
            $validator->errors()->add(
                'target_status',
                "Cannot transition from {$currentStatus->label()} to {$targetStatus->label()}. Please follow the standard workflow."
            );
        }
    }

    /**
     * Validate resolution notes are provided and meet minimum length when resolving.
     */
    protected function validateResolutionNotes(Validator $validator): void
    {
        $targetStatusValue = $this->input('target_status');

        if ($targetStatusValue !== FindingStatus::Resolved->value) {
            return;
        }

        /** @var Finding $finding */
        $finding = $this->route('finding');
        $notes = $this->input('notes');

        // Check if finding already has resolution notes
        $existingNotes = $finding->resolution_notes;

        // If no existing notes and no new notes, require notes
        if (empty($existingNotes) && empty($notes)) {
            $validator->errors()->add(
                'notes',
                'Resolution notes are required when resolving a finding.'
            );

            return;
        }

        // If new notes are provided, validate minimum length
        if (! empty($notes) && strlen(trim($notes)) < self::MIN_RESOLUTION_NOTES_LENGTH) {
            $validator->errors()->add(
                'notes',
                'Resolution notes must be at least '.self::MIN_RESOLUTION_NOTES_LENGTH.' characters.'
            );
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
            'target_status.required' => 'A target status is required.',
            'target_status.Illuminate\Validation\Rules\Enum' => 'Please select a valid status.',
            'notes.max' => 'Notes must not exceed 10,000 characters.',
        ];
    }
}
