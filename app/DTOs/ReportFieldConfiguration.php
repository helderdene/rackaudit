<?php

namespace App\DTOs;

/**
 * Data Transfer Object representing a report field configuration.
 *
 * Encapsulates the configuration for a single field/column available
 * in the custom report builder, including display metadata, data type,
 * and whether it's a calculated field.
 */
readonly class ReportFieldConfiguration
{
    /**
     * Create a new ReportFieldConfiguration instance.
     *
     * @param  string  $key  The internal field key used in queries
     * @param  string  $displayName  The user-friendly display name
     * @param  string  $category  The category for grouping fields in the UI
     * @param  bool  $isCalculated  Whether this field is calculated at runtime
     * @param  string  $dataType  The data type (string, integer, float, date, boolean)
     */
    public function __construct(
        public string $key,
        public string $displayName,
        public string $category,
        public bool $isCalculated,
        public string $dataType,
    ) {}

    /**
     * Create a ReportFieldConfiguration from an array.
     *
     * @param  array{key: string, display_name: string, category: string, is_calculated?: bool, data_type?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            displayName: $data['display_name'],
            category: $data['category'],
            isCalculated: $data['is_calculated'] ?? false,
            dataType: $data['data_type'] ?? 'string',
        );
    }

    /**
     * Convert the configuration to an array.
     *
     * @return array{key: string, display_name: string, category: string, is_calculated: bool, data_type: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'display_name' => $this->displayName,
            'category' => $this->category,
            'is_calculated' => $this->isCalculated,
            'data_type' => $this->dataType,
        ];
    }
}
