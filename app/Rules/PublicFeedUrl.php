<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PublicFeedUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = preg_replace('/^webcal:\/\//i', 'https://', (string) $value);

        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid iCal or webcal URL.');

            return;
        }

        $parts = parse_url($normalized);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            $fail('The :attribute must use http, https, or webcal.');

            return;
        }

        if ($this->isPrivateHost($parts['host'])) {
            $fail('The :attribute must point to a public internet host.');
        }
    }

    private function isPrivateHost(string $host): bool
    {
        if (strtolower($host) === 'localhost' || str_ends_with(strtolower($host), '.localhost')) {
            return true;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if ($addresses === false || $addresses === []) {
            return true;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
    }
}
