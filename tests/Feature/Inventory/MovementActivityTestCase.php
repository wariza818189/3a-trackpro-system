<?php

namespace Tests\Feature\Inventory;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class MovementActivityTestCase extends RestockTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Extend the existing in-memory behavior scaffold for read-only assertions.
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('action');
        });
    }

    protected function movement(string $type = StockMovement::TYPE_CORRECTION, array $overrides = [], bool $purchaseOrder = false): StockMovement
    {
        $actor = User::factory()->admin()->create();
        $variant = $this->variant($this->product($this->category()), [
            'size' => 'Movement size', 'type_series' => 'Movement series', 'thickness' => '2mm',
            'unit' => 'kg', 'quantity_mode' => 'fractional', 'current_stock' => '123.000',
        ]);
        $data = [
            'product_variant_id' => $variant->id,
            'movement_type' => $type,
            'quantity_before' => '10.000',
            'quantity_change' => '4.000',
            'quantity_after' => '14.000',
            'performed_by' => $actor->id,
            'sale_item_id' => null,
            'restock_item_id' => null,
            'reason' => 'Reason for '.$type,
            'created_at' => '2026-09-27 10:15:00',
        ];
        if ($type === StockMovement::TYPE_INITIAL_STOCK) {
            $data['quantity_before'] = '0.000';
            $data['quantity_after'] = '4.000';
        }
        if (in_array($type, [StockMovement::TYPE_SALE, StockMovement::TYPE_CORRECTION], true)) {
            $data['quantity_change'] = '-2.500';
            $data['quantity_after'] = '7.500';
        }
        if (in_array($type, [StockMovement::TYPE_SALE, StockMovement::TYPE_SALE_VOID], true)) {
            $saleId = DB::table('sales')->insertGetId([
                'checkout_token' => Str::uuid()->toString(), 'recorded_by' => $actor->id,
                'status' => 'completed', 'total_amount' => '100.00', 'cash_received' => '100.00',
                'change_amount' => '0.00', 'created_at' => $data['created_at'],
            ]);
            $data['sale_item_id'] = DB::table('sale_items')->insertGetId([
                'sale_id' => $saleId, 'product_variant_id' => $variant->id,
            ]);
        }
        if ($type === StockMovement::TYPE_RESTOCK) {
            $poId = $purchaseOrder ? DB::table('purchase_orders')->insertGetId([
                'submission_token' => Str::uuid()->toString(), 'created_by' => $actor->id,
                'supplier_name' => 'Movement supplier', 'status' => 'completed',
            ]) : null;
            $restockId = DB::table('restocks')->insertGetId([
                'submission_token' => Str::uuid()->toString(), 'recorded_by' => $actor->id,
                'purchase_order_id' => $poId, 'total_cost' => '20.00', 'created_at' => $data['created_at'],
            ]);
            $data['restock_item_id'] = DB::table('restock_items')->insertGetId([
                'restock_id' => $restockId, 'product_variant_id' => $variant->id,
                'product_name_snapshot' => 'Historical product', 'unit_snapshot' => 'kg',
                'quantity' => '4.000', 'unit_cost' => '5.00', 'line_total' => '20.00',
                'created_at' => $data['created_at'],
            ]);
        }
        if (in_array($type, [StockMovement::TYPE_RESTOCK, StockMovement::TYPE_SALE], true)) {
            $data['reason'] = null;
        }

        $id = DB::table('stock_movements')->insertGetId(array_replace($data, $overrides));

        return StockMovement::query()->findOrFail($id);
    }

    /** @return list<StockMovement> */
    protected function allTypes(): array
    {
        return array_map($this->movement(...), [
            StockMovement::TYPE_INITIAL_STOCK, StockMovement::TYPE_RESTOCK,
            StockMovement::TYPE_SALE, StockMovement::TYPE_CORRECTION, StockMovement::TYPE_SALE_VOID,
        ]);
    }

    protected function assertReadOnly(string $url): void
    {
        $tables = ['users', 'products', 'product_variants', 'sales', 'sale_items', 'restocks', 'restock_items', 'stock_movements', 'audit_logs'];
        $snapshot = fn (): array => collect($tables)->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->orderBy('id')->get()->toJson(),
        ])->all();
        $before = $snapshot();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get($url)->assertOk();
        $this->head($url)->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertMatchesRegularExpression('/\Aselect\b/i', $query['query']);
        }
        $this->assertSame($before, $snapshot());
    }

    protected function requestQueryCount(string $url): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
