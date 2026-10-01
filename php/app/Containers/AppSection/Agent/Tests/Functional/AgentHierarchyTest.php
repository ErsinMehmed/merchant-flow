<?php

namespace App\Containers\AppSection\Agent\Tests\Functional;

use App\Containers\AppSection\Agent\Models\Agent;
use App\Ship\Parents\Tests\TestCase;

final class AgentHierarchyTest extends TestCase
{
    public function test_detects_cycles_in_the_parent_chain(): void
    {
        $root = Agent::factory()->create();
        $child = Agent::factory()->create(['parent_agent_id' => $root->id]);
        $grandchild = Agent::factory()->create(['parent_agent_id' => $child->id]);
        $other = Agent::factory()->create();

        $this->assertTrue($root->wouldCreateCycle($root->id), 'self as parent');
        $this->assertTrue($root->wouldCreateCycle($grandchild->id), 'descendant as parent');
        $this->assertFalse($grandchild->wouldCreateCycle($root->id), 'ancestor as parent');
        $this->assertFalse($root->wouldCreateCycle($other->id));
        $this->assertFalse($root->wouldCreateCycle(null));
    }
}
