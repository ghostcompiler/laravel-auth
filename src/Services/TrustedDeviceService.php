<?php

namespace GhostCompiler\LaravelAuth\Services;

use GhostCompiler\LaravelAuth\Models\TrustedDevice;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str as SupportStr;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    public function trust(Authenticatable $user, Request $request, ?string $deviceName = null): void
    {
        $plainToken = Str::random(64);
        $device = TrustedDevice::query()->create([
            'authenticatable_type' => $user instanceof Model ? $user->getMorphClass() : $user::class,
            'authenticatable_id' => $user->getAuthIdentifier(),
            'name' => $deviceName ?: Str::limit((string) $request->userAgent(), 100, ''),
            'token' => Hash::make($plainToken),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'ip_hash' => $this->hashedIp($request),
            'user_agent_hash' => $this->hashedUserAgent($request),
            'last_used_at' => now(),
            'expires_at' => now()->addDays((int) config('laravel-auth.trusted_devices.ttl_days', 30)),
        ]);

        Cookie::queue(
            config('laravel-auth.trusted_devices.cookie'),
            $device->getKey() . '|' . $plainToken,
            (int) config('laravel-auth.trusted_devices.ttl_days', 30) * 24 * 60,
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    }

    public function isTrusted(Authenticatable $user, Request $request): bool
    {
        $cookie = $request->cookie(config('laravel-auth.trusted_devices.cookie'));

        if (!$cookie || !str_contains($cookie, '|')) {
            return false;
        }

        [$id, $plainToken] = explode('|', $cookie, 2);

        $device = TrustedDevice::query()
            ->whereKey($id)
            ->whereMorphedTo('authenticatable', $user)
            ->where('expires_at', '>', now())
            ->first();

        if (!$device || !Hash::check($plainToken, $device->token)) {
            return false;
        }

        if ((bool) config('laravel-auth.trusted_devices.bind_user_agent', true) && !Hash::check($this->userAgentFingerprint($request), (string) $device->user_agent_hash)) {
            return false;
        }

        if ((bool) config('laravel-auth.trusted_devices.bind_ip', false) && !Hash::check($this->ipFingerprint($request), (string) $device->ip_hash)) {
            return false;
        }

        $device->forceFill([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'ip_hash' => $this->hashedIp($request) ?? $device->ip_hash,
            'user_agent_hash' => $this->hashedUserAgent($request) ?? $device->user_agent_hash,
        ])->save();

        return true;
    }

    public function forgetAll(Authenticatable $user, Request $request): void
    {
        TrustedDevice::query()
            ->whereMorphedTo('authenticatable', $user)
            ->delete();

        $this->forget($request);
    }

    public function forget(Request $request): void
    {
        Cookie::queue(Cookie::forget(config('laravel-auth.trusted_devices.cookie')));
    }

    private function hashedUserAgent(Request $request, bool $hash = true): ?string
    {
        $fingerprint = $this->userAgentFingerprint($request);

        if ($fingerprint === '') {
            return null;
        }

        return $hash ? Hash::make($fingerprint) : $fingerprint;
    }

    private function hashedIp(Request $request, bool $hash = true): ?string
    {
        $fingerprint = $this->ipFingerprint($request);

        if ($fingerprint === '') {
            return null;
        }

        return $hash ? Hash::make($fingerprint) : $fingerprint;
    }

    private function userAgentFingerprint(Request $request): string
    {
        return SupportStr::limit((string) $request->userAgent(), 255, '');
    }

    private function ipFingerprint(Request $request): string
    {
        return (string) ($request->ip() ?? '');
    }
}
