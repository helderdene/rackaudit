<?php

namespace App\Http\Requests;

use App\Enums\DiscrepancyStatus;
use App\Models\Discrepancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Form request for importing discrepancies as audit verification items.
 *
 * Validates that:
 * - Selected discrepancy IDs are valid
 * - Discrepancies are in the correct scope (datacenter/room)
 * - Discrepancies are not already in_audit status
 */
class ImportDiscrepanciesRequest extends FormRequest
{
    /**
     * Roles that can import discrepancies into audits.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
        'Auditor',
    ];

    /**
     * Determine if the user is authorized to make this request.
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
            'discrepancy_ids' => ['required', 'array', 'min:1'],
            'discrepancy_ids.*' => ['integer', 'exists:discrepancies,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateDiscrepanciesNotInAudit($validator);
            $this->validateDiscrepanciesInScope($validator);
        });
    }

    /**
     * Validate that discrepancies are not already in an audit.
     */
    protected function validateDiscrepanciesNotInAudit(Validator $validator): void
    {
        $discrepancyIds = $this->input('discrepancy_ids', []);

        if (empty($discrepancyIds)) {
            return;
        }

        $inAuditCount = Discrepancy::whereIn('id', $discrepancyIds)
            ->where('status', DiscrepancyStatus::InAudit)
            ->count();

        if ($inAuditCount > 0) {
            $validator->errors()->add(
                'discrepancy_ids',
                'One or more selected discrepancies are already imported into an audit.'
            );
        }
    }

    /**
     * Validate that discrepancies are in the correct scope for the audit.
     */
    protected function validateDiscrepanciesInScope(Validator $validator): void
    {
        $audit = $this->route('audit');
        $discrepancyIds = $this->input('discrepancy_ids', []);

        if (! $audit || empty($discrepancyIds)) {
            return;
        }

        // Check datacenter scope
        $invalidDatacenterCount = Discrepancy::whereIn('id', $discrepancyIds)
            ->where('datacenter_id', '!=', $audit->datacenter_id)
            ->count();

        if ($invalidDatacenterCount > 0) {
            $validator->errors()->add(
                'discrepancy_ids',
                'One or more selected discrepancies are not in the same datacenter as the audit.'
            );

            return;
        }

        // If audit has room scope, validate room
        if ($audit->room_id !== null) {
            $invalidRoomCount = Discrepancy::whereIn('id', $discrepancyIds)
                ->where(function ($query) use ($audit) {
                    $query->whereNull('room_id')
                        ->orWhere('room_id', '!=', $audit->room_id);
                })
                ->count();

            if ($invalidRoomCount > 0) {
                $validator->errors()->add(
                    'discrepancy_ids',
                    'One or more selected discrepancies are not in the same room as the audit scope.'
                );
            }
        }
    }

    /**
     * Get the validated discrepancy IDs.
     *
     * @return array<int>
     */
    public function getDiscrepancyIds(): array
    {
        return array_map('intval', $this->validated('discrepancy_ids'));
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discrepancy_ids.required' => 'Please select at least one discrepancy to import.',
            'discrepancy_ids.array' => 'The discrepancy selection must be a list.',
            'discrepancy_ids.min' => 'Please select at least one discrepancy to import.',
            'discrepancy_ids.*.integer' => 'Invalid discrepancy ID format.',
            'discrepancy_ids.*.exists' => 'One or more selected discrepancies do not exist.',
        ];
    }
}
