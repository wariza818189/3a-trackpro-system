<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OpeningInventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\StockCorrectionController;
use App\Http\Controllers\StockInController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', fn () => view('welcome'))->name('home');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/product-variants', [ProductVariantController::class, 'index'])->name('product-variants.index');
    Route::get('/stock-in', [StockInController::class, 'index'])->name('stock-in.index');
    Route::get('/stock-in/create', [StockInController::class, 'create'])->name('stock-in.create');
    Route::post('/stock-in', [StockInController::class, 'store'])->name('stock-in.store');
    Route::get('/stock-in/{restock}', [StockInController::class, 'show'])->name('stock-in.show');

    Route::middleware('can:access-admin')->group(function (): void {
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
