<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $admin = $request->user()->can('access-admin');
        $status = $admin && in_array($request->query('status'), ['active', 'archived', 'all'], true)
            ? $request->query('status')
            : 'active';
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';

        $categories = Category::query()
            ->when(! $admin || $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $query->whereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
            })
            ->withCount([
                'products' => fn ($query) => $admin ? $query : $query->where('status', Product::STATUS_ACTIVE),
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('categories.index', compact('categories', 'status', 'search', 'admin'));
    }

    public function create(): View
    {
        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        try {
            $category = new Category($request->safe()->only('name'));
            $category->status = Category::STATUS_ACTIVE;
            $category->save();
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception, 'name', 'A category with this name already exists.');
            throw $exception;
        }

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        abort_unless($category->status === Category::STATUS_ACTIVE, 409, 'Archived categories must be reactivated before editing.');

        return view('categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $category): void {
                $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
                if ($locked->status !== Category::STATUS_ACTIVE) {
                    throw ValidationException::withMessages(['name' => 'Archived categories must be reactivated before editing.']);
                }

                $locked->update($request->safe()->only('name'));
            });
        } catch (QueryException $exception) {
            $this->throwIfDuplicate($exception, 'name', 'A category with this name already exists.');
            throw $exception;
        }

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function archive(Category $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());

            if ($locked->status !== Category::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['status' => 'Only an active category can be archived.']);
            }

            if ($locked->products()->where('status', Product::STATUS_ACTIVE)->exists()) {
                throw ValidationException::withMessages(['status' => 'Archive all active products in this category first.']);
            }

            $locked->status = Category::STATUS_ARCHIVED;
            $locked->save();
        });

        return back()->with('success', 'Category archived.');
    }

    public function reactivate(Category $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());

            if ($locked->status !== Category::STATUS_ARCHIVED) {
                throw ValidationException::withMessages(['status' => 'Only an archived category can be reactivated.']);
            }

            $locked->status = Category::STATUS_ACTIVE;
            $locked->save();
        });

        return back()->with('success', 'Category reactivated.');
    }

    private function throwIfDuplicate(QueryException $exception, string $field, string $message): void
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $sqliteUnique = $driverCode === 19
            && str_contains(strtolower($exception->getMessage()), 'unique constraint failed');

        if (($sqlState === '23000' && $driverCode === 1062) || $sqliteUnique) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
