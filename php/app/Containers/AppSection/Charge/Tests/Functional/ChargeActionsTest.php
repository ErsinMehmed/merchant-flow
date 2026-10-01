<?php

namespace App\Containers\AppSection\Charge\Tests\Functional;

use App\Containers\AppSection\Charge\Actions\CaptureChargeAction;
use App\Containers\AppSection\Charge\Actions\RefundChargeAction;
use App\Containers\AppSection\Charge\Actions\VoidChargeAction;
use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Ship\Parents\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ChargeActionsTest extends TestCase
{
    public function test_partial_refunds_accumulate_until_the_charge_is_fully_refunded(): void
    {
        $charge = Charge::factory()->create(['amount' => 10_000, 'amount_captured' => 10_000]);
        $refund = app(RefundChargeAction::class);

        $refund->run($charge->id, 2_500);
        $refund->run($charge->id, 2_500);
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'captured',
            'amount_refunded' => 5_000,
            'refunded' => false,
        ]);

        $refund->run($charge->id);
        $this->assertDatabaseHas('charges', [
            'id' => $charge->id,
            'status' => 'refunded',
            'amount_refunded' => 10_000,
            'refunded' => true,
        ]);
    }

    public function test_a_rejected_refund_leaves_the_charge_unchanged(): void
    {
        $charge = Charge::factory()->partiallyRefunded(4_000)->create(['amount' => 5_000, 'amount_captured' => 5_000]);

        try {
            app(RefundChargeAction::class)->run($charge->id, 1_001);
            $this->fail('A refund above the remaining amount must be rejected.');
        } catch (ChargeOperationRejected $e) {
            $this->assertSame('refund_exceeds_available', $e->reason);
        }

        $this->assertSame(4_000, $charge->fresh()?->amount_refunded);
    }

    public function test_capture_and_void_persist(): void
    {
        $toCapture = Charge::factory()->authorized()->create(['amount' => 3_000]);
        $toVoid = Charge::factory()->authorized()->create(['amount' => 4_000]);

        app(CaptureChargeAction::class)->run($toCapture->id);
        app(VoidChargeAction::class)->run($toVoid->id, VoidReason::Fraudulent);

        $this->assertDatabaseHas('charges', ['id' => $toCapture->id, 'status' => 'captured', 'amount_captured' => 3_000]);
        $this->assertDatabaseHas('charges', [
            'id' => $toVoid->id,
            'status' => 'voided',
            'amount_voided' => 4_000,
            'void_reason' => 'fraudulent',
        ]);
    }

    public function test_voiding_twice_is_rejected(): void
    {
        $charge = Charge::factory()->authorized()->create();
        $void = app(VoidChargeAction::class);
        $void->run($charge->id, VoidReason::Duplicate);

        $this->expectExceptionObject(ChargeOperationRejected::notVoidable(ChargeStatus::Voided));
        $void->run($charge->id, VoidReason::Duplicate);
    }

    /**
     * The checks hold even for writes that bypass the model, such as a bug in
     * another service sharing the database.
     */
    public function test_database_rejects_amounts_that_break_the_invariants(): void
    {
        $charge = Charge::factory()->create(['amount' => 1_000, 'amount_captured' => 1_000]);

        $this->assertRejectedByDatabase(fn () => DB::table('charges')->where('id', $charge->id)->update(['amount_refunded' => 1_001]));
        $this->assertRejectedByDatabase(fn () => DB::table('charges')->where('id', $charge->id)->update(['status' => 'pending']));
        $this->assertRejectedByDatabase(fn () => DB::table('charges')->where('id', $charge->id)->update(['refunded' => true]));
        $this->assertRejectedByDatabase(fn () => DB::table('charges')->where('id', $charge->id)->update(['void_reason' => 'whim']));
    }

    private function assertRejectedByDatabase(callable $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('The database accepted a row that breaks a charge invariant.');
        } catch (QueryException $e) {
            // MySQL error 3819: check constraint violated.
            $this->assertSame(3819, $e->errorInfo[1] ?? null, $e->getMessage());
        }
    }
}
