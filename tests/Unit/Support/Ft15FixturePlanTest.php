<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TrackPro\Ft15Support\FixturePlan;

require_once dirname(__DIR__, 3).'/scripts/ft15/FixturePlan.php';

class Ft15FixturePlanTest extends TestCase
{
    #[Test]
    public function it_builds_the_approved_operational_flow_without_consuming_manual_opening_inventory(): void
    {
        $plan = FixturePlan::make(
            'FT15-20260909-A',
            new DateTimeImmutable('2026-09-09 09:00:00', new DateTimeZone('Asia/Manila')),
        );

        $this->assertSame('FT15 Fixtures', $plan['catalog']['main_category']);
        $this->assertSame('FT15 Sample Material', $plan['catalog']['main_product']);
        $this->assertSame('piece', $plan['variants']['whole']['unit']);
        $this->assertSame('whole', $plan['variants']['whole']['quantity_mode']);
        $this->assertSame('125.00', $plan['variants']['whole']['selling_price']);
        $this->assertSame('0.000', $plan['variants']['whole']['current_stock']);
        $this->assertNull($plan['variants']['whole']['opening_fixture']);
        $this->assertSame('kg', $plan['variants']['fractional']['unit']);
        $this->assertSame('fractional', $plan['variants']['fractional']['quantity_mode']);
        $this->assertSame('80.00', $plan['variants']['fractional']['selling_price']);
        $this->assertSame('6.500', $plan['variants']['fractional']['opening_fixture']['quantity']);
        $this->assertSame('12.000', $plan['manual_flow']['opening_inventory']['quantity']);
        $this->assertSame('350.00', $plan['manual_flow']['sale']['total']);
        $this->assertSame('50.00', $plan['manual_flow']['sale']['change']);
        $this->assertSame(
            ['whole' => '16.000', 'fractional' => '7.500'],
            $plan['manual_flow']['expected_ending_stock'],
        );

        $wholeAfterStockIn = bcadd(bcadd('12.000', '5.000', 3), '2.000', 3);
        $this->assertSame('16.000', bcsub('18.000', '2.000', 3));
        $this->assertSame('19.000', $wholeAfterStockIn);
        $fractionalAfterStockIn = bcadd('6.500', '2.250', 3);
        $this->assertSame('7.500', bcsub($fractionalAfterStockIn, '1.250', 3));
        $this->assertSame(
            '350.00',
            bcadd(bcmul('2.000', '125.00', 2), bcmul('1.250', '80.00', 2), 2),
        );
    }

    #[Test]
    public function it_keeps_controlled_history_distinct_from_sale_void_workflow_evidence(): void
    {
        $plan = FixturePlan::make(
            'FT15-20260909-A',
            new DateTimeImmutable('2026-09-09 09:00:00', new DateTimeZone('Asia/Manila')),
        );
        $control = $plan['controlled_non_completed_sale'];

        $this->assertSame('voided', $control['status']);
        $this->assertStringContainsString('CONTROLLED FIXTURE', $control['void_reason']);
        $this->assertStringContainsString('no void workflow was executed', $control['void_reason']);
        $this->assertStringContainsString('no SaleItem', $control['semantics']);
        $this->assertStringContainsString(
            'no SaleItem, SALE movement, or SALE_VOID movement is created.',
            $control['semantics'],
        );
        $this->assertSame('2026-09-08 12:00:00', $control['created_at']);
    }

    #[Test]
    public function its_manifest_is_deterministic_and_excludes_secrets_tokens_and_protected_data_names(): void
    {
        $plan = FixturePlan::make(
            'FT15-20260909-A',
            new DateTimeImmutable('2026-09-09', new DateTimeZone('Asia/Manila')),
        );
        $manifest = FixturePlan::manifest($plan);
        $encoded = strtolower((string) json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->assertSame($manifest, FixturePlan::manifest($plan));
        $this->assertStringNotContainsString('checkout_token', $encoded);
        $this->assertStringNotContainsString('password_env', $encoded);
        $this->assertStringNotContainsString('trackpro_local', $encoded);
        $this->assertStringNotContainsString('test hammer', $encoded);
        $this->assertStringNotContainsString('sale_void workflow is implemented', $encoded);
        $this->assertSame(
            'RESET-trackpro_test-FOR-FT15:FT15-20260909-A',
            FixturePlan::confirmationPhrase('FT15-20260909-A'),
        );
    }

    #[Test]
    public function it_rejects_ambiguous_or_untraceable_run_identifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FixturePlan::make(
            'unsafe run id',
            new DateTimeImmutable('2026-09-09', new DateTimeZone('Asia/Manila')),
        );
    }
}
