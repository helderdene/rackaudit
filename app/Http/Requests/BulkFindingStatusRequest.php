<?php

namespace App\Http\Requests;

use App\Enums\FindingStatus;
use App\Models\Finding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkFindingStatusRequest extends FormRequest
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
     * Determine if the user is authorized to make this request.
     *
     * User must have access to all selected findings.
     * Admins and IT Managers can always change status.
     * Other users must be assigned to the audit of each finding.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Admins and IT Managers can always bulk change status
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }

        // For non-admins, check access to each finding
        $findingIds = $this->input('finding_ids', []);

        if (empty($findingIds)) {
            return true; // Will fail validation anyway
        }

        // Get all findings and check if user has access to each
        $findings = Finding::with('audit.assignees')->whereIn('id', $findingIds)->get();

        foreach ($findings as $finding) {
            // User is assigned to this finding
            if ($finding->assigned_to === $user->id) {
                continue;
            }

            // User is assigned to the parent audit
            if ($finding->audit && $finding->audit->assignees->contains('id', $user->id)) {
                continue;
            }

            // User doesn't have access to this finding
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'finding_ids' => ['required', 'array', 'min:1'],
            'finding_ids.*' => ['integer', 'exists:findings,id'],
            'status' => ['required', Rule::enum(FindingStatus::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateWorkflowRules($validator);
        });
    }

    /**
     * Validate workflow rules apply to all selected findings.
     * Admins can bypass workflow restrictions.
     */
    protected function validateWorkflowRules(Validator $validator): void
    {
        $user = $this->user();

        // Admins can bypass workflow restrictions
        if ($user && $user->hasAnyRole(self::ADMIN_ROLES)) {
            return;
        }

        $targetStatusValue = $this->input('status');
        $targetStatus = FindingStatus::tryFrom($targetStatusValue);

        if (! $targetStatus) {
            return; // Invalid status will be caught by enum rule
        }

        $findingIds = $this->input('finding_ids', []);

        if (empty($findingIds)) {
            return;
        }

        $findings = Finding::whereIn('id', $findingIds)->get();
        $invalidFindings = [];

        foreach ($findings as $finding) {
            // Skip if same status
            if ($finding->status === $targetStatus) {
                continue;
            }

            // Check if transition is allowed
            if (! $finding->status->canTransitionTo($targetStatus)) {
                $invalidFindings[] = $finding->title;
            }
        }

        if (! empty($invalidFindings)) {
            $validator->errors()->add(
                'status',
                'The following findings cannot be transitioned to '.$targetStatus->label().': '.implode(', ', array_slice($invalidFindings, 0, 3)).(count($invalidFindings) > 3 ? ' and '.(count($invalidFindings) - 3).' more' : '')
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
            'finding_ids.required' => 'At least one finding must be selected.',
            'finding_ids.array' => 'Finding IDs must be an array.',
            'finding_ids.min' => 'At least one finding must be selected.',
            'finding_ids.*.exists' => 'One or more selected findings do not exist.',
            'status.required' => 'A status must be specified.',
            'status.Illuminate\Validation\Rules\Enum' => 'Please select a valid status.',
        ];
    }
}
