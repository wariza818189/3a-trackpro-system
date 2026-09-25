<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AuditLogController extends Controller
{
    private const SAFE_FIELDS = [
        'name' => 'Name',
        'username' => 'Username',
        'role' => 'Role',
        'status' => 'Status',
    ];

    public function index(Request $request): View
    {
        $actors = User::query()
            ->select(['id', 'name', 'username', 'status'])
            ->whereIn('id', AuditLog::query()->select('user_id')->distinct())
            ->orderBy('name')
            ->orderBy('username')
            ->orderBy('id')
            ->get();
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $actionOptions = $actions->map(fn (string $value): array => [
            'value' => $value,
            'label' => self::actionLabel($value),
        ]);

        $user = $this->filterInput($request->query('user', ''));
        $action = $this->filterInput($request->query('action', ''));
        $dateFrom = $this->filterInput($request->query('date_from', ''));
        $dateTo = $this->filterInput($request->query('date_to', ''));
        $filterErrors = [];

        if (! is_string($user) || ($user !== '' && (! preg_match('/\A[1-9][0-9]*\z/D', $user) || ! $actors->contains('id', (int) $user) || (string) (int) $user !== $user))) {
            $filterErrors['user'] = 'Select a valid actor.';
        }
        if (! is_string($action) || ($action !== '' && ! $actions->containsStrict($action))) {
            $filterErrors['action'] = 'Select a valid action.';
        }

        [$from, $fromError] = $this->dateBoundary($dateFrom);
        [$to, $toError] = $this->dateBoundary($dateTo);
        if ($fromError !== null) {
            $filterErrors['date_from'] = $fromError;
        }
        if ($toError !== null) {
            $filterErrors['date_to'] = $toError;
        }
        if ($from !== null && $to !== null && $from->isAfter($to)) {
            $filterErrors['date_to'] = 'The to date must be on or after the from date.';
        }

        $entries = null;
        $hasRecords = null;
        if ($filterErrors === []) {
            $entries = AuditLog::query()
                ->select(['id', 'user_id', 'action', 'entity_type', 'entity_id', 'before_values', 'after_values', 'description', 'created_at'])
                ->with('user:id,name,username')
                ->when($user !== '', fn ($query) => $query->where('user_id', (int) $user))
                ->when($action !== '', fn ($query) => $query->where('action', $action))
                ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from))
                ->when($to !== null, fn ($query) => $query->where('created_at', '<', $to->addDay()->startOfDay()))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();

            if ($entries->total() === 0) {
                $hasRecords = AuditLog::query()->exists();
            }

            $affectedUserIds = $entries->getCollection()
                ->filter(fn (AuditLog $log): bool => $log->entity_type === 'user' && $log->entity_id !== null)
                ->pluck('entity_id')
                ->unique()
                ->all();
            $affectedUsers = $affectedUserIds === []
                ? collect()
                : User::query()->select(['id', 'name', 'username'])->whereIn('id', $affectedUserIds)->get()->keyBy('id');

            $entries->setCollection($entries->getCollection()->map(function (AuditLog $log) use ($affectedUsers): array {
                $affectedUser = $log->entity_type === 'user' ? $affectedUsers->get($log->entity_id) : null;

                return [
                    'log' => $log,
                    'action_label' => self::actionLabel($log->action),
                    'record_label' => self::recordLabel($log),
                    'current_user' => $affectedUser,
                    'changes' => self::changes($log),
                ];
            }));
        }

        return view('audit-logs.index', compact(
            'actors', 'actionOptions', 'user', 'action', 'dateFrom', 'dateTo', 'filterErrors', 'entries', 'hasRecords',
        ));
    }

    private function filterInput(mixed $value): ?string
    {
        return is_string($value) ? trim($value) : null;
    }

    /** @return array{CarbonImmutable|null, string|null} */
    private function dateBoundary(?string $value): array
    {
        if ($value === null) {
            return [null, 'Enter a valid date in YYYY-MM-DD format.'];
        }
        if ($value === '') {
            return [null, null];
        }
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value) !== 1) {
            return [null, 'Enter a valid date in YYYY-MM-DD format.'];
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        $errors = CarbonImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return [null, 'Enter a valid date in YYYY-MM-DD format.'];
        }

        return [$date->startOfDay(), null];
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'USER_CREATED' => 'User Created',
            'USER_UPDATED' => 'User Updated',
            'USER_ROLE_CHANGED' => 'User Role Changed',
            'USER_DISABLED' => 'User Disabled',
            'USER_REACTIVATED' => 'User Reactivated',
            'USER_PASSWORD_RESET' => 'User Password Reset',
            default => Str::title(Str::lower(str_replace(['_', '-'], ' ', $action))) ?: $action,
        };
    }

    private static function recordLabel(AuditLog $log): string
    {
        if ($log->entity_type === null || $log->entity_id === null) {
            return 'System event';
        }

        return match ($log->entity_type) {
            'user' => 'User #'.$log->entity_id,
            'sale' => 'Sale #'.$log->entity_id,
            default => 'Other record #'.$log->entity_id,
        };
    }

    /** @return array<int, array{label: string, before: string|null, after: string|null, transition: bool}> */
    private static function changes(AuditLog $log): array
    {
        if ($log->action === 'USER_PASSWORD_RESET') {
            return [];
        }

        $fields = match ($log->action) {
            'USER_UPDATED' => ['name', 'username'],
            'USER_ROLE_CHANGED' => ['role'],
            'USER_DISABLED', 'USER_REACTIVATED' => ['status'],
            default => array_keys(self::SAFE_FIELDS),
        };
        $before = is_array($log->before_values) ? $log->before_values : [];
        $after = is_array($log->after_values) ? $log->after_values : [];
        $changes = [];
        foreach ($fields as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;
            if ($log->action === 'USER_CREATED' && ! is_string($new)) {
                continue;
            }
            if (! is_string($old) && ! is_string($new)) {
                continue;
            }
            $changes[] = [
                'label' => self::SAFE_FIELDS[$field],
                'before' => is_string($old) ? self::displayValue($field, $old) : null,
                'after' => is_string($new) ? self::displayValue($field, $new) : null,
                'transition' => $log->action !== 'USER_CREATED',
            ];
        }

        return $changes;
    }

    private static function displayValue(string $field, string $value): string
    {
        if ($field === 'role' || $field === 'status') {
            return match ($value) {
                'admin' => 'Admin',
                'staff' => 'Staff',
                'active' => 'Active',
                'disabled' => 'Disabled',
                default => $value,
            };
        }

        return $value;
    }
}
