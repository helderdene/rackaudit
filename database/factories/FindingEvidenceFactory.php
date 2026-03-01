<?php

namespace Database\Factories;

use App\Enums\EvidenceType;
use App\Models\Finding;
use App\Models\FindingEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating FindingEvidence test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FindingEvidence>
 */
class FindingEvidenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = FindingEvidence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(EvidenceType::cases());

        if ($type === EvidenceType::Text) {
            return [
                'finding_id' => Finding::factory(),
                'type' => $type,
                'content' => fake()->paragraph(),
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
            ];
        }

        $extension = fake()->randomElement(['png', 'jpg', 'pdf', 'docx']);
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $filename = fake()->word().'.'.$extension;

        return [
            'finding_id' => Finding::factory(),
            'type' => $type,
            'content' => null,
            'file_path' => 'finding-evidence/'.fake()->numberBetween(1, 100).'/'.$filename,
            'original_filename' => $filename,
            'mime_type' => $mimeTypes[$extension],
        ];
    }

    /**
     * Create a text evidence entry.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::Text,
            'content' => fake()->paragraph(),
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
        ]);
    }

    /**
     * Create a file evidence entry.
     */
    public function file(): static
    {
        $extension = fake()->randomElement(['png', 'jpg', 'pdf', 'docx']);
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $filename = fake()->word().'.'.$extension;

        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::File,
            'content' => null,
            'file_path' => 'finding-evidence/'.fake()->numberBetween(1, 100).'/'.$filename,
            'original_filename' => $filename,
            'mime_type' => $mimeTypes[$extension],
        ]);
    }

    /**
     * Create a PNG image evidence entry.
     */
    public function image(): static
    {
        $filename = fake()->word().'.png';

        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::File,
            'content' => null,
            'file_path' => 'finding-evidence/'.fake()->numberBetween(1, 100).'/'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'image/png',
        ]);
    }

    /**
     * Create a PDF document evidence entry.
     */
    public function pdf(): static
    {
        $filename = fake()->word().'.pdf';

        return $this->state(fn (array $attributes) => [
            'type' => EvidenceType::File,
            'content' => null,
            'file_path' => 'finding-evidence/'.fake()->numberBetween(1, 100).'/'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Create evidence for a specific finding.
     */
    public function forFinding(Finding $finding): static
    {
        return $this->state(fn (array $attributes) => [
            'finding_id' => $finding->id,
        ]);
    }
}
