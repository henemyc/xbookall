<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One global phone identity policy for every GymXBook account type.
 *
 * Phone numbers are written canonically as 10-digit Indian mobile numbers.
 * Legacy values are still detected with an SQL normalized comparison so a new
 * account can never reuse an existing +91/formatted migration record.
 */
class PhoneIdentityService
{
    public const DUPLICATE_MESSAGE = 'This mobile number is already linked to an existing GymXBook account. Use another phone number.';

    public function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $digits)) {
            return null;
        }
        return $digits;
    }

    /** Query every user type and every tenant, including legacy formatted values. */
    public function matchingUsers(string $normalizedPhone, ?int $ignoreUserId = null): Builder
    {
        $query = User::query()->whereRaw(
            "RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) = ?",
            [$normalizedPhone]
        );
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }
        return $query;
    }

    public function isAvailable(string $normalizedPhone, ?int $ignoreUserId = null): bool
    {
        return !$this->matchingUsers($normalizedPhone, $ignoreUserId)->exists();
    }

    /**
     * Returns a canonical number or throws an InvalidArgumentException with a
     * user-safe message. Controllers can turn it into their normal response.
     */
    public function requireAvailable(?string $phone, ?int $ignoreUserId = null): string
    {
        $normalized = $this->normalize($phone);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Phone must be a valid 10-digit Indian mobile number');
        }
        if (!$this->isAvailable($normalized, $ignoreUserId)) {
            throw new \InvalidArgumentException(self::DUPLICATE_MESSAGE);
        }
        return $normalized;
    }
}
