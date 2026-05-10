<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

final class SupportAddressMatcher
{
    /**
     * @param  list<string>  $needles  Normalized lower-case support addresses
     * @param  list<string>  $haystackEmails  Recipient addresses (lower-case)
     */
    public function matches(array $needles, array $haystackEmails): bool
    {
        if ($needles === [] || $haystackEmails === []) {
            return false;
        }

        foreach ($haystackEmails as $email) {
            foreach ($needles as $needle) {
                if ($email === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function normalizedAddressesFromConfig(): array
    {
        $raw = config('gaze-ghostwriter.support_addresses', []);

        return array_values(array_unique(array_filter(array_map(
            static fn (string $a): string => strtolower(trim($a)),
            is_array($raw) ? $raw : []
        ))));
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public function normalizeList(array $emails): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $e): string => strtolower(trim($e)),
            $emails
        ))));
    }
}
