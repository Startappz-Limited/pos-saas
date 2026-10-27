<?php

namespace App\Services\Sync;

/**
 * Where a till's last pull stopped (Phase 7): for each paged entity the
 * `updated_at` and id of the last row sent, and for the snapshots (staff,
 * settings) a fingerprint of what was last sent: roles and shop assignments
 * change no timestamp, so a snapshot is sent again whenever its content
 * differs. Opaque to the till: it sends back what it was given. A cursor that
 * can't be read starts the pull afresh.
 */
final class SyncCursor
{
    /**
     * @param  array<string, array{0: string, 1: int}>  $positions
     * @param  array<string, string>  $fingerprints
     */
    public function __construct(
        public array $positions = [],
        public array $fingerprints = [],
    ) {}

    public static function decode(?string $cursor): self
    {
        if ($cursor === null || $cursor === '') {
            return new self;
        }

        $data = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true);

        if (! is_array($data) || ($data['v'] ?? null) !== 1 || ! is_array($data['p'] ?? null)) {
            return new self;
        }

        $positions = [];
        foreach ($data['p'] as $entity => $position) {
            if (is_string($entity) && is_array($position) && is_string($position[0] ?? null) && is_int($position[1] ?? null)) {
                $positions[$entity] = [$position[0], $position[1]];
            }
        }

        $fingerprints = array_filter(is_array($data['s'] ?? null) ? $data['s'] : [], 'is_string');

        return new self($positions, $fingerprints);
    }

    public function encode(): string
    {
        $json = json_encode(['v' => 1, 'p' => (object) $this->positions, 's' => (object) $this->fingerprints]);

        return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    public function position(string $entity): ?array
    {
        return $this->positions[$entity] ?? null;
    }
}
