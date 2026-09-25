<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeUserRoleRequest;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Models\User;
use App\Services\Users\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate(['q' => ['sometimes', 'string', 'max:100']]);
        $search = trim($validated['q'] ?? '');
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

        $users = User::query()
            ->select(['id', 'name', 'username', 'role', 'status', 'created_at'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($escaped): void {
                $pattern = "%{$escaped}%";
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("username LIKE ? ESCAPE '!'", [$pattern]);
            }))
            ->orderBy('name')
            ->orderBy('username')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(StoreUserRequest $request, UserManagementService $service): RedirectResponse
    {
        $user = $service->create($request->user(), $request->safe()->only(['name', 'username', 'password', 'role']));

        return redirect()->route('users.edit', $user)->with('success', 'User account created.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserProfileRequest $request, User $user, UserManagementService $service): RedirectResponse
    {
        $service->updateProfile($request->user(), $user, $request->safe()->only(['name', 'username']));

        return redirect()->route('users.edit', $user)->with('success', 'User profile updated.');
    }

    public function changeRole(ChangeUserRoleRequest $request, User $user, UserManagementService $service): RedirectResponse
    {
        $service->changeRole($request->user(), $user, $request->validated('role'));

        return redirect()->route('users.edit', $user)->with('success', 'User role changed.');
    }

    public function archive(Request $request, User $user, UserManagementService $service): RedirectResponse
    {
        $service->archive($request->user(), $user);

        return redirect()->route('users.edit', $user)->with('success', 'User account disabled.');
    }

    public function reactivate(Request $request, User $user, UserManagementService $service): RedirectResponse
    {
        $service->reactivate($request->user(), $user);

        return redirect()->route('users.edit', $user)->with('success', 'User account reactivated.');
    }

    public function password(ResetUserPasswordRequest $request, User $user, UserManagementService $service): RedirectResponse
    {
        $service->resetPassword($request->user(), $user, $request->validated('password'));

        return redirect()->route('users.edit', $user)->with('success', 'User password reset.');
    }
}
