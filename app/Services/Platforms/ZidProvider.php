<?php

namespace App\Services\Platforms;

use App\Contracts\PlatformProvider;
use App\Data\StoreProfileData;
use App\Data\UserProfileData;
use App\Models\OauthToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ZidProvider implements PlatformProvider
{
    public const OAUTH_URL = 'https://oauth.zid.sa/';

    public function getAuthorizationUrl(): string
    {
        $state = str()->random(40);
        Cache::put('oauth_zid_state_'.$state, true, now()->addMinutes(15));

        $redirectUri = config('services.zid.redirect') ?: route('auth.callback', ['platform' => 'zid']);

        $queries = http_build_query([
            'client_id' => config('services.zid.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]);

        return self::OAUTH_URL.'oauth/authorize?'.$queries;
    }

    public function handleCallback(string $code, ?string $state = null): User
    {
        if ($state && ! Cache::pull('oauth_zid_state_'.$state)) {
            throw new InvalidArgumentException('المعلومات الأمنية للطلب غير صالحة أو منتهية الصلاحية (Invalid State).');
        }

        $redirectUri = config('services.zid.redirect') ?: route('auth.callback', ['platform' => 'zid']);

        // 1. Get OAuth Tokens from Zid
        $tokensUrl = self::OAUTH_URL.'oauth/token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.zid.client_id'),
            'client_secret' => config('services.zid.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $merchantTokens = $response->json();

        $managerToken = $merchantTokens['access_token'] ?? null;  // X-Manager-Token header
        $authToken = $merchantTokens['authorization'] ?? null;     // Bearer Authorization token
        $refreshToken = $merchantTokens['refresh_token'] ?? null;

        if (empty($authToken) && empty($managerToken)) {
            $errMsg = $merchantTokens['error_description'] ?? ($merchantTokens['error'] ?? 'Failed to obtain access token from Zid.');
            throw new RuntimeException('Zid OAuth error: '.$errMsg);
        }

        $headers = [
            'Authorization' => 'Bearer '.($authToken ?? $managerToken),
            'X-Manager-Token' => $managerToken ?? '',
            'Accept-Language' => 'ar',
            'Accept' => 'application/json',
        ];

        // 2. Fetch User Profile and Store Profile from Zid API
        $userHttpResponse = Http::withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/profile');
        if (! $userHttpResponse->successful()) {
            throw new RuntimeException('Failed to fetch Zid user profile.');
        }

        $storeHttpResponse = Http::withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store');
        if (! $storeHttpResponse->successful()) {
            throw new RuntimeException('Failed to fetch Zid store profile.');
        }

        $userProfile = $userHttpResponse->json();
        $storeProfile = $storeHttpResponse->json();

        $userData = $userProfile['user'] ?? ($userProfile['data']['user'] ?? []);
        $storeData = $storeProfile['store'] ?? ($storeProfile['data']['store'] ?? []);

        if (empty($userData['email'])) {
            throw new RuntimeException('Zid user profile is missing real email field.');
        }

        if (empty($storeData['title']) || empty($storeData['id'])) {
            throw new RuntimeException('Zid store profile is missing real store data.');
        }

        $merchantId = (string) $storeData['id'];
        $userEmail = (string) $userData['email'];
        $userName = (string) ($userData['name'] ?? $storeData['title']);
        $storeName = (string) $storeData['title'];

        // 3. Find or Create User
        $user = User::firstOrCreate(
            ['email' => $userEmail],
            ['name' => $userName]
        );

        // 4. Save/Update OAuth Token in Database
        OauthToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'platform' => 'zid',
                'merchant' => $merchantId,
                'store_name' => $storeName,
                'access_token' => $managerToken,
                'authorization_token' => $authToken,
                'refresh_token' => $refreshToken,
                'expires_at' => isset($merchantTokens['expires_in']) ? now()->addSeconds((int) $merchantTokens['expires_in']) : null,
            ]
        );

        return $user;
    }

    public function refreshToken(OauthToken $oauthToken): OauthToken
    {
        if (empty($oauthToken->refresh_token)) {
            throw new InvalidArgumentException('Refresh token is missing for Zid.');
        }

        $tokensUrl = self::OAUTH_URL.'oauth/token';
        $response = Http::asForm()->post($tokensUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.zid.client_id'),
            'client_secret' => config('services.zid.client_secret'),
            'refresh_token' => $oauthToken->refresh_token,
        ]);

        $merchantTokens = $response->json();

        $managerToken = $merchantTokens['access_token'] ?? null;
        $authToken = $merchantTokens['authorization'] ?? null;
        $refreshToken = $merchantTokens['refresh_token'] ?? null;

        if (empty($managerToken)) {
            throw new RuntimeException('Failed to refresh Zid access token.');
        }

        $oauthToken->update([
            'access_token' => $managerToken,
            'authorization_token' => $authToken ?? $oauthToken->authorization_token,
            'refresh_token' => $refreshToken ?? $oauthToken->refresh_token,
            'expires_at' => isset($merchantTokens['expires_in']) ? now()->addSeconds((int) $merchantTokens['expires_in']) : $oauthToken->expires_at,
        ]);

        return $oauthToken;
    }

    public function getUserProfile(OauthToken $oauthToken): UserProfileData
    {
        $response = $this->apiClient($oauthToken)->get('/managers/account/profile');

        if (! $response->successful()) {
            throw new RuntimeException('فشل جلب بيانات التاجر من منصة زد');
        }

        return UserProfileData::fromZid($response->json());
    }

    public function getStoreProfile(OauthToken $oauthToken): StoreProfileData
    {
        $headers = $this->buildZidHeaders($oauthToken);

        // ─── تنفيذ 5 طلبات متوازية عبر Http::pool ─────────────────────────────
        $responses = Http::pool(fn ($pool) => [
            $pool->as('store')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store'),
            $pool->as('branding')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/branding'),
            $pool->as('social')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/social'),
            $pool->as('localization')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/localization'),
            $pool->as('business')->withHeaders($headers)->get('https://api.zid.sa/v1/managers/account/store/business'),
        ]);

        $storeData = $responses['store']->successful() ? $responses['store']->json() : [];
        $brandingData = $responses['branding']->successful() ? $responses['branding']->json('branding') : null;
        $socialData = $responses['social']->successful() ? $responses['social']->json('social') : null;
        $localizationData = $responses['localization']->successful() ? $responses['localization']->json('localization') : null;
        $businessData = $responses['business']->successful() ? $responses['business']->json('business') : null;

        if (empty($storeData)) {
            throw new RuntimeException('فشل جلب البيانات الأساسية للمتجر من منصة زد');
        }

        return StoreProfileData::fromZid(
            $storeData,
            $brandingData,
            $socialData,
            $localizationData,
            $businessData
        );
    }

    /**
     * تنفيذ طلب البروكسي الديناميكي الشفاف لمنصة زد
     */
    public function proxyRequest(OauthToken $oauthToken, string $method, string $path, array $queryParams, array $body = []): array
    {
        $cleanPath = '/' . ltrim($path, '/');

        // 1. حماية من اجتياز المسار (Path Traversal)
        if (str_contains($cleanPath, '..')) {
            return [
                'status' => 403,
                'body' => ['success' => false, 'message' => 'مسار غير صالح (Path Traversal)'],
            ];
        }

        // 2. حماية قائمة المسارات المسموحة (SSRF Whitelist)
        $allowedPrefixes = ['/products', '/categories', '/orders', '/customers', '/store', '/profile', '/attributes', '/badges', '/locations'];
        $isAllowed = $cleanPath === '/' || $cleanPath === '' || collect($allowedPrefixes)->contains(fn ($prefix) => $cleanPath === $prefix || str_starts_with($cleanPath, $prefix . '/'));

        if (! $isAllowed) {
            return [
                'status' => 403,
                'body' => ['success' => false, 'message' => 'مسار غير مصرح به (Proxy Whitelist)'],
            ];
        }

        $isListPath = in_array($cleanPath, ['/products', '/products/', '/categories', '/categories/']);
        $normalizedQuery = $isListPath ? $this->normalizeQueryParams($queryParams, $cleanPath) : $queryParams;

        $targetPath = $this->mapZidPath($cleanPath);
        $client = $this->apiClient($oauthToken);

        $methodUpper = strtoupper($method);
        $timeout = in_array($methodUpper, ['POST', 'PUT', 'PATCH', 'DELETE']) ? 60 : 45;
        $client = $client->timeout($timeout);

        // ─── Debug Log: رؤية ما سيُرسَل لـ Zid ──────────────────────────────────
        $fullUrl = 'https://api.zid.sa/v1' . $targetPath;
        $storeIdLog = $oauthToken->merchant ?? 'MISSING!';
        $hasAuthToken = ! empty($oauthToken->authorization_token);
        $hasManagerToken = ! empty($oauthToken->access_token);
        \Log::debug("[Zid Proxy 🔵 Request] {$methodUpper} {$fullUrl}", [
            'query_sent'       => $normalizedQuery,
            'store_id'         => $storeIdLog,
            'has_auth_token'   => $hasAuthToken,
            'has_manager_token' => $hasManagerToken,
        ]);

        try {
            if ($methodUpper === 'POST' && (request()->hasFile('image') || request()->hasFile('photo'))) {
                $file = request()->file('image') ?? request()->file('photo');
                $client = $client->attach('image', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                $postFields = [];
                if (request()->has('alt_text')) {
                    $postFields['alt_text'] = request()->input('alt_text');
                }
                $response = $client->post($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $postFields);
            } else {
                $response = match ($methodUpper) {
                    'GET'    => $client->get($targetPath, $normalizedQuery),
                    'POST'   => $client->post($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
                    'PUT'    => $client->put($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
                    'PATCH'  => $client->patch($targetPath . ($normalizedQuery ? '?' . http_build_query($normalizedQuery) : ''), $body),
                    'DELETE' => $client->delete($targetPath, $normalizedQuery),
                    default  => null,
                };
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            \Log::error("[Zid Proxy ❌ Connection Error] {$methodUpper} {$fullUrl}", ['error' => $e->getMessage()]);
            return ['status' => 503, 'body' => ['success' => false, 'message' => 'تعذّر الاتصال بـ Zid API: ' . $e->getMessage()]];
        }

        if (! $response) {
            return [
                'status' => 405,
                'body' => ['success' => false, 'message' => 'طريقة الطلب غير مدعومة'],
            ];
        }

        $statusCode = $response->status();
        $bodyText   = $response->body();
        $jsonData   = $response->json();

        // ─── Debug Log: رؤية ما أرجعه Zid ──────────────────────────────────────
        \Log::debug("[Zid Proxy 🟢 Response] {$methodUpper} {$fullUrl}", [
            'status'     => $statusCode,
            'body_keys'  => is_array($jsonData) ? array_keys($jsonData) : gettype($jsonData),
            'count'      => $jsonData['count'] ?? 'N/A',
            'results_ct' => isset($jsonData['results']) ? count($jsonData['results']) : 'no results key',
            'body_preview' => substr($bodyText, 0, 300),
        ]);

        // 3. اعتراض خطأ 404 لعدم تطابق المنتجات أو القوائم الفرعية في زد وتحويله لرد ناجح فارغ
        if ($statusCode === 404) {
            if ($cleanPath === '/products' || $cleanPath === '/products/') {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [],
                        'pagination' => [
                            'currentPage' => 1,
                            'totalPages' => 1,
                            'totalCount' => 0,
                            'perPage' => 15,
                            'hasNext' => false,
                            'hasPrev' => false,
                        ],
                    ],
                ];
            }
            if (str_contains($cleanPath, '/images') || str_contains($cleanPath, '/custom_options') || str_contains($cleanPath, '/custom_user_input') || str_contains($cleanPath, '/attributes') || str_contains($cleanPath, '/badges') || str_contains($cleanPath, '/locations')) {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [],
                        'results' => [],
                    ],
                ];
            }
        }

        if ($statusCode >= 200 && $statusCode < 300 && is_array($jsonData)) {
            if ($methodUpper === 'GET' && $isListPath) {
                $normalizedResponse = $this->normalizeProxyResponse($jsonData, $cleanPath, $queryParams);
                return ['status' => $statusCode, 'body' => $normalizedResponse];
            }
            return ['status' => $statusCode, 'body' => $jsonData];
        }

        return ['status' => $statusCode, 'body' => $jsonData ?? ['success' => false, 'message' => $bodyText]];
    }

    private function mapZidPath(string $path): string
    {
        $parts = array_values(array_filter(explode('/', $path), fn ($p) => $p !== ''));
        if (empty($parts)) {
            return '/';
        }

        $entity = $parts[0];

        // 1. Products Special Handling
        if ($entity === 'products') {
            if (count($parts) === 1) {
                return '/managers/store/products/';
            }

            $id = $parts[1];
            $rest = implode('/', array_slice($parts, 2));

            if (empty($rest)) {
                return "/managers/store/products/{$id}/view/";
            }

            return "/products/{$id}/{$rest}/";
        }

        // 2. Store-scoped Entities (categories, orders, customers, attributes, locations, badges)
        $storeEntities = ['categories', 'orders', 'customers', 'attributes', 'locations', 'badges'];
        if (in_array($entity, $storeEntities)) {
            $newPath = "/managers/store/{$entity}";

            if (count($parts) > 1) {
                $id = $parts[1];
                $newPath .= "/{$id}";

                $restOfPath = implode('/', array_slice($parts, 2));

                if (empty($restOfPath) && in_array($entity, ['categories', 'orders'])) {
                    $newPath .= '/view';
                } elseif (! empty($restOfPath)) {
                    $newPath .= "/{$restOfPath}";
                }
            }

            if (! str_ends_with($newPath, '/')) {
                $newPath .= '/';
            }

            return $newPath;
        }

        $finalPath = '/' . ltrim($path, '/');
        if (! str_ends_with($finalPath, '/')) {
            $finalPath .= '/';
        }

        return $finalPath;
    }

    private function isSkuPattern(string $str): bool
    {
        $trimmed = trim($str);
        if (empty($trimmed)) {
            return false;
        }

        $hasDashesOrDots = preg_match('/[-.]/', $trimmed);
        $isAlphanumericAndLong = preg_match('/^[a-zA-Z0-9]{6,}$/', $trimmed);
        $isPureNumberAndLong = preg_match('/^[0-9]{8,}$/', $trimmed);

        return (bool) ($hasDashesOrDots || $isAlphanumericAndLong || $isPureNumberAndLong);
    }

    private function normalizeQueryParams(array $query, string $path): array
    {
        $page = (int) ($query['page'] ?? 1);
        $limit = (int) ($query['limit'] ?? 15);
        $normalized = [];

        $normalized['page'] = $page;
        $normalized['page_size'] = $limit;

        if (! empty($query['search'])) {
            $normalized['name'] = $query['search'];
        }

        if (! empty($query['category_id'])) {
            $normalized['categories'] = $query['category_id'];
        }

        if (isset($query['is_published'])) {
            $normalized['is_draft'] = $query['is_published'] === 'true' ? 'false' : 'true';
        }

        $finalQuery = array_merge($query, $normalized);
        unset($finalQuery['limit'], $finalQuery['search'], $finalQuery['category_id'], $finalQuery['is_published']);

        return $finalQuery;
    }

    private function normalizeProxyResponse(array $rawData, string $path, array $originalQuery): array
    {
        $page = (int) ($originalQuery['page'] ?? 1);
        $limit = (int) ($originalQuery['limit'] ?? 15);
        $pathLower = strtolower($path);

        $unifiedData = [];
        if (str_contains($pathLower, 'products')) {
            if (isset($rawData['products'])) {
                if (is_array($rawData['products'])) {
                    $unifiedData = $rawData['products']['results'] ?? (array_is_list($rawData['products']) ? $rawData['products'] : array_values($rawData['products']));
                }
            } elseif (isset($rawData['results']) && is_array($rawData['results'])) {
                $unifiedData = $rawData['results'];
            } elseif (isset($rawData['data']) && is_array($rawData['data'])) {
                $unifiedData = $rawData['data']['products'] ?? ($rawData['data']['results'] ?? $rawData['data']);
            }
        } elseif (str_contains($pathLower, 'categories')) {
            if (isset($rawData['categories'])) {
                $unifiedData = is_array($rawData['categories']) ? ($rawData['categories']['results'] ?? (array_is_list($rawData['categories']) ? $rawData['categories'] : array_values($rawData['categories']))) : [];
            } else {
                $unifiedData = $rawData['results'] ?? ($rawData['data'] ?? []);
            }
        } elseif (str_contains($pathLower, 'orders')) {
            if (isset($rawData['orders'])) {
                $unifiedData = is_array($rawData['orders']) ? ($rawData['orders']['results'] ?? (array_is_list($rawData['orders']) ? $rawData['orders'] : array_values($rawData['orders']))) : [];
            } else {
                $unifiedData = $rawData['results'] ?? ($rawData['data'] ?? []);
            }
        } elseif (str_contains($pathLower, 'customers')) {
            if (isset($rawData['customers'])) {
                $unifiedData = is_array($rawData['customers']) ? ($rawData['customers']['results'] ?? (array_is_list($rawData['customers']) ? $rawData['customers'] : array_values($rawData['customers']))) : [];
            } else {
                $unifiedData = $rawData['results'] ?? ($rawData['data'] ?? []);
            }
        } else {
            $unifiedData = $rawData['data'] ?? ($rawData['results'] ?? ($rawData['categories'] ?? ($rawData['orders'] ?? ($rawData['customers'] ?? []))));
        }

        if (! is_array($unifiedData) || ! array_is_list($unifiedData)) {
            if (is_array($unifiedData)) {
                $unifiedData = array_values($unifiedData);
            } else {
                $unifiedData = [];
            }
        }

        $totalCount = (int) (
            $rawData['count'] ?? 
            ($rawData['products']['count'] ?? 
            ($rawData['total_categories_count'] ?? 
            ($rawData['total_order_count'] ?? count($unifiedData))))
        );
        $totalPages = max(1, (int) ceil($totalCount / $limit));

        $pagination = [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'perPage' => $limit,
            'hasNext' => $page < $totalPages,
            'hasPrev' => $page > 1,
        ];

        return [
            'success' => true,
            'data' => $unifiedData,
            'pagination' => $pagination,
        ];
    }

    /**
     * عميل HTTP الموحد والمبسط لطلبات API زد (HTTP Client Helper)
     */
    private function apiClient(OauthToken $token)
    {
        return Http::baseUrl('https://api.zid.sa/v1')
            ->withHeaders($this->buildZidHeaders($token));
    }

    private function buildZidHeaders(OauthToken $token): array
    {
        $accessToken = $token->authorization_token ?? $token->access_token;
        $managerToken = $token->access_token;
        $storeId = $token->merchant;

        $headers = [
            'Authorization'   => 'Bearer '.$accessToken,
            'X-Manager-Token' => $managerToken ?? '',
            // Access-Token مرادف لـ X-Manager-Token — مطلوب من زد في عمليات الكتابة (POST/PUT/PATCH)
            'Access-Token'    => $managerToken ?? '',
            // مطلوب من زد للحصول على صلاحيات المدير وبياناته الكاملة
            'Role'            => 'Manager',
            'Accept-Language' => 'ar',
            'Accept'          => 'application/json',
        ];

        if (! empty($storeId)) {
            $headers['Store-Id'] = (string) $storeId;
        }

        return $headers;
    }
}
