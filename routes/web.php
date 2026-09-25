<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DamagedItemsReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\LowStockReportController;
use App\Http\Controllers\OpeningInventoryController;
use App\Http\Controllers\PendingPurchaseOrdersReportController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderFollowUpController;
use App\Http\Controllers\PurchaseOrderReceivingController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RestockingReportController;
use App\Http\Controllers\SalesHistoryController;
use App\Http\Controllers\StockCorrectionController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\UnfulfilledItemsReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/register/open', [CashRegisterController::class, 'open'])->name('pos.register.open');
    Route::post('/pos/register/close', [CashRegisterController::class, 'close'])->name('pos.register.close');
    Route::get('/sales', [SalesHistoryController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SalesHistoryController::class, 'show'])->whereNumber('sale')->name('sales.show');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
    Route::get('/product-variants', [ProductVariantController::class, 'index'])->name('product-variants.index');
    Route::get('/stock-in', [StockInController::class, 'index'])->name('stock-in.index');
    Route::get('/stock-in/create', [StockInController::class, 'create'])->name('stock-in.create');
    Route::post('/stock-in', [StockInController::class, 'store'])->name('stock-in.store');
    Route::get('/stock-in/{restock}', [StockInController::class, 'show'])->name('stock-in.show');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderReceivingController::class, 'create'])->whereNumber('purchaseOrder')->name('purchase-orders.receive.create');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderReceivingController::class, 'store'])->whereNumber('purchaseOrder')->name('purchase-orders.receive.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->whereNumber('purchaseOrder')->name('purchase-orders.show');

    Route::middleware('can:access-admin')->group(function (): void {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/inventory', [InventoryReportController::class, 'index'])->name('reports.inventory');
        Route::get('/reports/low-stock', [LowStockReportController::class, 'index'])->name('reports.low-stock');
        Route::get('/reports/restocking', [RestockingReportController::class, 'index'])->name('reports.restocking');
        Route::get('/reports/pending-purchase-orders', [PendingPurchaseOrdersReportController::class, 'index'])->name('reports.pending-purchase-orders');
        Route::get('/reports/unfulfilled-items', [UnfulfilledItemsReportController::class, 'index'])->name('reports.unfulfilled-items');
        Route::get('/reports/damaged-items', [DamagedItemsReportController::class, 'index'])->name('reports.damaged-items');
        Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/purchase-orders/{purchaseOrder}/follow-up', [PurchaseOrderFollowUpController::class, 'create'])->whereNumber('purchaseOrder')->name('purchase-orders.follow-up.create');
        Route::post('/purchase-orders/{purchaseOrder}/follow-up', [PurchaseOrderFollowUpController::class, 'store'])->whereNumber('purchaseOrder')->name('purchase-orders.follow-up.store');
        Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->whereNumber('purchaseOrder')->name('purchase-orders.edit');
        Route::patch('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->whereNumber('purchaseOrder')->name('purchase-orders.update');
        Route::get('/opening-inventory', [OpeningInventoryController::class, 'index'])->name('opening-inventory.index');
        Route::get('/product-variants/{productVariant}/opening-inventory', [OpeningInventoryController::class, 'create'])->name('opening-inventory.create');
        Route::post('/product-variants/{productVariant}/opening-inventory', [OpeningInventoryController::class, 'store'])->name('opening-inventory.store');

        Route::get('/stock-corrections', [StockCorrectionController::class, 'index'])->name('stock-corrections.index');
        Route::get('/product-variants/{productVariant}/stock-correction', [StockCorrectionController::class, 'create'])->name('stock-corrections.create');
        Route::post('/product-variants/{productVariant}/stock-correction', [StockCorrectionController::class, 'store'])->name('stock-corrections.store');

        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
        Route::patch('/categories/{category}/reactivate', [CategoryController::class, 'reactivate'])->name('categories.reactivate');

        Route::get('/categories/{category}/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/categories/{category}/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
        Route::patch('/products/{product}/reactivate', [ProductController::class, 'reactivate'])->name('products.reactivate');

        Route::get('/products/{product}/variants/create', [ProductVariantController::class, 'create'])->name('product-variants.create');
        Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->name('product-variants.store');
        Route::get('/product-variants/{productVariant}/edit', [ProductVariantController::class, 'edit'])->name('product-variants.edit');
        Route::patch('/product-variants/{productVariant}', [ProductVariantController::class, 'update'])->name('product-variants.update');
        Route::patch('/product-variants/{productVariant}/archive', [ProductVariantController::class, 'archive'])->name('product-variants.archive');
        Route::patch('/product-variants/{productVariant}/reactivate', [ProductVariantController::class, 'reactivate'])->name('product-variants.reactivate');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
