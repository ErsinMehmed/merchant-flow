<?php

namespace App\Containers\AppSection\Agent\Policies;

use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Agent\Models\Agent;
use App\Containers\AppSection\Authorization\Enums\Permission;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Policies\Policy as ParentPolicy;

final class AgentPolicy extends ParentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function view(User $actor, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function update(User $actor, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function delete(User $actor, Agent $agent): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function replicate(): bool
    {
        return false;
    }

    public function attachAnyAccount(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function attachAccount(User $actor, Agent $agent, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function detachAccount(User $actor, Agent $agent, Account $account): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function attachAnyUser(User $actor): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function attachUser(User $actor, Agent $agent, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageAgents) && $user->is_agent;
    }

    public function detachUser(User $actor, Agent $agent, User $user): bool
    {
        return $actor->hasPermission(Permission::ManageAgents);
    }

    public function addAgent(): bool
    {
        return false;
    }
}
