<?php

declare(strict_types=1);

namespace Tjseabury\WoprIslands\Snapshot;

/**
 * Signs snapshot payloads with HMAC-SHA256.
 * In WordPress, the signing key defaults to AUTH_KEY when no secret is injected.
 * Outside WordPress (e.g. PHPUnit), pass an explicit key via the constructor.
 */
final class SnapshotSigner
{
    public function __construct(
        private readonly ?string $secret = null
    ) {
    }

    public function createToken(array $payload): string
    {
        $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $canonical, $this->resolveKey(), true);

        return self::base64UrlEncode($canonical) . '.' . self::base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseToken(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new SnapshotException('Invalid snapshot token format.');
        }

        [$payloadB64, $sigB64] = $parts;
        $canonical = self::base64UrlDecode($payloadB64);
        $expected = hash_hmac('sha256', $canonical, $this->resolveKey(), true);
        $signature = self::base64UrlDecode($sigB64);

        if (!hash_equals($expected, $signature)) {
            throw new SnapshotException('Snapshot signature verification failed.');
        }

        $payload = json_decode($canonical, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new SnapshotException('Snapshot payload must be a JSON object.');
        }

        return $payload;
    }

    private function resolveKey(): string
    {
        if ($this->secret !== null && $this->secret !== '') {
            return $this->secret;
        }

        if (defined('AUTH_KEY') && AUTH_KEY !== '') {
            return (string) AUTH_KEY;
        }

        throw new SnapshotException(
            'No signing key: set AUTH_KEY in WordPress or inject a secret into SnapshotSigner.'
        );
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $b64): string
    {
        $remainder = strlen($b64) % 4;
        if ($remainder > 0) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($decoded === false) {
            throw new SnapshotException('Invalid base64 in snapshot token.');
        }

        return $decoded;
    }
}
