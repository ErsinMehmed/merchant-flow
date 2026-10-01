<?php

namespace App\Containers\AppSection\Charge\Tests\Unit;

use App\Containers\AppSection\Account\Enums\AccountStatus;
use App\Containers\AppSection\Account\Models\Account;
use App\Containers\AppSection\Charge\Enums\ChargeStatus;
use App\Containers\AppSection\Charge\Enums\Currency;
use App\Containers\AppSection\Charge\Enums\VoidReason;
use App\Containers\AppSection\Charge\Exceptions\ChargeOperationRejected;
use App\Containers\AppSection\Charge\Models\Charge;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Runs the cases in contracts/charge-operations.json. The Go API runs the
 * same file, which keeps both implementations of the charge rules aligned.
 */
final class ChargeOperationsContractTest extends TestCase
{
    /**
     * @param  array<string, mixed>|null  $initial
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $expect
     */
    #[DataProvider('cases')]
    public function test_operation(?array $initial, array $operation, array $expect): void
    {
        try {
            $charge = $this->apply($initial, $operation);
        } catch (ChargeOperationRejected $e) {
            $this->assertArrayHasKey('error', $expect, "Unexpected rejection: {$e->reason}");
            $this->assertSame($expect['error'], $e->reason);

            return;
        }

        $this->assertArrayNotHasKey('error', $expect, 'Expected the operation to be rejected.');
        $this->assertSame($expect['status'], $charge->status->value);
        $this->assertSame($expect['amount'], $charge->amount);
        $this->assertSame($expect['amount_captured'], $charge->amount_captured);
        $this->assertSame($expect['amount_refunded'], $charge->amount_refunded);
        $this->assertSame($expect['amount_voided'], $charge->amount_voided);
        $this->assertSame($expect['refunded'], $charge->refunded);
        if (array_key_exists('void_reason', $expect)) {
            $this->assertSame($expect['void_reason'], $charge->void_reason?->value);
        }
    }

    /**
     * @return iterable<string, array{?array<string, mixed>, array<string, mixed>, array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        $path = (getenv('CONTRACTS_PATH') ?: dirname(__DIR__, 7).'/contracts').'/charge-operations.json';
        $contract = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($contract['cases'] as $case) {
            yield $case['name'] => [$case['charge'] ?? null, $case['operation'], $case['expect']];
        }
    }

    /**
     * @param  array<string, mixed>|null  $initial
     * @param  array<string, mixed>  $operation
     */
    private function apply(?array $initial, array $operation): Charge
    {
        if ($operation['type'] === 'create') {
            $account = new Account([
                'status' => AccountStatus::from($operation['account']['status']),
                'active_yn' => $operation['account']['active_yn'],
            ]);
            $account->id = 1;
            $creator = new User;
            $creator->id = 1;

            return Charge::initiate($account, $operation['amount'], Currency::USD, 'customer@example.com', $operation['capture'], $creator);
        }

        $charge = new Charge;
        $charge->status = ChargeStatus::from($initial['status']);
        $charge->amount = $initial['amount'];
        $charge->amount_captured = $initial['amount_captured'];
        $charge->amount_refunded = $initial['amount_refunded'];
        $charge->amount_voided = $initial['amount_voided'];
        $charge->disputed = $initial['disputed'];
        $charge->refunded = $initial['status'] === ChargeStatus::Refunded->value;

        match ($operation['type']) {
            'capture' => $charge->capture(),
            'refund' => $charge->refund($operation['amount'] ?? null),
            'void' => $charge->void(VoidReason::from($operation['reason'])),
        };

        return $charge;
    }
}
