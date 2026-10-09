<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Diffs the Flutter app's endpoint registry against the routes this API actually
 * registers.
 *
 * The mobile app is a deployed client: a renamed or removed route is not a
 * compile error anywhere, it is a 404 discovered by a cashier mid-shift. Worse,
 * the app's models parse defensively (`json['x']?.toString() ?? '0.00'`), so a
 * renamed *field* degrades to a default instead of throwing. This command turns
 * the path half of that contract into something CI can fail on.
 */
class MobileContractDiff extends Command
{
    protected $signature = 'mobile:contract-diff
        {--app-path= : Path to the Flutter project (defaults to config mobile.app_path)}
        {--fail-on-drift : Exit non-zero when the app calls a route that does not exist}';

    protected $description = 'Diff the Flutter app\'s api_endpoints.dart against the registered API routes';

    /**
     * Routes the mobile app is not expected to call — inbound webhooks are for
     * external platforms, not the client.
     */
    private const NON_CLIENT_PREFIXES = ['webhooks/'];

    public function handle(): int
    {
        $registry = $this->registryPath();

        if ($registry === null || ! is_file($registry)) {
            $this->components->error('Flutter endpoint registry not found.');
            $this->line('  Looked for: '.($registry ?: '(no path configured)'));
            $this->line('  Set MOBILE_APP_PATH in .env or pass --app-path=/path/to/flutter/pos');

            return self::FAILURE;
        }

        $declared = $this->parseFlutterEndpoints((string) file_get_contents($registry));
        $registered = $this->registeredApiPaths();

        // Paths the app calls that this API does not serve — these 404 in production.
        $missing = array_values(array_diff($declared, $registered));

        // Paths this API serves that the app never references — not a defect, but
        // it's where "the app can't do X yet" usually turns out to live.
        $unused = array_values(array_diff($registered, $declared));

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Flutter registry</>', $registry);
        $this->components->twoColumnDetail('<fg=gray>Endpoints declared by app</>', (string) count($declared));
        $this->components->twoColumnDetail('<fg=gray>API paths registered</>', (string) count($registered));
        $this->newLine();

        if ($missing !== []) {
            $this->components->error(count($missing).' endpoint(s) the app calls do NOT exist in this API');
            foreach ($missing as $path) {
                $this->line("  <fg=red>✗</> {$path}".$this->suggest($path, $registered));
            }
            $this->newLine();
        } else {
            $this->components->info('Every endpoint the app calls is registered.');
        }

        if ($unused !== []) {
            $this->line("<fg=yellow>API surface the app does not consume ({$this->count($unused)}):</>");
            foreach ($unused as $path) {
                $this->line("  <fg=gray>·</> {$path}");
            }
            $this->newLine();
        }

        return $missing !== [] && $this->option('fail-on-drift')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function count(array $items): int
    {
        return count($items);
    }

    /**
     * Nearest registered path, so the output says what to change rather than just
     * that something is wrong. `/status` vs `/update-status` is the failure mode
     * this exists to catch.
     */
    private function suggest(string $path, array $registered): string
    {
        $parent = Str::beforeLast($path, '/');
        $leaf = Str::afterLast($path, '/');

        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($registered as $candidate) {
            if (Str::beforeLast($candidate, '/') !== $parent) {
                continue;
            }

            $candidateLeaf = Str::afterLast($candidate, '/');

            // A renamed action usually keeps the old name inside the new one —
            // `status` → `update-status`. Rank that far above a merely similar
            // sibling, which is how `notes` used to win this comparison.
            $score = str_contains($candidateLeaf, $leaf) || str_contains($leaf, $candidateLeaf)
                ? 0
                : levenshtein($leaf, $candidateLeaf);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        // Only volunteer a suggestion when it is plausibly the same endpoint.
        return $best !== null && $bestScore <= max(4, (int) (strlen($leaf) * 0.5))
            ? "  <fg=gray>→ did you mean</> <fg=green>{$best}</><fg=gray>?</>"
            : '';
    }

    private function registryPath(): ?string
    {
        $base = $this->option('app-path') ?: config('mobile.app_path');

        return $base ? rtrim((string) $base, '/').'/lib/core/constants/api_endpoints.dart' : null;
    }

    /**
     * Pull path literals out of `api_endpoints.dart` and normalise them into the
     * shape `Route::getRoutes()` reports.
     *
     * Both forms in that file are covered:
     *   static const String sales = '/sales';
     *   static String saleVoid(String uuid) => '/sales/$uuid/void';
     *
     * @return array<int, string>
     */
    private function parseFlutterEndpoints(string $dart): array
    {
        // Every single-quoted literal that looks like an absolute path.
        preg_match_all("#'(/[^']*)'#", $dart, $matches);

        $paths = [];

        foreach ($matches[1] ?? [] as $literal) {
            // Interpolated Dart identifiers (`$uuid`, `${alertUuid}`) become the
            // route-parameter placeholder so both sides compare structurally.
            $normalised = preg_replace('/\$\{?[A-Za-z_][A-Za-z0-9_]*\}?/', '{param}', $literal);

            if ($normalised === null || $normalised === '/') {
                continue;
            }

            $paths[] = trim($normalised, '/');
        }

        return $this->normalise($paths);
    }

    /**
     * @return array<int, string>
     */
    private function registeredApiPaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! Str::startsWith($uri, 'api/')) {
                continue;
            }

            $uri = Str::after($uri, 'api/');

            foreach (self::NON_CLIENT_PREFIXES as $prefix) {
                if (Str::startsWith($uri, $prefix)) {
                    continue 2;
                }
            }

            // `{sale:uuid}` / `{cashRegister}` all collapse to one placeholder:
            // the app's literal cannot encode the binding field anyway.
            $paths[] = (string) preg_replace('/\{[^}]+\}/', '{param}', $uri);
        }

        return $this->normalise($paths);
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function normalise(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths)));
        sort($paths);

        return $paths;
    }
}
