<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\Concerns\ImmutableRecord;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Restock;
use App\Models\RestockItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class ModelFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any attempted PDO access fails before connecting, including SQLite.
        foreach (array_keys(config('database.connections')) as $name) {
            $connection = DB::connection($name);
            $connection->setPdo(fn () => throw new LogicException('Database access is forbidden in foundation tests.'));
            $connection->setReadPdo(fn () => throw new LogicException('Database access is forbidden in foundation tests.'));
        }
    }

    public function test_variant_identity_normalization_and_decimal_casts(): void
    {
        $variant = new ProductVariant([
            'size' => '  2   x  3  ',
            'type_series' => null,
            'thickness' => ' 1.5mm ',
            'unit' => ' KG ',
            'cost_price' => null,
            'selling_price' => '123.45',
        ]);
        $variant->current_stock = '0.125';

        $this->assertSame('2 x 3', $variant->size);
        $this->assertSame('', $variant->type_series);
        $this->assertSame('1.5mm', $variant->thickness);
        $this->assertSame('kg', $variant->unit);
        $this->assertNull($variant->cost_price);
        $this->assertSame('123.45', $variant->selling_price);
        $this->assertSame('0.125', $variant->current_stock);
        $this->assertSame('0.000', (new ProductVariant)->current_stock);
        $this->assertSame('-0.250', (new StockMovement(['quantity_change' => '-0.25']))->quantity_change);
    }

    public function test_cash_register_session_uses_trusted_writes_and_expected_casts(): void
    {
        $session = new CashRegisterSession;
        $session->opening_cash = '1250.5';
        $session->opened_at = '2026-09-17 08:00:00';
        $session->closed_at = '2026-09-17 17:00:00';
        $session->active_slot = '1';

        $this->assertSame(['*'], $session->getGuarded());
        $this->assertSame('1250.50', $session->opening_cash);
        $this->assertSame('2026-09-17 08:00:00', $session->opened_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-17 17:00:00', $session->closed_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $session->active_slot);
        $this->assertArrayNotHasKey(ImmutableRecord::class, class_uses_recursive($session));
    }

    public function test_variant_quantity_presentation_preserves_authoritative_decimal_values(): void
    {
        $whole = new ProductVariant(['quantity_mode' => 'whole']);
        $whole->current_stock = '8.000';
        $this->assertSame('8', $whole->displayCurrentStock());
        $this->assertSame('8.000', $whole->current_stock);

        $whole->current_stock = '0.000';
        $this->assertSame('0', $whole->displayCurrentStock());
        $this->assertSame('0.000', $whole->current_stock);

        $fractional = new ProductVariant(['quantity_mode' => 'fractional']);
        $fractional->current_stock = '7.500';
        $this->assertSame('7.500', $fractional->displayCurrentStock());
        $this->assertSame('7.500', $fractional->current_stock);

        $fractional->current_stock = '6.000';
        $this->assertSame('6.000', $fractional->displayCurrentStock());
        $this->assertSame('6.000', $fractional->current_stock);
    }

    public function test_public_numbers_are_derived_without_truncating_large_ids(): void
    {
        $sale = new Sale;
        $sale->id = 152;
        $this->assertSame('TRX-000152', $sale->receiptNumber());
        $sale->id = 1000001;
        $this->assertSame('TRX-1000001', $sale->receiptNumber());

        $restock = new Restock;
        $restock->id = 123;
        $this->assertSame('RST-000123', $restock->restockNumber());
    }

    public function test_unsaved_sale_has_no_receipt_number(): void
    {
        $this->expectException(LogicException::class);
        (new Sale)->receiptNumber();
    }

    public function test_user_factory_matches_username_schema_and_hides_credentials(): void
    {
        $user = User::factory()->admin()->disabled()->make(['username' => ' TEST_ADMIN ']);

        $this->assertSame('test_admin', $user->username);
        $this->assertSame('admin', $user->role);
        $this->assertSame('disabled', $user->status);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertArrayNotHasKey('email', $user->getAttributes());
    }

    public function test_relationships_use_the_approved_foreign_keys(): void
    {
        $this->assertSame('categories.id', (new Product)->category()->getQualifiedOwnerKeyName());
        $this->assertSame('products.category_id', (new Category)->products()->getQualifiedForeignKeyName());
        $this->assertSame('product_id', (new ProductVariant)->product()->getForeignKeyName());
        $this->assertSame('recorded_by', (new Sale)->recordedBy()->getForeignKeyName());
        $this->assertSame('cash_register_session_id', (new Sale)->cashRegisterSession()->getForeignKeyName());
        $this->assertSame('voided_by', (new Sale)->voidedBy()->getForeignKeyName());
        $this->assertSame('opened_by', (new CashRegisterSession)->openedBy()->getForeignKeyName());
        $this->assertSame('closed_by', (new CashRegisterSession)->closedBy()->getForeignKeyName());
        $this->assertSame('cash_register_session_id', (new CashRegisterSession)->sales()->getForeignKeyName());
        $this->assertSame('opened_by', (new User)->openedCashRegisterSessions()->getForeignKeyName());
        $this->assertSame('closed_by', (new User)->closedCashRegisterSessions()->getForeignKeyName());
        $this->assertSame('sale_id', (new SaleItem)->sale()->getForeignKeyName());
        $this->assertSame('sale_item_id', (new SaleItem)->saleMovement()->getForeignKeyName());
        $this->assertSame('restock_id', (new RestockItem)->restock()->getForeignKeyName());
        $this->assertSame('restock_item_id', (new RestockItem)->stockMovement()->getForeignKeyName());
        $this->assertSame('sale_item_id', (new StockMovement)->saleItem()->getForeignKeyName());
        $this->assertSame('restock_item_id', (new StockMovement)->restockItem()->getForeignKeyName());
        $this->assertSame('performed_by', (new StockMovement)->performedBy()->getForeignKeyName());
        $this->assertSame('created_by', (new PurchaseOrder)->createdBy()->getForeignKeyName());
        $this->assertSame('purchase_order_id', (new PurchaseOrder)->items()->getForeignKeyName());
        $this->assertSame('parent_purchase_order_id', (new PurchaseOrder)->parent()->getForeignKeyName());
        $this->assertSame('parent_purchase_order_id', (new PurchaseOrder)->children()->getForeignKeyName());
        $this->assertSame('purchase_order_id', (new PurchaseOrderItem)->purchaseOrder()->getForeignKeyName());
        $this->assertSame('product_variant_id', (new PurchaseOrderItem)->variant()->getForeignKeyName());
        $this->assertSame('created_by', (new User)->purchaseOrders()->getForeignKeyName());
        $this->assertSame('product_variant_id', (new ProductVariant)->purchaseOrderItems()->getForeignKeyName());
        $this->assertSame('RESTOCK', StockMovement::TYPE_RESTOCK);
        $this->assertSame('CORRECTION', StockMovement::TYPE_CORRECTION);
        $this->assertSame('SALE', StockMovement::TYPE_SALE);
        $this->assertSame('completed', Sale::STATUS_COMPLETED);
        $this->assertSame('voided', Sale::STATUS_VOIDED);
        $this->assertSame('pending', PurchaseOrder::STATUS_PENDING);
        $this->assertSame('partially_received', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        $this->assertSame('completed', PurchaseOrder::STATUS_COMPLETED);
        $this->assertSame('closed_with_remainder', PurchaseOrder::STATUS_CLOSED_WITH_REMAINDER);
        $this->assertSame('user_id', (new AuditLog)->user()->getForeignKeyName());
    }

    public function test_history_uses_created_at_only_and_rejects_instance_edits_and_deletes(): void
    {
        foreach ([Sale::class, SaleItem::class, Restock::class, RestockItem::class, StockMovement::class, AuditLog::class] as $class) {
            $record = new $class;
            $record->id = 1;
            $record->exists = true;
            $this->assertNull($record->getUpdatedAtColumn());

            foreach (['save', 'delete'] as $method) {
                try {
                    $record->$method();
                    $this->fail($class.' unexpectedly allowed '.$method);
                } catch (LogicException $exception) {
                    $this->assertStringStartsWith('Historical records cannot be ', $exception->getMessage());
                }
            }
        }
    }

    public function test_nonhistorical_models_remain_mutable(): void
    {
        foreach ([Product::class, ProductVariant::class, Category::class, User::class, CashRegisterSession::class, PurchaseOrder::class, PurchaseOrderItem::class] as $class) {
            $this->assertArrayNotHasKey(ImmutableRecord::class, class_uses_recursive($class));
        }
    }

    public function test_purchase_order_models_use_approved_defaults_and_mass_assignment_boundaries(): void
    {
        $purchaseOrder = new PurchaseOrder([
            'supplier_name' => '  Acme Supply  ',
            'notes' => 'Deliver to receiving.',
            'submission_token' => 'browser-controlled-token',
            'created_by' => 99,
            'status' => PurchaseOrder::STATUS_COMPLETED,
            'parent_purchase_order_id' => 10,
        ]);
        $item = new PurchaseOrderItem([
            'purchase_order_id' => 1,
            'product_variant_id' => 2,
            'product_name_snapshot' => 'Roofing Sheet',
            'size_snapshot' => '8 ft',
            'type_series_snapshot' => 'Corrugated',
            'thickness_snapshot' => '0.4 mm',
            'unit_snapshot' => 'sheet',
            'ordered_quantity' => '1.25',
            'expected_unit_cost' => '500',
        ]);

        $this->assertSame(['supplier_name', 'notes'], $purchaseOrder->getFillable());
        $this->assertSame('  Acme Supply  ', $purchaseOrder->supplier_name);
        $this->assertSame('Deliver to receiving.', $purchaseOrder->notes);
        $this->assertNull($purchaseOrder->submission_token);
        $this->assertNull($purchaseOrder->created_by);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $purchaseOrder->status);
        $this->assertNull($purchaseOrder->parent_purchase_order_id);
        $this->assertSame('1.250', $item->ordered_quantity);
        $this->assertSame('500.00', $item->expected_unit_cost);
        $this->assertSame([
            'purchase_order_id',
            'product_variant_id',
            'product_name_snapshot',
            'size_snapshot',
            'type_series_snapshot',
            'thickness_snapshot',
            'unit_snapshot',
            'ordered_quantity',
            'expected_unit_cost',
        ], $item->getFillable());
    }

    public function test_legacy_sale_allows_a_null_cash_register_session_relationship(): void
    {
        $sale = new Sale;

        $this->assertNull($sale->cash_register_session_id);
        $this->assertNull($sale->cashRegisterSession()->getParent()->cash_register_session_id);
        $this->assertNotContains('cash_register_session_id', $sale->getFillable());
    }

    public function test_catalog_mass_assignment_excludes_status_and_current_stock(): void
    {
        $category = new Category(['name' => 'Tools', 'status' => 'archived']);
        $product = new Product(['name' => 'Hammer', 'status' => 'archived']);
        $variant = new ProductVariant([
            'selling_price' => '100.00',
            'current_stock' => '9.000',
            'status' => 'archived',
        ]);

        $this->assertNull($category->status);
        $this->assertNull($product->status);
        $this->assertSame('active', $variant->status);
        $this->assertSame('0.000', $variant->current_stock);
    }

    public function test_audit_supports_entityless_events_and_structured_values(): void
    {
        $audit = new AuditLog(['action' => 'LOGIN', 'entity_type' => null, 'entity_id' => null, 'after_values' => ['status' => 'active']]);
        $this->assertNull($audit->entity_id);
        $this->assertSame(['status' => 'active'], $audit->after_values);
    }
}
