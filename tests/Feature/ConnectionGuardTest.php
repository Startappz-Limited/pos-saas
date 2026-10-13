<?php

use App\Support\ConnectionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ConnectionGuard stops queries on any connection but the allowed ones before
 * they run (stage 0, step 6): dataset:build and db:restore-snapshot use it so
 * nothing they do can reach the development database.
 */
beforeEach(function () {
    $this->probeFile = storage_path('framework/testing/guard-probe-'.uniqid().'.sqlite');
    @mkdir(dirname($this->probeFile), 0755, true);
    touch($this->probeFile);
    config(['database.connections.guard_probe' => ['driver' => 'sqlite', 'database' => $this->probeFile, 'prefix' => '']]);
});

afterEach(function () {
    @unlink($this->probeFile);
});

it('blocks a query on a connection that is not allowed, before it runs', function () {
    ConnectionGuard::only([config('database.default')]);

    expect(fn () => Schema::connection('guard_probe')->create('should_not_exist', fn ($table) => $table->id()))
        ->toThrow(RuntimeException::class, "Blocked a query on the 'guard_probe' connection");

    // A fresh, unguarded connection to the same file sees no table.
    DB::purge('guard_probe');
    expect(Schema::connection('guard_probe')->hasTable('should_not_exist'))->toBeFalse();
});

it('lets queries through on the allowed connections', function () {
    ConnectionGuard::only(['guard_probe']);

    Schema::connection('guard_probe')->create('allowed_table', fn ($table) => $table->id());

    expect(DB::connection('guard_probe')->table('allowed_table')->count())->toBe(0);
});

it('blocks the default connection when only another one is allowed', function () {
    ConnectionGuard::only(['guard_probe']);

    expect(fn () => DB::connection()->select('select 1'))
        ->toThrow(RuntimeException::class, 'Blocked a query on the');
});

it('guards a connection that a service resolved before the guard', function () {
    $early = DB::connection('guard_probe');

    ConnectionGuard::only([config('database.default')]);

    expect(fn () => $early->select('select 1'))->toThrow(RuntimeException::class);
});
