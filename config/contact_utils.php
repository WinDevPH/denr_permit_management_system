<?php
/**
 * Contact number: digits only (no letters). Returns normalized digits string or null if invalid.
 */
function denr_normalize_contact_number(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
        return null;
    }
    if ($raw !== '' && preg_match('/[a-zA-Z]/', $raw)) {
        return null;
    }
    return $digits;
}
