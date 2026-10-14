<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stops every query on any connection except the allowed ones, before it
 * runs (stage 0, after two near-misses with the development database).
 *
 * Commands that rebuild or restore a test database switch the default
 * connection at runtime, but services resolved at boot keep the connection
 * they started with: Spatie's permission cache, for one, stays bound to the
 * .env database's cache table. This guard makes any such stray query fail
 * loudly instead of reaching the development database.
 *
 * Registered on every configured connection; resolving a connection doesn't
 * open it, and services that already hold a connection get the same
 * (guarded) instance.
 */
class ConnectionGuard
{
    /**
     * @param  list<string>  $allowed  connection names queries may run on
     */
    public static function only(array $allowed): void
    {
        foreach (array_keys(config('database.connections')) as $name) {
            DB::connection($name)->beforeExecuting(function (string $query, array $bindings, Connection $connection) use ($allowed): void {
                if (! in_array($connection->getName(), $allowed, true)) {
                    throw new RuntimeException(sprintf(
                        "Blocked a query on the '%s' connection (%s): only %s may be used here. Query: %s",
                        $connection->getName(),
                        $connection->getDatabaseName(),
                        implode(', ', $allowed),
                        mb_substr($query, 0, 200),
                    ));
                }
            });
        }
    }
}
