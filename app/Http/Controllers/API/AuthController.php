<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Pendidikan;
use App\Models\Prodi;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request) {}

    public function keycloakLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string|max:8000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            /** @var \SocialiteProviders\Keycloak\Provider $driver */
            $driver = Socialite::driver('keycloak');
            $socialUser = $driver->userFromToken($request->input('access_token'));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token SSO tidak valid'], 401);
        }

        if (!$socialUser || !$socialUser->getId()) {
            return response()->json(['message' => 'Token SSO tidak valid'], 401);
        }

        return $this->issueLocalTokenFromSocialiteUser($socialUser, [
            'sso' => true,
        ]);
    }

    public function keycloakCallbackLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:2048',
            'code_verifier' => 'required|string|min:43|max:128',
            'redirect_uri' => 'required|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Request tidak valid'], 422);
        }

        $tokenUrl = config('services.keycloak.token_url');
        $clientId = config('services.keycloak.client_id');
        $clientSecret = config('services.keycloak.client_secret');

        if (!$tokenUrl || !$clientId || !$clientSecret) {
            return response()->json(['message' => 'Konfigurasi Keycloak belum lengkap'], 500);
        }

        if (!$this->isAllowedRedirectUri($request->input('redirect_uri'))) {
            return response()->json(['message' => 'Redirect URI SSO tidak diizinkan'], 422);
        }

        try {
            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post($tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $request->input('code'),
                    'redirect_uri' => $request->input('redirect_uri'),
                    'code_verifier' => $request->input('code_verifier'),
                ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Login SSO gagal'], 401);
        }

        if (!$tokenResponse->successful()) {
            return response()->json(['message' => 'Login SSO gagal'], 401);
        }

        $tokenPayload = $tokenResponse->json();
        $accessToken = $tokenPayload['access_token'] ?? null;
        if (!$accessToken) {
            return response()->json(['message' => 'Access token tidak ditemukan'], 401);
        }

        try {
            /** @var \SocialiteProviders\Keycloak\Provider $driver */
            $driver = Socialite::driver('keycloak');
            $socialUser = $driver->userFromToken($accessToken);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Token SSO tidak valid'], 401);
        }

        if (!$socialUser || !$socialUser->getId()) {
            return response()->json(['message' => 'Token SSO tidak valid'], 401);
        }

        return $this->issueLocalTokenFromSocialiteUser($socialUser, [
            'sso' => true,
        ]);
    }

    private function issueLocalTokenFromSocialiteUser($socialUser, $extra = [])
    {
        $raw = $socialUser->user ?? [];
        $username = $socialUser->getNickname() ?? null;
        $email = $socialUser->getEmail() ?? null;
        $name = $socialUser->getName() ?? null;
        if (!$username && !$email) {
            return response()->json(['message' => 'Data akun SSO tidak valid'], 401);
        }

        $user = null;
        if ($username) {
            $user = User::where('username', $username)->first();
        }

        if (!$user) {
            $user = $this->findOrCreateUserFromSocialite($socialUser);
        }

        $idSdm = $this->getIdSdm($user);
        $this->getDataUser($user);

        if ($idSdm && $user->id_sdm !== $idSdm) {
            $user->id_sdm = $idSdm;
            $user->save();
        }

        $user->tokens()->where('name', 'auth_token')->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(array_merge([
            'id' => $user->id,
            'id_sdm' => $user->id_sdm,
            'level' => $user->level,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], $extra));
    }

    public function redirectToKeycloak()
    {
        /** @var \SocialiteProviders\Keycloak\Provider $driver */
        $driver = Socialite::driver('keycloak');

        return $driver
            ->redirectUrl(route('keycloak.callback'))
            ->redirect();
    }

    public function handleKeycloakCallback(Request $request)
    {
        try {
            /** @var \SocialiteProviders\Keycloak\Provider $driver */
            $driver = Socialite::driver('keycloak');

            $socialUser = $driver
                ->redirectUrl(route('keycloak.callback'))
                ->user();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Login SSO gagal'], 401);
        }

        if (!$socialUser || !$socialUser->getId()) {
            return response()->json(['message' => 'Token SSO tidak valid'], 401);
        }

        $user = $this->findOrCreateUserFromSocialite($socialUser);
        $user->tokens()->where('name', 'auth_token')->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        $frontendRedirect = $this->buildFrontendRedirectUrl($this->getFrontendRedirectUrl(), $token);
        if ($frontendRedirect) {
            return redirect()->away($frontendRedirect);
        }

        return response()->json([
            'id' => $user->id,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'sso' => true,
        ]);
    }

    private function findOrCreateUserFromSocialite($socialUser)
    {
        $username = $socialUser->getNickname() ?? null;
        $email = $socialUser->getEmail() ?? null;
        $name = $socialUser->getName() ?? null;

        if (!$username && !$email) {
            throw new \RuntimeException('Data akun SSO tidak valid');
        }
        $user = User::updateOrCreate(
            ['username' => $username],
            ['email' => $email]
        );

        return $user;
    }

    private function getFrontendRedirectUrl()
    {
        $allowedUris = config('services.keycloak.redirect_uris', []);
        return is_array($allowedUris) && count($allowedUris) ? $allowedUris[0] : null;
    }

    private function buildFrontendRedirectUrl(?string $url, string $token)
    {
        if (!$url) {
            return null;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'access_token=' . urlencode($token) . '&token_type=Bearer';
    }


    private function isAllowedRedirectUri($redirectUri)
    {
        $allowedUris = config('services.keycloak.redirect_uris', []);
        if (!$allowedUris) {
            return true;
        }

        foreach ($allowedUris as $allowedUri) {
            if (hash_equals(rtrim($allowedUri, '/'), rtrim($redirectUri, '/'))) {
                return true;
            }
        }

        return false;
    }
    private function getDataUser(User $user)
    {
        $nip = $user->username;
        if (!$nip) {
            return null;
        }
        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->withToken(config('services.sdm.key'))
                ->get(config('services.sdm.url') . '/dosen/' . $nip);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? null;
                if (is_array($data)) {
                    $prodi = Prodi::where('nama', $data['akademik']['homebase'])->first();
                    Profile::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'about' => $data['about'] ?? null,
                            'fakultas_id' => $prodi->fakultas_id ?? null,
                            'prodi_id' => $prodi->id ?? null,
                            'foto' => $data['identitas']['foto'] ?? null,
                        ]
                    );
                    User::find($user->id)->update([
                        'name' => $data['identitas']['nama'] ?? null,
                    ]);
                    foreach ($data['pendidikan'] as $item) {
                        if (isset($item['nama_jenjang'])) {
                            Pendidikan::updateOrCreate(
                                [
                                    'nama_jenjang' => $item['nama_jenjang'] ?? null,
                                    'user_id' => $user->id,
                                ],
                                [
                                    'nama_perguruan_tinggi' => $item['nama_perguruan_tinggi'] ?? null,
                                    'program_studi' => $item['program_studi'] ?? null,
                                    'tahun_lulus' => $item['tahun_lulus'] ?? null,
                                ]
                            );
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SDM get pendidikan request failed.', [
                'user_id' => $user->id,
                'nip' => $nip,
                'message' => $e->getMessage(),
            ]);
            error_log('SDM get pendidikan request failed for user_id ' . $user->id . ' with nip ' . $nip . ': ' . $e->getMessage());
        }
    }
    private function getIdSdm(User $user)
    {
        if ($user->id_sdm) {
            return $user->id_sdm;
        }

        $nip = $user->username;
        if (!$nip) {
            return null;
        }

        $token = $this->getSisterToken();
        if (!$token) {
            return null;
        }

        $baseUrl = config('services.sister.url');
        if (!$baseUrl) {
            return null;
        }

        try {
            $response = $this->requestSisterSdm($baseUrl, $token, $nip);

            if ($response->status() === 401) {
                Cache::forget('sister_api_token');
                $token = $this->getSisterToken();

                if ($token) {
                    $response = $this->requestSisterSdm($baseUrl, $token, $nip);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SISTER get id_sdm request failed.', [
                'user_id' => $user->id,
                'nip' => $nip,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (!$response->successful()) {
            Log::warning('SISTER get id_sdm returned an error.', [
                'user_id' => $user->id,
                'nip' => $nip,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return $this->extractIdSdm($response->json());
    }

    private function requestSisterSdm(string $baseUrl, string $token, string $nip)
    {
        return Http::acceptJson()
            ->withToken($token)
            ->timeout(20)
            ->get($baseUrl . '/referensi/sdm', [
                'nip' => $nip,
            ]);
    }

    private function getSisterToken()
    {
        return Cache::remember('sister_api_token', (int) config('services.sister.token_cache_seconds', 3600), function () {
            $baseUrl = config('services.sister.url');
            $username = config('services.sister.username');
            $password = config('services.sister.password');
            $idPengguna = config('services.sister.id_pengguna');

            if (!$baseUrl || !$username || !$password || !$idPengguna) {
                Log::warning('SISTER API credentials are incomplete.');
                return null;
            }

            try {
                $response = Http::acceptJson()
                    ->timeout(20)
                    ->post($baseUrl . '/authorize', [
                        'username' => $username,
                        'password' => $password,
                        'id_pengguna' => $idPengguna,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('SISTER authorize request failed.', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if (!$response->successful()) {
                Log::warning('SISTER authorize returned an error.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $this->extractSisterToken($response->json());
        });
    }

    private function extractSisterToken($payload)
    {
        if (is_string($payload)) {
            return $payload;
        }

        if (!is_array($payload)) {
            return null;
        }

        $candidates = [
            $payload['token'] ?? null,
            $payload['access_token'] ?? null,
            $payload['data']['token'] ?? null,
            $payload['data']['access_token'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function extractIdSdm($payload)
    {
        if (!is_array($payload)) {
            return null;
        }

        $records = $payload['data'] ?? $payload['result'] ?? $payload;
        if (isset($records['id_sdm'])) {
            return $records['id_sdm'];
        }

        if (isset($records[0]) && is_array($records[0])) {
            return $records[0]['id_sdm'] ?? $records[0]['id'] ?? null;
        }

        foreach ($records as $value) {
            if (is_array($value)) {
                $idSdm = $this->extractIdSdm($value);
                if ($idSdm) {
                    return $idSdm;
                }
            }
        }

        return null;
    }
    // method for user logout and delete token
    public function logout()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $user->tokens()->delete();

        return [
            'message' => 'You have successfully logged out and the token was successfully deleted'
        ];
    }
}
