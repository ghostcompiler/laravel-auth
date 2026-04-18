<?php

namespace GhostCompiler\LaravelAuth\OTP\Transport;

use GhostCompiler\LaravelAuth\Contracts\OtpTransport;
use GhostCompiler\LaravelAuth\Models\OtpChallenge;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class BaseOtpTransport implements OtpTransport
{
    public function verifyCode(Authenticatable $user, OtpChallenge $challenge, string $code, array $context = []): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function postJson(string $url, array $payload, array $options = []): array
    {
        $request = Http::acceptJson();

        if (isset($options['basic'])) {
            [$username, $password] = $options['basic'];
            $request = $request->withBasicAuth($username, $password);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            $request = $request->withHeaders($options['headers']);
        }

        $response = $request->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException('OTP provider request failed.');
        }

        return $response->json() ?? [];
    }
}
