<?php

namespace App\Http\Requests;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Models\Finding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFindingRequest extends FormRequest
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
     * Users can update findings if they are:
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
            'status' => ['sometimes', Rule::enum(FindingStatus::class)],
            'severity' => ['sometimes', Rule::enum(FindingSeverity::class)],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'finding_category_id' => ['sometimes', 'nullable', 'exists:finding_categories,id'],
            'resolution_notes' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateResolutionNotesRequiredForResolved($validator);
            $this->validateResolutionNotesMinimumLength($validator);
            $this->validateStatusTransition($validator);
        });
    }

    /**
     * Validate that resolution_notes is required when status changes to Resolved.
     */
    protected function validateResolutionNotesRequiredForResolved(Validator $validator): void
    {
        $newStatus = $this->input('status');

        if ($newStatus !== FindingStatus::Resolved->value) {
            return;
        }

        /** @var Finding $finding */
        $finding = $this->route('finding');

        // Resolution notes are required either from the request or already on the finding
        $resolutionNotes = $this->input('resolution_notes') ?? $finding->resolution_notes;

        if (empty($resolutionNotes)) {
            $validator->errors()->add(
                'resolution_notes',
                'Resolution notes are required when marking a finding as resolved.'
            );
        }
    }

    /**
     * Validate minimum length for resolution notes when resolving.
     *
     * Only enforces minimum length when:
     * 1. Transitioning to Resolved status
     * 2. AND providing new resolution notes in this request
     *
     * Existing resolution notes that are shorter are grandfathered in.
     */
    protected function validateResolutionNotesMinimumLength(Validator $validator): void
    {
        $newStatus = $this->input('status');
        $newResolutionNotes = $this->input('resolution_notes');

        // Only validate when transitioning to Resolved
        if ($newStatus !== FindingStatus::Resolved->value) {
            return;
        }

        // If no new notes provided in request, skip (allows grandfathered notes)
        if ($newResolutionNotes === null) {
            return;
        }

        /** @var Finding $finding */
        $finding = $this->route('finding');

        // If finding already had resolution notes, and they're not being changed, skip
        if ($finding->resolution_notes !== null && $newResolutionNotes === $finding->resolution_notes) {
            return;
        }

        // Validate minimum length for new resolution notes
        if (strlen(trim($newResolutionNotes)) < self::MIN_RESOLUTION_NOTES_LENGTH) {
            $validator->errors()->add(
                'resolution_notes',
                'Resolution notes must be at least '.self::MIN_RESOLUTION_NOTES_LENGTH.' characters when resolving a finding.'
            );
        }
    }

    /**
     * Validate status transitions follow the workflow.
     * Admins can bypass workflow restrictions.
     */
    protected function validateStatusTransition(Validator $validator): void
    {
        $newStatusValue = $this->input('status');

        if (! $newStatusValue) {
            return;
        }

        $newStatus = FindingStatus::tryFrom($newStatusValue);

        if (! $newStatus) {
            return; // Invalid status will be caught by enum rule
        }

        /** @var Finding $finding */
        $finding = $this->route('finding');
        $currentStatus = $finding->status;

        // Same status, no transition needed
        if ($currentStatus === $newStatus) {
            return;
        }

        // Admins can bypass workflow restrictions
        if ($this->user()->hasAnyRole(self::ADMIN_ROLES)) {
            return;
        }

        // Check if transition is allowed
        if (! $currentStatus->canTransitionTo($newStatus)) {
            $validator->errors()->add(
                'status',
                "Cannot transition from {$currentStatus->label()} to {$newStatus->label()}. Please follow the standard workflow."
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
            'status.Illuminate\Validation\Rules\Enum' => 'Please select a valid status.',
            'severity.Illuminate\Validation\Rules\Enum' => 'Please select a valid severity level.',
            'assigned_to.exists' => 'The selected assignee does not exist.',
            'finding_category_id.exists' => 'The selected category does not exist.',
            'resolution_notes.max' => 'Resolution notes must not exceed 10,000 characters.',
            'due_date.date' => 'The due date must be a valid date.',
            'due_date.after_or_equal' => 'The due date must be today or a future date.',
        ];
    }
}
