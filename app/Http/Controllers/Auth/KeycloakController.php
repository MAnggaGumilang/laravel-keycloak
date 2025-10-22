<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


class KeycloakController extends Controller
{
    public function redirect()
    {
        // Override konfigurasi untuk mencegah fallback ke realm 'master'
        $cfg = array_filter([
            'client_id'     => env('KEYCLOAK_CLIENT_ID'),
            'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
            'redirect'      => env('KEYCLOAK_REDIRECT_URI'),
            'base_url'      => env('KEYCLOAK_BASE_URL'),
            'host'          => env('KEYCLOAK_BASE_URL'),
            'realms'         => env('KEYCLOAK_REALM'),
            'scope'         => ['openid', 'profile', 'email'],
        ], fn ($v) => $v !== null && $v !== '');
        config(['services.keycloak' => array_merge(config('services.keycloak', []), $cfg)]);

        return Socialite::driver('keycloak')->redirect();
    }

    public function callback(Request $request)
    {

        // Jika Anda sempat kena InvalidStateException saat dev:
    $kcUser = \Laravel\Socialite\Facades\Socialite::driver('keycloak')->stateless()->user();
    // $kcUser = \Laravel\Socialite\Facades\Socialite::driver('keycloak')->user();

    // --- Ambil token response secara aman ---
    // Beberapa versi/driver menyediakan properti ->accessTokenResponseBody,
    // pastikan kita selalu menghasilkan array.
 $tokenResponse = $kcUser->accessTokenResponseBody ?? [];

$idToken      = $tokenResponse['id_token'] ?? null;
$accessToken  = $tokenResponse['access_token'] ?? null;
$refreshToken = $tokenResponse['refresh_token'] ?? null;

session([
    'kc_id_token'     => $idToken,
    'kc_access_token' => $accessToken,
    'kc_refresh_token'=> $refreshToken,
]);

    // --- Ekstrak profil dasar ---
    $email = $kcUser->getEmail();
    $name  = $kcUser->getName() ?: $kcUser->getNickname() ?: $email;

    // --- Provisioning user lokal (password dummy untuk kolom NOT NULL) ---
    $user = \App\Models\User::firstOrCreate(
        ['email' => $email],
        [
            'name'     => $name,
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)),
        ]
    );

    if (! $user->wasRecentlyCreated) {
        $user->update(['name' => $name]);
    }

    // --- Login Laravel ---
    \Illuminate\Support\Facades\Auth::login($user, remember: true);

    // --- Simpan id_token untuk RP-Initiated Logout ---
    // (Bisa null kalau Keycloak tak mengembalikan id_token; aman.)
    $request->session()->put('kc_id_token', $idToken);

    // --- Ambil roles dari token ---
    // Utamakan dari id_token; jika tak ada, coba dari access_token.
    $roles = [];

    $decodeJwt = function (?string $jwt): array {
        if (!$jwt || substr_count($jwt, '.') !== 2) return [];
        [$h, $p] = explode('.', $jwt)[0] ?? null; // dummy to satisfy static analysis
        [$header, $payload] = explode('.', $jwt);
        $json = base64_decode(strtr($payload, '-_', '+/'));
        return json_decode($json ?: '[]', true) ?: [];
    };

    $claims = $decodeJwt($idToken);
    if (!empty($claims)) {
        $roles = $claims['realm_access']['roles'] ?? [];
    }

    if (empty($roles)) {
        $claims = $decodeJwt($accessToken);
        $roles  = $claims['realm_access']['roles'] ?? [];
    }

    $request->session()->put('kc_roles', $roles);

    // --- Arahkan ke halaman tujuan ---
    return redirect()->intended(route('dashboard'));
    }

  public function logout(Request $request)
{
       // Ambil id_token SEBELUM sesi dihapus
    $idTokenHint = session('kc_id_token');

    $base  = rtrim(config('services.keycloak.base_url') ?? env('KEYCLOAK_BASE_URL'), '/');
    $realm = trim(config('services.keycloak.realms') ?? env('KEYCLOAK_REALM'), '/');

    if ($realm === '' || $realm === null) {
        abort(500, 'Keycloak realm is not configured.');
    }

    $endSessionUrl = $base . '/realms/' . $realm . '/protocol/openid-connect/logout';

    // Gunakan nilai ENV yg persis sama dengan yang Anda daftarkan di Keycloak
    $postLogoutUri = env('KEYCLOAK_LOGOUT_REDIRECT_URI', config('app.url'));

    $params = [
        'post_logout_redirect_uri' => $postLogoutUri,
        'client_id'                => config('services.keycloak.client_id'),
    ];
    if (!empty($idTokenHint)) {
        $params['id_token_hint'] = $idTokenHint;
    }

    // Hapus sesi lokal SETELAH param siap
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->away($endSessionUrl . '?' . http_build_query($params));
}

}
