<?php

namespace GhostCompiler\LaravelAuth\Services;

use GhostCompiler\LaravelAuth\Support\Base32;

class TotpService
{
    public function generateSecret(int $bytes = 20): string
    {
        return Base32::encode(random_bytes($bytes));
    }

    public function verify(string $secret, string $code, int $window, int $digits, int $period): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (!preg_match('/^\d+$/', $code)) {
            return false;
        }

        $counter = (int) floor(time() / $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->at($secret, $counter + $offset, $digits), $code)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $label, string $secret, string $issuer, int $digits, int $period): string
    {
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'digits' => $digits,
            'period' => $period,
        ]);

        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label) . '?' . $query;
    }

    private function at(string $secret, int $counter, int $digits): string
    {
        $binarySecret = Base32::decode($secret);
        $packedCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $packedCounter, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }
}
