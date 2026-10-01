<?php

namespace App\Containers\AppSection\Agent\Rules;

use App\Containers\AppSection\Agent\Models\Agent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps the agent hierarchy a tree: an agent cannot become its own parent or
 * the parent of one of its ancestors.
 */
final readonly class ValidParentAgent implements ValidationRule
{
    public function __construct(private Agent $agent) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->agent->wouldCreateCycle((int) $value)) {
            $fail('An agent cannot be its own parent or the parent of one of its ancestors.');
        }
    }
}
