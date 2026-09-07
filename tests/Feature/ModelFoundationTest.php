<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Concerns\ImmutableRecord;
use App\Models\Product;
use App\Models\ProductVariant;
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
        $this->assertSame('voided_by', (new Sale)->voidedBy()->getForeignKeyName());
        $this->assertSame('sale_id', (new SaleItem)->sale()->getForeignKeyName());
        $this->assertSame('restock_id', (new RestockItem)->restock()->getForeignKeyName());
        $this->assertSame('restock_item_id', (new RestockItem)->stockMovement()->getForeignKeyName());
        $this->assertSame('sale_item_id', (new StockMovement)->saleItem()->getForeignKeyName());
        $this->assertSame('restock_item_id', (new StockMovement)->restockItem()->getForeignKeyName());
        $this->assertSame('performed_by', (new StockMovement)->performedBy()->getForeignKeyName());
        $this->assertSame('RESTOCK', StockMovement::TYPE_RESTOCK);
        $this->assertSame('CORRECTION', StockMovement::TYPE_CORRECTION);
        $this->assertSame('user_id', (new AuditLog)->user()->getForeignKeyName());
    }

    public function test_history_uses_created_at_only_and_rejects_instance_edits_and_deletes(): void
    {
        foreach ([SaleItem::class, Restock::class, RestockItem::class, StockMovement::class, AuditLog::class] as $class) {
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
        foreach ([Sale::class, Product::class, ProductVariant::class, Category::class, User::class] as $class) {
            $this->assertArrayNotHasKey(ImmutableRecord::class, class_uses_recursive($class));
        }
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
