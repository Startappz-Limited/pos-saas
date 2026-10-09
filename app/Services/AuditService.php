<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only access to the audit trail.
 *
 * The application only ever writes audit rows through the AuditLogger listener;
 * everything here is a query. Shop scoping is applied here because audit rows live
 * on a separate connection and cannot be joined to `shops`.
 */
class AuditService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(array $filters, User $user, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters, $user)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Every audit row for one record, newest first.
     *
     * @return Collection<int, AuditLog>
     */
    public function forAuditable(string $type, string $uuid, User $user): Collection
    {
        return $this->query([], $user)
            ->forAuditable($type, $uuid)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function forUser(int $userId, User $viewer, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query([], $viewer)
            ->forUser($userId)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Rows for export, streamed in chunks and bounded by an explicit cap so a wide
     * date range cannot exhaust memory.
     *
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, AuditLog>
     */
    public function stream(array $filters, User $user, int $limit = 10000): \Generator
    {
        $yielded = 0;

        foreach ($this->query($filters, $user)->orderByDesc('created_at')->lazy(500) as $log) {
            if ($yielded >= $limit) {
                return;
            }

            $yielded++;

            yield $log;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function statistics(array $filters, User $user): array
    {
        $stats = $this->query($filters, $user)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN event = 'created' THEN 1 ELSE 0 END) as created")
            ->selectRaw("SUM(CASE WHEN event = 'updated' THEN 1 ELSE 0 END) as updated")
            ->selectRaw("SUM(CASE WHEN event IN ('deleted', 'force_deleted') THEN 1 ELSE 0 END) as deleted")
            ->first();

        return [
            'total' => (int) $stats->total,
            'created' => (int) $stats->created,
            'updated' => (int) $stats->updated,
            'deleted' => (int) $stats->deleted,
        ];
    }

    /**
     * The distinct auditable types present, for the filter dropdown.
     *
     * @return array<int, string>
     */
    public function auditableTypes(User $user): array
    {
        return $this->query([], $user)
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->all();
    }

    /**
     * Base query with shop scoping and filters applied.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function query(array $filters, User $user): Builder
    {
        $query = AuditLog::query();

        $this->applyShopScope($query, $user);

        return $query
            ->when(
                filled($filters['auditable_type'] ?? null),
                fn (Builder $q) => $q->where('auditable_type', $filters['auditable_type'])
            )
            ->when(
                filled($filters['event'] ?? null),
                fn (Builder $q) => $q->where('event', $filters['event'])
            )
            ->when(
                filled($filters['user_id'] ?? null),
                fn (Builder $q) => $q->where('user_id', (int) $filters['user_id'])
            )
            ->when(
                filled($filters['from'] ?? null),
                fn (Builder $q) => $q->where('created_at', '>=', $filters['from'])
            )
            ->when(
                filled($filters['to'] ?? null),
                fn (Builder $q) => $q->where('created_at', '<=', $filters['to'])
            );
    }

    /**
     * Restrict to the shops the user may see. Rows with no shop context are
     * system-wide and stay visible.
     *
     * @param  Builder<AuditLog>  $query
     */
    private function applyShopScope(Builder $query, User $user): void
    {
        if (! $user->hasShopRestrictions()) {
            return;
        }

        $shopIds = $user->assignedShopIds()->all();

        $query->where(function (Builder $q) use ($shopIds): void {
            $q->whereNull('shop_id')->orWhereIn('shop_id', $shopIds);
        });
    }
}
