<?php

namespace App\Exports;

use App\Exports\Templates\AbstractTemplateExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Abstract base class for data exports.
 *
 * Extends AbstractTemplateExport to reuse styling and column definitions,
 * but provides actual entity data instead of example template data.
 * Subclasses must implement the query() and transformRow() methods.
 */
abstract class AbstractDataExport extends AbstractTemplateExport implements FromCollection
{
    /**
     * Filters to apply to the export query.
     *
     * @var array<string, mixed>
     */
    protected array $filters = [];

    /**
     * Create a new export instance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Get the collection of data to export.
     *
     * @return Collection<int, array<mixed>>
     */
    public function collection(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn ($record) => $this->transformRow($record));
    }

    /**
     * Get the query builder for the export data.
     *
     * Subclasses should implement this to return a query builder
     * with appropriate eager loading and filters applied.
     */
    abstract protected function query(): \Illuminate\Database\Eloquent\Builder;

    /**
     * Transform a single model record to an array row matching the headings.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $record
     * @return array<mixed>
     */
    abstract protected function transformRow($record): array;

    /**
     * Get the example data row(s) for this export.
     *
     * For data exports, this returns an empty array since we use collection() instead.
     * This method is required by AbstractTemplateExport but is not used for data exports.
     *
     * @return array<int, array<mixed>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * Get the filters applied to this export.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
