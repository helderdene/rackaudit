<?php

namespace App\Http\Requests;

use App\Models\Port;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Form request for pairing ports on patch panels.
 *
 * Validates that the paired port belongs to the same device,
 * neither port is already paired, and a port cannot be paired with itself.
 */
class PairPortRequest extends FormRequest
{
    /**
     * Roles that can pair ports on devices.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only users who can update devices can pair ports.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        return $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paired_port_id' => ['required', 'integer', 'exists:ports,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateSameDevice($validator);
            $this->validateNotSelf($validator);
            $this->validateNotAlreadyPaired($validator);
        });
    }

    /**
     * Validate that the paired port belongs to the same device.
     */
    private function validateSameDevice(Validator $validator): void
    {
        $pairedPortId = $this->input('paired_port_id');

        if (! $pairedPortId) {
            return;
        }

        $port = $this->route('port');
        $pairedPort = Port::find($pairedPortId);

        if (! $pairedPort) {
            return;
        }

        if ($port->device_id !== $pairedPort->device_id) {
            $validator->errors()->add(
                'paired_port_id',
                'Ports can only be paired within the same device.'
            );
        }
    }

    /**
     * Validate that a port is not being paired with itself.
     */
    private function validateNotSelf(Validator $validator): void
    {
        $pairedPortId = $this->input('paired_port_id');

        if (! $pairedPortId) {
            return;
        }

        $port = $this->route('port');

        if ($port->id === (int) $pairedPortId) {
            $validator->errors()->add(
                'paired_port_id',
                'A port cannot be paired with itself.'
            );
        }
    }

    /**
     * Validate that neither port is already paired.
     */
    private function validateNotAlreadyPaired(Validator $validator): void
    {
        $pairedPortId = $this->input('paired_port_id');

        if (! $pairedPortId) {
            return;
        }

        $port = $this->route('port');
        $pairedPort = Port::find($pairedPortId);

        if (! $pairedPort) {
            return;
        }

        // Check if the current port is already paired
        if ($port->paired_port_id !== null) {
            $validator->errors()->add(
                'paired_port_id',
                'This port is already paired with another port.'
            );

            return;
        }

        // Check if the target port is already paired
        if ($pairedPort->paired_port_id !== null) {
            $validator->errors()->add(
                'paired_port_id',
                'The selected port is already paired with another port.'
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
            'paired_port_id.required' => 'The paired port is required.',
            'paired_port_id.integer' => 'The paired port ID must be an integer.',
            'paired_port_id.exists' => 'The selected paired port does not exist.',
        ];
    }
}
