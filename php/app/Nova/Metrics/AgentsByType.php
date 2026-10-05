<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Agent\Enums\AgentType;
use App\Containers\AppSection\Agent\Models\Agent;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

final class AgentsByType extends Partition
{
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count($request, Agent::class, 'type')
            ->label(static fn (mixed $value): string => AgentType::tryFrom((string) $value)?->label() ?? (string) $value);
    }
}
