<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TrackPro\Ft17Support\FixturePlan;

require_once dirname(__DIR__, 3).'/scripts/ft17/SafetyGuard.php';
require_once dirname(__DIR__, 3).'/scripts/ft17/FixturePlan.php';

final class Ft17FixturePlanTest extends TestCase
{
    #[Test]
    public function it_represents_exactly_the_approved_37_case_universe_as_not_executed(): void
    {
        $plan = FixturePlan::make(
            'FT17-INFRA-20260912-A',
            new DateTimeImmutable('2026-09-12', new DateTimeZone('Asia/Manila')),
        );
        $ids = array_keys($plan['formal_cases']);
        $documentedIds = $this->documentedCaseIds(
            '## 8. Edge and permission case catalog — later #17',
            '## 9. Guarded MySQL and integrity cases',
        );
        $ft15Ids = $this->documentedCaseIds(
            '## 7. Functional case catalog — later #15',
            '## 8. Edge and permission case catalog — later #17',
        );

        $this->assertCount(37, $ids);
        $this->assertCount(37, array_unique($ids));
        $this->assertSame($documentedIds, $ids);
        $this->assertSame([], array_values(array_intersect($ft15Ids, $ids)));
        $this->assertSame(['not-executed'], array_values(array_unique(array_column($plan['formal_cases'], 'status'))));
        $this->assertSame(['TC-AUTH-006', 'TC-SALES-004'], array_keys($plan['planning_gaps']));
        $this->assertStringContainsString('genuine invalid-CSRF', $plan['planning_gaps']['TC-AUTH-006']);
        $this->assertStringContainsString('explicitly prove mixed completed/voided', $plan['planning_gaps']['TC-SALES-004']);

        foreach ($ids as $id) {
            $this->assertStringNotContainsString('TC-INT-', $id);
            $this->assertStringNotContainsString('TC-REVIEW-', $id);
        }
    }

    #[Test]
    public function it_prepares_isolated_state_profiles_without_claiming_sale_void_execution(): void
    {
        $plan = FixturePlan::make(
            'FT17-INFRA-20260912-A',
            new DateTimeImmutable('2026-09-12', new DateTimeZone('Asia/Manila')),
        );

        $this->assertNull($plan['variants']['uninitialized_whole']['history_profile']);
        $this->assertNull($plan['variants']['uninitialized_fractional']['history_profile']);
        $this->assertSame(['RESTOCKED', '8.000'], $plan['variants']['restocked_whole']['history_profile']);
        $this->assertSame(['CORRECTED', '4.000'], $plan['variants']['corrected_whole']['history_profile']);
        $this->assertSame(['SOLD', '7.000'], $plan['variants']['sale_history_whole']['history_profile']);
        $this->assertSame('archived', $plan['catalog']['products']['under_archived_category']['status']);
        $this->assertSame('archived', $plan['variants']['under_archived_product']['status']);
        $this->assertSame('archived', $plan['variants']['under_archived_category']['status']);
        $this->assertLessThan(
            $plan['history_controls']['completed_sale']['created_at'],
            $plan['fixture_created_at'],
        );
        $this->assertGreaterThan(
            $plan['history_controls']['completed_sale']['created_at'],
            $plan['users']['disabled_staff']['updated_at'],
        );
        $this->assertSame('disabled_staff', $plan['history_controls']['completed_sale']['recorded_by']);
        $this->assertStringContainsString('no void workflow', $plan['history_controls']['controlled_voided_sale']['semantics']);
        $this->assertStringContainsString('SALE_VOID', $plan['history_controls']['controlled_voided_sale']['semantics']);
    }

    #[Test]
    public function its_inventory_and_money_controls_are_exact_and_nonnegative(): void
    {
        $plan = FixturePlan::make(
            'FT17-INFRA-20260912-A',
            new DateTimeImmutable('2026-09-12', new DateTimeZone('Asia/Manila')),
        );

        foreach ($plan['variants'] as $variant) {
            $this->assertMatchesRegularExpression('/\A\d+\.\d{3}\z/D', $variant['current_stock']);
            $this->assertGreaterThanOrEqual(0, bccomp($variant['current_stock'], '0.000', 3));
            if ($variant['quantity_mode'] === 'whole') {
                $this->assertStringEndsWith('.000', $variant['current_stock']);
            }
            if ($variant['status'] === 'archived') {
                $this->assertSame('0.000', $variant['current_stock']);
            }
        }

        $this->assertSame($plan['variants']['restocked_whole']['current_stock'], bcadd('3.000', '5.000', 3));
        $this->assertSame($plan['variants']['corrected_whole']['current_stock'], bcsub('6.000', '2.000', 3));

        $sale = $plan['history_controls']['completed_sale'];
        $this->assertSame($plan['variants']['sale_history_whole']['current_stock'], bcsub('10.000', $sale['quantity'], 3));
        $this->assertSame($sale['total_amount'], bcmul($sale['quantity'], $sale['unit_price'], 2));
        $this->assertSame($sale['change_amount'], bcsub($sale['cash_received'], $sale['total_amount'], 2));
    }

    #[Test]
    public function its_manifest_is_deterministic_and_discloses_no_secrets_or_tokens(): void
    {
        $plan = FixturePlan::make(
            'FT17-INFRA-20260912-A',
            new DateTimeImmutable('2026-09-12', new DateTimeZone('Asia/Manila')),
        );
        $manifest = FixturePlan::manifest($plan);
        $encoded = strtolower((string) json_encode($manifest, JSON_THROW_ON_ERROR));

        $this->assertSame($manifest, FixturePlan::manifest($plan));
        $this->assertSame(37, $manifest['formal_case_count']);
        $this->assertFalse($manifest['formal_execution_started']);
        $this->assertStringNotContainsString('password_env', $encoded);
        $this->assertStringNotContainsString('checkout_token', $encoded);
        $this->assertStringNotContainsString('trackpro_local', $encoded);
        $this->assertSame(
            'RESET-trackpro_ft17_test-FOR-FT17-INFRA:FT17-INFRA-20260912-A',
            FixturePlan::confirmationPhrase('FT17-INFRA-20260912-A'),
        );
        $this->assertTrue(FixturePlan::confirmationMatches(
            'FT17-INFRA-20260912-A',
            'RESET-trackpro_ft17_test-FOR-FT17-INFRA:FT17-INFRA-20260912-A',
        ));
        $this->assertFalse(FixturePlan::confirmationMatches(
            'FT17-INFRA-20260912-A',
            'RESET-trackpro_test-FOR-FT15:FT15-20260909-A',
        ));
    }

    #[Test]
    public function it_rejects_an_unsafe_fixture_identifier_before_confirmation_can_be_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FixturePlan::confirmationMatches('../trackpro_local', 'anything');
    }

    /** @return list<string> */
    private function documentedCaseIds(string $startHeading, string $endHeading): array
    {
        $document = file_get_contents(dirname(__DIR__, 3).'/docs/test-cases.md');
        $this->assertIsString($document);
        $afterStart = explode($startHeading, $document, 2);
        $this->assertCount(2, $afterStart);
        $beforeEnd = explode($endHeading, $afterStart[1], 2);
        $this->assertCount(2, $beforeEnd);
        $matchCount = preg_match_all('/^\| (TC-[A-Z]+-[0-9]{3}) /m', $beforeEnd[0], $matches);
        $this->assertIsInt($matchCount);

        return $matches[1];
    }
}
