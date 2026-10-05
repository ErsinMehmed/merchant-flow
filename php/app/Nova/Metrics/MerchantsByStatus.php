<?php

namespace App\Nova\Metrics;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use App\Containers\AppSection\Account\Models\Account;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

final class MerchantsByStatus extends Partition
{
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count($request, Account::class, 'status')
            ->label(static fn (mixed $value): string => AccountStatus::options()[$value] ?? (string) $value)
            ->colors([
                'Active' => '#22c55e',
                'Pending' => '#f59e0b',
                'Suspended' => '#ef4444',
                'Closed' => '#94a3b8',
            ]);
    }
}
