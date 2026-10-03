<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class ApiAuthenticate
{
    public function handle(Request $r, Closure $next, string $scope)
    {
        $raw = $r->bearerToken();
        abort_unless(is_string($raw) && strlen($raw) <= 200, 401);
        $token = ApiToken::with('user')->where('token_hash', hash('sha256', $raw))->first();
        $u = $token?->user;
        abort_unless($token && $u && $token->expires_at->isFuture() && $u->email_verified_at && ! $u->password_reset_required && hash_equals($token->password_fingerprint, $u->apiCredentialFingerprint()), 401);
        abort_unless(in_array($scope, $token->scopes, true), 403);
        $r->setUserResolver(fn () => $u);
        $token->update(['last_used_at' => now()]);
        $response = $next($r);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
