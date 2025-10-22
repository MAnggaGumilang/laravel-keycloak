<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

Route::get('/ping', fn() => ['pong' => true]);

Route::get('/secure-data', function (Request $request) {
    $authHeader = $request->header('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return response()->json(['error' => 'Missing token'], 401);
    }

    $jwt = substr($authHeader, 7);

    try {
        // 1) Ambil JWKS dari Keycloak
        $jwksUrl = 'http://localhost:8080/realms/laravel-sso-lab/protocol/openid-connect/certs';
        $jwksJson = @file_get_contents($jwksUrl);
        if ($jwksJson === false) {
            return response()->json(['error' => 'JWKS fetch failed'], 500);
        }

        $jwks = json_decode($jwksJson, true);
        if (!is_array($jwks)) {
            return response()->json(['error' => 'Invalid JWKS format'], 500);
        }

        // 2) Ubah JWKS menjadi array Key (keyed by kid)
        $keys = JWK::parseKeySet($jwks); // array: ['<kid>' => Key, ...]
        if (empty($keys)) {
            return response()->json(['error' => 'No keys in JWKS'], 500);
        }

        // 3) Verifikasi dan decode JWT
        //    Di v6, Anda bisa langsung memberikan array Key; JWT akan pilih berdasarkan 'kid'
        $decoded = JWT::decode($jwt, $keys);

        // 4) Kembalikan data protected
        return response()->json([
            'message' => 'Access granted to protected resource',
            'user'    => $decoded->email ?? $decoded->preferred_username ?? null,
            'roles'   => $decoded->realm_access->roles ?? [],
            'iat'     => $decoded->iat ?? null,
            'exp'     => $decoded->exp ?? null,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error'   => 'Invalid or expired token',
            'message' => $e->getMessage(),
        ], 401);
    }
});
