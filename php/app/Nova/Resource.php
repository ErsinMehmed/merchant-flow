<?php

namespace App\Nova;

use App\Nova\Cards\InlineFilters;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

abstract class Resource extends NovaResource
{
    /**
     * Every index shows its filters and page size inline instead of in Nova's
     * filter dropdown (see App\Nova\Cards\InlineFilters).
     */
    public function cards(NovaRequest $request): array
    {
        return [new InlineFilters(static::uriKey(), $this->filters($request), static::perPageOptions())];
    }
}
