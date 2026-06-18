<?php

declare(strict_types=1);

namespace Chronicle\Signing;

use Illuminate\Support\Facades\Log;

/**
 * Adapts the pre-key-ring single-key signing config into the current keys/active structure.
 */
final class LegacySigningConfigAdapter
{
    protected static bool $noticed = false;

    /**
     * Return true when the config uses the pre-1.10 flat signing shape (no `keys` key).
     *
     * @param  array<string, mixed>  $config
     */
    public static function isLegacy(array $config): bool
    {
        return ! array_key_exists('keys', $config);
    }

    /**
     * Convert the old flat signing config to the plural signing.active + signing.keys[] shape.
     *
     * @param  array<string, mixed>  $config
     * @return array{active: string, enforce_on_boot: bool, keys: array<string, array<string, mixed>>}
     */
    public static function adapt(array $config): array
    {
        if (! self::$noticed) {
            self::$noticed = true;
            Log::notice(
                '[Chronicle] Deprecated flat signing config detected. '
                .'Migrate to the signing.active + signing.keys[] shape. '
                .'See https://laravel-chronicle.github.io/docs/signing-and-keys'
            );
        }

        $rawKeyId = $config['key_id'] ?? null;
        $keyId = is_string($rawKeyId) && $rawKeyId !== ''
            ? $rawKeyId
            : 'chronicle-dev-key';

        return [
            'enforce_on_boot' => (bool) ($config['enforce_on_boot'] ?? false),
            'active' => $keyId,
            'keys' => [
                $keyId => [
                    'provider' => $config['provider'] ?? Ed25519SigningProvider::class,
                    'algorithm' => 'ed25519',
                    'private_key' => $config['private_key'] ?? null,
                    'public_key' => $config['public_key'] ?? null,
                ],
            ],
        ];
    }
}
