<?php

namespace App\Http\Requests;

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Form request for placing a device in a rack.
 *
 * Validates placement parameters and includes collision detection
 * to prevent overlapping devices at the same U positions.
 */
class PlaceDeviceRequest extends FormRequest
{
    /**
     * Roles that can place devices.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
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
            'rack_id' => ['required', 'exists:racks,id'],
            'start_u' => ['required', 'integer', 'min:1', 'max:48'],
            'face' => ['required', Rule::enum(DeviceRackFace::class)],
            'width_type' => ['required', Rule::enum(DeviceWidthType::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validateNoCollision($validator);
            }
        });
    }

    /**
     * Validate that placing the device does not cause a collision with existing devices.
     */
    private function validateNoCollision(Validator $validator): void
    {
        $device = $this->route('device');

        if (! $device instanceof Device) {
            return;
        }

        $rackId = $this->input('rack_id');
        $startU = (int) $this->input('start_u');
        $face = $this->input('face');
        $widthType = $this->input('width_type');
        $uHeight = $device->u_height;

        // Calculate the U positions this device would occupy
        $endU = $startU + $uHeight - 1;

        // Check for collisions with existing devices in the same rack
        $collidingDevices = Device::query()
            ->where('rack_id', $rackId)
            ->where('id', '!=', $device->id)
            ->where('rack_face', $face)
            ->whereNotNull('start_u')
            ->get()
            ->filter(function (Device $existingDevice) use ($startU, $endU, $widthType) {
                // Calculate existing device's U range
                $existingStartU = $existingDevice->start_u;
                $existingEndU = $existingStartU + $existingDevice->u_height - 1;

                // Check if U ranges overlap
                $uOverlap = $startU <= $existingEndU && $endU >= $existingStartU;

                if (! $uOverlap) {
                    return false;
                }

                // Check width compatibility (half-width devices can share a U position)
                $existingWidth = $existingDevice->width_type?->value;

                // Full width always collides with anything at same U
                if ($widthType === 'full' || $existingWidth === 'full') {
                    return true;
                }

                // Half-left collides with half-left, half-right collides with half-right
                if ($widthType === $existingWidth) {
                    return true;
                }

                // half_left and half_right can coexist
                return false;
            });

        if ($collidingDevices->isNotEmpty()) {
            $validator->errors()->add(
                'start_u',
                'This position is already occupied by another device. Please choose a different position.'
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
            'rack_id.required' => 'A rack must be specified for device placement.',
            'rack_id.exists' => 'The selected rack does not exist.',
            'start_u.required' => 'The starting U position is required.',
            'start_u.integer' => 'The starting U position must be an integer.',
            'start_u.min' => 'The starting U position must be at least 1.',
            'start_u.max' => 'The starting U position must not exceed 48.',
            'face.required' => 'The rack face is required.',
            'face.Illuminate\Validation\Rules\Enum' => 'The selected rack face is invalid.',
            'width_type.required' => 'The width type is required.',
            'width_type.Illuminate\Validation\Rules\Enum' => 'The selected width type is invalid.',
        ];
    }
}
