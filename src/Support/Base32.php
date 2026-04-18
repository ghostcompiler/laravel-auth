<?php

namespace GhostCompiler\LaravelAuth\Support;

class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $binary = '';

        foreach (str_split($input) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 5);
        $encoded = '';

        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    public static function decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');

        if ($input === '') {
            return '';
        }

        $binary = '';

        foreach (str_split($input) as $char) {
            $position = strpos(self::ALPHABET, $char);

            if ($position === false) {
                continue;
            }

            $binary .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = str_split($binary, 8);
        $decoded = '';

        foreach ($bytes as $byte) {
            if (strlen($byte) < 8) {
                continue;
            }

            $decoded .= chr(bindec($byte));
        }

        return $decoded;
    }
}
