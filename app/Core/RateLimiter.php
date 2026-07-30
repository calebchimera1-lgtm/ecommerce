<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Login throttling backed by the `login_attempts` table. Keyed on an
 * arbitrary identifier (this app uses the lowercased email) so that
 * repeated failures against one account are throttled even if the
 * attacker rotates IP addresses, while distinct accounts remain
 * unaffected by each other's failures.
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $identifier): bool
    {
        $max = (int) config('security.rate_limit_max_attempts');
        $decayMinutes = (int) config('security.rate_limit_decay_minutes');
        $since = date('Y-m-d H:i:s', time() - $decayMinutes * 60);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total FROM login_attempts
             WHERE identifier = :identifier AND successful = 0 AND attempted_at > :since'
        );
        $stmt->execute(['identifier' => $identifier, 'since' => $since]);

        return (int) $stmt->fetch()['total'] >= $max;
    }

    public static function hit(string $identifier, string $ip, bool $successful): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO login_attempts (identifier, ip_address, successful) VALUES (:identifier, :ip, :successful)'
        );
        $stmt->execute([
            'identifier' => $identifier,
            'ip' => $ip,
            'successful' => $successful ? 1 : 0,
        ]);
    }
}
