<?php

declare(strict_types=1);

namespace TrackPro\Ft15Support;

use DateTimeImmutable;
use InvalidArgumentException;

final class FixturePlan
{
    public const DATABASE = 'trackpro_test';

    public const CONNECTION = 'mysql_testing';

    public const MODE = 'functional-testing';

    public static function make(string $runId, DateTimeImmutable $asOfManila): array
    {
        self::assertRunId($runId);

        $fixtureTimestamp = $asOfManila->setTime(12, 0)->modify('-1 day')->format('Y-m-d H:i:s');

        return [
            'run_id' => $runId,
            'as_of_manila_date' => $asOfManila->format('Y-m-d'),
            'users' => [
                'admin' => [
                    'name' => 'FT15 Admin',
                    'username' => 'ft15_admin',
                    'role' => 'admin',
                    'status' => 'active',
                    'password_env' => 'FT15_ADMIN_PASSWORD',
                ],
                'staff' => [
                    'name' => 'FT15 Staff',
                    'username' => 'ft15_staff',
                    'role' => 'staff',
                    'status' => 'active',
                    'password_env' => 'FT15_STAFF_PASSWORD',
                ],
                'controlled_disabled_cashier' => [
                    'name' => 'FT15 Controlled Disabled Cashier',
                    'username' => 'ft15_controlled_disabled',
                    'role' => 'staff',
                    'status' => 'disabled',
                    'password_env' => null,
                ],
            ],
            'catalog' => [
                'main_category' => 'FT15 Fixtures',
                'main_product' => 'FT15 Sample Material',
                'lifecycle_category' => 'FT15 Lifecycle Category',
                'analytics_category' => 'FT15 Controlled Analytics',
                'analytics_product' => 'FT15 Controlled Stock Evidence',
            ],
            'variants' => [
                'whole' => [
                    'size' => 'FT15 Whole Variant',
                    'type_series' => '',
                    'thickness' => '',
                    'unit' => 'piece',
                    'quantity_mode' => 'whole',
                    'cost_price' => null,
                    'selling_price' => '125.00',
                    'current_stock' => '0.000',
                    'low_stock_threshold' => '5.000',
                    'opening_fixture' => null,
                ],
                'fractional' => [
                    'size' => 'FT15 Fractional Variant',
                    'type_series' => '',
                    'thickness' => '',
                    'unit' => 'kg',
                    'quantity_mode' => 'fractional',
                    'cost_price' => null,
                    'selling_price' => '80.00',
                    'current_stock' => '6.500',
                    'low_stock_threshold' => '2.500',
                    'opening_fixture' => [
                        'quantity' => '6.500',
                        'reason' => "FT15 CONTROLLED FIXTURE opening balance for {$runId}",
                    ],
                ],
                'controlled_low_stock' => [
                    'size' => 'FT15 Controlled Low Stock',
                    'type_series' => '',
                    'thickness' => '',
                    'unit' => 'piece',
                    'quantity_mode' => 'whole',
                    'cost_price' => null,
                    'selling_price' => '10.00',
                    'current_stock' => '1.000',
                    'low_stock_threshold' => '5.000',
                    'opening_fixture' => [
                        'quantity' => '1.000',
                        'reason' => "FT15 CONTROLLED FIXTURE low-stock evidence for {$runId}",
                    ],
                ],
                'controlled_out_of_stock' => [
                    'size' => 'FT15 Controlled Out of Stock',
                    'type_series' => '',
                    'thickness' => '',
                    'unit' => 'piece',
                    'quantity_mode' => 'whole',
                    'cost_price' => null,
                    'selling_price' => '10.00',
                    'current_stock' => '0.000',
                    'low_stock_threshold' => '0.000',
                    'opening_fixture' => [
                        'quantity' => '0.000',
                        'reason' => "FT15 CONTROLLED FIXTURE out-of-stock evidence for {$runId}",
                    ],
                ],
            ],
            'controlled_non_completed_sale' => [
                'checkout_token' => self::deterministicUuid($runId, 'controlled-non-completed-sale'),
                'status' => 'voided',
                'total_amount' => '10.00',
                'cash_received' => '10.00',
                'change_amount' => '0.00',
                'void_reason' => "FT15 CONTROLLED FIXTURE only; no void workflow was executed ({$runId}).",
                'created_at' => $fixtureTimestamp,
                'semantics' => 'Status-neutral history and completed-only analytics control; no SaleItem, SALE movement, or SALE_VOID movement is created.',
            ],
            'manual_flow' => [
                'opening_inventory' => [
                    'variant' => 'whole',
                    'quantity' => '12.000',
                    'reason' => 'Initial physical count',
                    'case' => 'TC-OI-001',
                ],
                'admin_stock_in' => [
                    'case' => 'TC-STKIN-001',
                    'whole' => ['quantity' => '5.000', 'unit_cost' => '70.00'],
                    'fractional' => ['quantity' => '2.250', 'unit_cost' => '45.50'],
                ],
                'staff_stock_in' => [
                    'case' => 'TC-STKIN-002',
                    'whole' => ['quantity' => '2.000', 'unit_cost' => '72.00'],
                ],
                'correction' => [
                    'case' => 'TC-CORR-001',
                    'variant' => 'whole',
                    'target' => '18.000',
                ],
                'sale' => [
                    'case' => 'TC-POS-001',
                    'whole' => ['quantity' => '2.000', 'unit_price' => '125.00'],
                    'fractional' => ['quantity' => '1.250', 'unit_price' => '80.00'],
                    'total' => '350.00',
                    'cash' => '400.00',
                    'change' => '50.00',
                ],
                'expected_ending_stock' => [
                    'whole' => '16.000',
                    'fractional' => '7.500',
                ],
            ],
        ];
    }

    public static function manifest(array $plan): array
    {
        return [
            'purpose' => 'Tracker #15 functional-test support only',
            'run_id' => $plan['run_id'],
            'as_of_manila_date' => $plan['as_of_manila_date'],
            'connection' => self::CONNECTION,
            'database' => self::DATABASE,
            'credential_variables' => ['FT15_ADMIN_PASSWORD', 'FT15_STAFF_PASSWORD'],
            'users' => array_map(
                static fn (array $user): array => [
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'status' => $user['status'],
                ],
                $plan['users'],
            ),
            'catalog' => $plan['catalog'],
            'variants' => array_map(
                static fn (array $variant): array => [
                    'size' => $variant['size'],
                    'unit' => $variant['unit'],
                    'quantity_mode' => $variant['quantity_mode'],
                    'selling_price' => $variant['selling_price'],
                    'current_stock' => $variant['current_stock'],
                    'low_stock_threshold' => $variant['low_stock_threshold'],
                    'state' => $variant['opening_fixture'] === null
                        ? 'reserved for manual TC-OI-001'
                        : 'controlled fixture with INITIAL_STOCK evidence',
                ],
                $plan['variants'],
            ),
            'controlled_non_completed_sale' => [
                'status' => $plan['controlled_non_completed_sale']['status'],
                'created_at' => $plan['controlled_non_completed_sale']['created_at'],
                'semantics' => $plan['controlled_non_completed_sale']['semantics'],
            ],
            'manual_flow' => $plan['manual_flow'],
        ];
    }

    public static function confirmationPhrase(string $runId): string
    {
        self::assertRunId($runId);

        return 'RESET-trackpro_test-FOR-FT15:'.$runId;
    }

    public static function assertRunId(string $runId): void
    {
        if (preg_match('/\A[A-Z0-9][A-Z0-9._-]{5,47}\z/D', $runId) !== 1) {
            throw new InvalidArgumentException('The FT15 run identifier must be 6-48 uppercase letters, digits, dots, underscores, or hyphens.');
        }
    }

    private static function deterministicUuid(string $runId, string $label): string
    {
        $hex = substr(hash('sha256', $runId.'|'.$label), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
