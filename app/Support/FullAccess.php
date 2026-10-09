<?php

namespace App\Support;

use App\Models\User;

/**
 * While a policy decides an ability for a user holding `{resource}.full-access`,
 * every `{resource}.*` permission check for that user passes (see the
 * Gate::before hook in AppServiceProvider). The policy's own rules still run.
 */
class FullAccess
{
    /**
     * @var array<int, array{user: int|string, resource: string}>
     */
    private static array $grants = [];

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function grant(User $user, string $resource, callable $callback): mixed
    {
        self::$grants[] = ['user' => $user->getKey(), 'resource' => $resource];

        try {
            return $callback();
        } finally {
            array_pop(self::$grants);
        }
    }

    public static function grants(mixed $user, string $ability): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        foreach (self::$grants as $grant) {
            if ($grant['user'] === $user->getKey() && str_starts_with($ability, $grant['resource'].'.')) {
                return true;
            }
        }

        return false;
    }
}
