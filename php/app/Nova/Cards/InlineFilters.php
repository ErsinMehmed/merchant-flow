<?php

namespace App\Nova\Cards;

use Laravel\Nova\Card;
use Laravel\Nova\Filters\DateFilter;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Shows a resource's filters permanently under the search box and its page
 * size next to the pagination, replacing Nova's filter dropdown. It writes
 * the URL parameters Nova itself reads, so filtering is still done by the
 * filter classes on the server.
 *
 * Rendered by resources/nova/inline-filters.js.
 */
final class InlineFilters extends Card
{
    public $width = self::FULL_WIDTH;

    public $height = self::DYNAMIC_HEIGHT;

    public $component = 'inline-filters';

    /**
     * @param  list<Filter>  $filters
     * @param  list<int>  $perPageOptions
     */
    public function __construct(
        private readonly string $resourceKey,
        private readonly array $filters,
        private readonly array $perPageOptions,
    ) {
        parent::__construct();
    }

    public function jsonSerialize(): array
    {
        $request = app(NovaRequest::class);

        return array_merge(parent::jsonSerialize(), [
            'resource' => $this->resourceKey,
            'perPageOptions' => $this->perPageOptions,
            'filters' => array_map(static fn (Filter $filter): array => [
                'key' => $filter->key(),
                'name' => (string) $filter->name(),
                'type' => $filter instanceof DateFilter ? 'date' : 'select',
                'options' => $filter instanceof DateFilter ? [] : self::options($filter->options($request)),
            ], $this->filters),
        ]);
    }

    /**
     * @param  iterable<string, mixed>  $options  label => value, as Nova filters return them
     * @return list<array{label: string, value: string}>
     */
    private static function options(iterable $options): array
    {
        $list = [];
        foreach ($options as $label => $value) {
            $list[] = ['label' => (string) $label, 'value' => (string) $value];
        }

        return $list;
    }
}
