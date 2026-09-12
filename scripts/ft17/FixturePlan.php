<?php

declare(strict_types=1);

namespace TrackPro\Ft17Support;

use DateTimeImmutable;
use InvalidArgumentException;

final class FixturePlan
{
    /** @var array<string, list<string>> */
    public const FORMAL_CASES = [
        'auth' => ['TC-AUTH-003', 'TC-AUTH-004', 'TC-AUTH-005', 'TC-AUTH-006'],
        'catalog' => [
            'TC-CAT-003', 'TC-PROD-002', 'TC-VAR-004', 'TC-CAT-004',
            'TC-VAR-005', 'TC-VAR-006', 'TC-CAT-005', 'TC-VAR-007',
        ],
        'opening_inventory' => ['TC-OI-002', 'TC-OI-003', 'TC-OI-004'],
        'stock_in' => [
            'TC-STKIN-003', 'TC-STKIN-004', 'TC-STKIN-005',
            'TC-STKIN-006', 'TC-STKIN-007', 'TC-STKIN-008',
        ],
        'correction' => ['TC-CORR-002', 'TC-CORR-003', 'TC-CORR-004', 'TC-CORR-005'],
        'pos' => [
            'TC-POS-002', 'TC-POS-003', 'TC-POS-004',
            'TC-POS-005', 'TC-POS-006', 'TC-POS-007',
        ],
        'sales' => ['TC-SALES-003', 'TC-SALES-004'],
        'reports' => ['TC-REP-002', 'TC-REP-003'],
        'resources' => ['TC-RES-001', 'TC-RES-002'],
    ];

    public static function make(string $fixtureId, DateTimeImmutable $asOfManila): array
    {
        self::assertFixtureId($fixtureId);

        $fixtureTimestamp = $asOfManila->setTime(10, 0)->modify('-1 day')->format('Y-m-d H:i:s');
        $historyTimestamp = $asOfManila->setTime(12, 0)->modify('-1 day')->format('Y-m-d H:i:s');
        $disabledTimestamp = $asOfManila->setTime(10, 0)->format('Y-m-d H:i:s');
        $cases = [];

        foreach (self::FORMAL_CASES as $module => $caseIds) {
            foreach ($caseIds as $caseId) {
                $cases[$caseId] = [
                    'module' => $module,
                    'status' => 'not-executed',
                    'support' => self::supportFor($caseId),
                ];
            }
        }

        return [
            'fixture_id' => $fixtureId,
            'as_of_manila_date' => $asOfManila->format('Y-m-d'),
            'fixture_created_at' => $fixtureTimestamp,
            'formal_cases' => $cases,
            'planning_gaps' => [
                'TC-AUTH-006' => 'A later browser harness must capture genuine invalid-CSRF rejection and prove no mutation.',
                'TC-SALES-004' => 'A later assertion must explicitly prove mixed completed/voided status-neutral Sales History behavior.',
            ],
            'users' => [
                'admin' => self::user('FT17 Admin', 'ft17_admin', 'admin', 'active', 'FT17_ADMIN_PASSWORD'),
                'staff' => self::user('FT17 Staff', 'ft17_staff', 'staff', 'active', 'FT17_STAFF_PASSWORD'),
                'disabled_staff' => self::user('FT17 Disabled Staff', 'ft17_disabled', 'staff', 'disabled', null) + [
                    'updated_at' => $disabledTimestamp,
                ],
            ],
            'catalog' => [
                'categories' => [
                    'primary' => ['name' => 'FT17 Fasteners', 'status' => 'active'],
                    'secondary' => ['name' => 'FT17 Secondary', 'status' => 'active'],
                    'archived' => ['name' => 'FT17 Archived Category', 'status' => 'archived'],
                ],
                'products' => [
                    'primary' => ['category' => 'primary', 'name' => 'FT17 Edge Material', 'status' => 'active'],
                    'scoped_primary' => ['category' => 'primary', 'name' => 'FT17 Scoped Duplicate', 'status' => 'active'],
                    'scoped_secondary' => ['category' => 'secondary', 'name' => 'FT17 Scoped Duplicate', 'status' => 'active'],
                    'archived' => ['category' => 'primary', 'name' => 'FT17 Archived Product', 'status' => 'archived'],
                    'under_archived_category' => ['category' => 'archived', 'name' => 'FT17 Archived Ancestor Product', 'status' => 'archived'],
                ],
            ],
            'variants' => [
                'uninitialized_whole' => self::variant('primary', 'New Whole', 'piece', 'whole', '25.00', '0.000', null),
                'uninitialized_fractional' => self::variant('primary', 'New Fractional', 'kg', 'fractional', '20.00', '0.000', null),
                'initialized_whole' => self::variant('primary', 'Initialized Whole', 'piece', 'whole', '25.00', '10.000', ['INITIAL_STOCK', '10.000']),
                'initialized_fractional' => self::variant('primary', 'Initialized Fractional', 'kg', 'fractional', '20.00', '10.500', ['INITIAL_STOCK', '10.500']),
                'restocked_whole' => self::variant('primary', 'Restocked Whole', 'piece', 'whole', '30.00', '8.000', ['RESTOCKED', '8.000'], '40.00'),
                'corrected_whole' => self::variant('primary', 'Corrected Whole', 'piece', 'whole', '35.00', '4.000', ['CORRECTED', '4.000']),
                'sale_history_whole' => self::variant('primary', 'Sale History Whole', 'piece', 'whole', '25.00', '7.000', ['SOLD', '7.000']),
                'archived' => self::variant('primary', 'Archived Variant', 'piece', 'whole', '15.00', '0.000', ['INITIAL_STOCK', '0.000'], null, 'archived'),
                'under_archived_product' => self::variant('archived', 'Archived Product Variant', 'piece', 'whole', '15.00', '0.000', null, null, 'archived'),
                'under_archived_category' => self::variant('under_archived_category', 'Archived Ancestor Variant', 'piece', 'whole', '15.00', '0.000', null, null, 'archived'),
            ],
            'history_controls' => [
                'completed_sale' => [
                    'recorded_by' => 'disabled_staff',
                    'variant' => 'sale_history_whole',
                    'quantity' => '3.000',
                    'unit_price' => '25.00',
                    'total_amount' => '75.00',
                    'cash_received' => '100.00',
                    'change_amount' => '25.00',
                    'created_at' => $historyTimestamp,
                    'checkout_token' => self::deterministicUuid($fixtureId, 'completed-sale'),
                ],
                'controlled_voided_sale' => [
                    'recorded_by' => 'disabled_staff',
                    'status' => 'voided',
                    'total_amount' => '10.00',
                    'cash_received' => '10.00',
                    'change_amount' => '0.00',
                    'created_at' => $historyTimestamp,
                    'checkout_token' => self::deterministicUuid($fixtureId, 'controlled-voided-sale'),
                    'semantics' => 'Controlled status fixture only; no void workflow, SaleItem, SALE movement, or SALE_VOID movement is created.',
                ],
            ],
        ];
    }

    public static function manifest(array $plan): array
    {
        return [
            'purpose' => 'Tracker #17 infrastructure and future fixture support only',
            'fixture_id' => $plan['fixture_id'],
            'as_of_manila_date' => $plan['as_of_manila_date'],
            'fixture_created_at' => $plan['fixture_created_at'],
            'connection' => SafetyGuard::CONNECTION,
            'database' => SafetyGuard::DATABASE,
            'formal_case_count' => count($plan['formal_cases']),
            'formal_cases' => $plan['formal_cases'],
            'planning_gaps' => $plan['planning_gaps'],
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
                    'product' => $variant['product'],
                    'size' => $variant['size'],
                    'unit' => $variant['unit'],
                    'quantity_mode' => $variant['quantity_mode'],
                    'current_stock' => $variant['current_stock'],
                    'status' => $variant['status'],
                    'history_profile' => $variant['history_profile'],
                ],
                $plan['variants'],
            ),
            'history_controls' => [
                'completed_sale' => array_diff_key($plan['history_controls']['completed_sale'], ['checkout_token' => true]),
                'controlled_voided_sale' => array_diff_key($plan['history_controls']['controlled_voided_sale'], ['checkout_token' => true]),
            ],
            'formal_execution_started' => false,
        ];
    }

    public static function confirmationPhrase(string $fixtureId): string
    {
        self::assertFixtureId($fixtureId);

        return 'RESET-trackpro_ft17_test-FOR-FT17-INFRA:'.$fixtureId;
    }

    public static function confirmationMatches(string $fixtureId, string $provided): bool
    {
        return hash_equals(self::confirmationPhrase($fixtureId), $provided);
    }

    public static function assertFixtureId(string $fixtureId): void
    {
        if (preg_match('/\AFT17-INFRA-[A-Z0-9][A-Z0-9._-]{0,31}\z/D', $fixtureId) !== 1) {
            throw new InvalidArgumentException('The fixture identifier must use FT17-INFRA- followed by 1-32 uppercase letters, digits, dots, underscores, or hyphens.');
        }
    }

    /** @return array<string, string|null> */
    private static function user(string $name, string $username, string $role, string $status, ?string $passwordEnv): array
    {
        return compact('name', 'username', 'role', 'status') + ['password_env' => $passwordEnv];
    }

    private static function variant(
        string $product,
        string $size,
        string $unit,
        string $quantityMode,
        string $sellingPrice,
        string $currentStock,
        ?array $historyProfile,
        ?string $costPrice = null,
        string $status = 'active',
    ): array {
        return [
            'product' => $product,
            'size' => $size,
            'type_series' => '',
            'thickness' => '',
            'unit' => $unit,
            'quantity_mode' => $quantityMode,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'current_stock' => $currentStock,
            'low_stock_threshold' => '2.000',
            'status' => $status,
            'history_profile' => $historyProfile,
        ];
    }

    /** @return list<string> */
    private static function supportFor(string $caseId): array
    {
        return match (true) {
            str_starts_with($caseId, 'TC-AUTH-') => ['users', 'route-or-browser-harness'],
            str_starts_with($caseId, 'TC-CAT-'), str_starts_with($caseId, 'TC-PROD-'), str_starts_with($caseId, 'TC-VAR-') => ['catalog-state-controls'],
            str_starts_with($caseId, 'TC-OI-') => ['uninitialized-and-history-profile-variants'],
            str_starts_with($caseId, 'TC-STKIN-') => ['initialized-variants', 'transactional-test-seams'],
            str_starts_with($caseId, 'TC-CORR-') => ['movement-versioned-variants', 'transactional-test-seams'],
            str_starts_with($caseId, 'TC-POS-') => ['saleable-variants', 'transactional-test-seams'],
            str_starts_with($caseId, 'TC-SALES-'), str_starts_with($caseId, 'TC-REP-') => ['mixed-status-disabled-cashier-history'],
            default => ['route-and-resource-harness'],
        };
    }

    private static function deterministicUuid(string $fixtureId, string $label): string
    {
        $hex = substr(hash('sha256', $fixtureId.'|'.$label), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
