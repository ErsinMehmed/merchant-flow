<?php

namespace App\Nova;

use App\Nova\Cards\InlineFilters;
use Laravel\Nova\Card;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends NovaResource<TModel>
 */
abstract class Resource extends NovaResource
{
    /**
     * Every index shows its filters and page size inline instead of in Nova's
     * filter dropdown (see App\Nova\Cards\InlineFilters).
     *
     * @return list<Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [new InlineFilters(static::uriKey(), $this->filters($request), static::perPageOptions())];
    }
}
