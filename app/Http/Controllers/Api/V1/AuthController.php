<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AuthLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiLocalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private function issueMobileTokens(User $user): array
    {
        $accessTtlMinutes = max(1, (int) config('services.mobile_auth.ttl_minutes', 1440));
        $refreshTtlMinutes = max(
            $accessTtlMinutes + 1,
            (int) config('services.mobile_auth.refresh_ttl_minutes', 43200),
        );

        $accessExpiresAt = now()->addMinutes($accessTtlMinutes);
        $refreshExpiresAt = now()->addMinutes($refreshTtlMinutes);

        return [
            'token' => $user->createToken('mobile', ['*'], $accessExpiresAt)->plainTextToken,
            'refresh_token' => $user->createToken('mobile-refresh', ['mobile:refresh'], $refreshExpiresAt)->plainTextToken,
            'expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_expires_at' => $refreshExpiresAt->toIso8601String(),
        ];
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '0' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '2519')) {
            return '0' . substr($digits, 3);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '09')) {
            return $digits;
        }

        return $digits;
    }

    public function login(AuthLoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $columns = ['id', 'role_id', 'region_id', 'name', 'phone', 'email', 'password', 'is_active'];
        $passwordVerified = false;

        if (! empty($credentials['email'])) {
            $user = User::query()
                ->select($columns)
                ->where('is_active', true)
                ->where('email', $credentials['email'])
                ->first();
        } else {
            $normalizedPhone = $this->normalizePhone($credentials['phone'] ?? '');
            $phoneCandidates = User::query()
                ->select($columns)
                ->where('is_active', true)
                ->whereIn('phone', array_values(array_unique(array_filter([
                    (string) ($credentials['phone'] ?? ''),
                    $normalizedPhone,
                ]))))
                ->limit(3)
                ->get();

            $passwordMatches = $phoneCandidates
                ->filter(fn (User $candidate) => Hash::check($credentials['password'], $candidate->password))
                ->values();

            $user = $passwordMatches->count() === 1
                ? $passwordMatches->first()
                : null;
            $passwordVerified = $user !== null;
        }

        if (! $user || (! $passwordVerified && ! Hash::check($credentials['password'], $user->password))) {
            return response()->json([
                'message' => ApiLocalizer::message($request, 'invalid_credentials'),
            ], 401);
        }

        $user->loadMissing('role');

        $roleName = strtolower((string) optional($user->role)->name);
        $allowNonFarmerLogin = (bool) config('services.mobile_auth.allow_non_farmer_login', false);
        if (! $allowNonFarmerLogin && $roleName !== 'farmer') {
            return response()->json([
                'message' => ApiLocalizer::message($request, 'farmer_only'),
            ], 403);
        }

        $tokens = $this->issueMobileTokens($user);
        $this->ensureFarmerRegionFromFarm($user);

        return response()->json([
            ...$tokens,
            'user' => new UserResource($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
        ]);

        $normalizedPhone = $this->normalizePhone($validated['phone']);
        if ($normalizedPhone === '') {
            $message = ApiLocalizer::message($request, 'valid_phone_required');
            return response()->json([
                'message' => $message,
                'errors' => ['phone' => [$message]],
            ], 422);
        }

        $phoneExists = User::query()
            ->whereIn('phone', array_values(array_unique([
                (string) $validated['phone'],
                $normalizedPhone,
            ])))
            ->exists();

        if ($phoneExists) {
            $message = ApiLocalizer::message($request, 'phone_registered');
            return response()->json([
                'message' => $message,
                'errors' => ['phone' => [$message]],
            ], 422);
        }

        if (! empty($validated['email']) && User::query()->where('email', $validated['email'])->exists()) {
            $message = ApiLocalizer::message($request, 'email_registered');
            return response()->json([
                'message' => $message,
                'errors' => ['email' => [$message]],
            ], 422);
        }

        $user = User::create([
            'role_id' => Role::farmer()->id,
            'region_id' => $validated['region_id'] ?? null,
            'name' => trim((string) $validated['name']),
            'phone' => $normalizedPhone,
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ])->loadMissing('role');

        $tokens = $this->issueMobileTokens($user);
        $this->ensureFarmerRegionFromFarm($user);

        return response()->json([
            ...$tokens,
            'user' => new UserResource($user),
        ], 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $plainRefreshToken = (string) $validated['refresh_token'];
        $refreshToken = PersonalAccessToken::findToken($plainRefreshToken);

        if (
            $refreshToken === null ||
            ! $refreshToken->can('mobile:refresh') ||
            ! ($refreshToken->tokenable instanceof User)
        ) {
            return response()->json([
                'message' => ApiLocalizer::message($request, 'invalid_refresh_token'),
            ], 401);
        }

        if ($refreshToken->expires_at !== null && $refreshToken->expires_at->isPast()) {
            $refreshToken->delete();

            return response()->json([
                'message' => ApiLocalizer::message($request, 'refresh_token_expired'),
            ], 401);
        }

        /** @var User $user */
        $user = $refreshToken->tokenable->loadMissing('role');
        if (! $user->is_active) {
            $refreshToken->delete();

            return response()->json([
                'message' => ApiLocalizer::message($request, 'user_inactive'),
            ], 403);
        }

        $roleName = strtolower((string) optional($user->role)->name);
        $allowNonFarmerLogin = (bool) config('services.mobile_auth.allow_non_farmer_login', false);
        if (! $allowNonFarmerLogin && $roleName !== 'farmer') {
            $refreshToken->delete();

            return response()->json([
                'message' => ApiLocalizer::message($request, 'farmer_only'),
            ], 403);
        }

        $refreshToken->delete();
        $tokens = $this->issueMobileTokens($user);
        $this->ensureFarmerRegionFromFarm($user);

        return response()->json([
            ...$tokens,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = (string) $request->input('refresh_token', '');
        $request->user()->currentAccessToken()?->delete();
        if ($refreshToken !== '') {
            $refreshModel = PersonalAccessToken::findToken($refreshToken);
            if (
                $refreshModel !== null &&
                $refreshModel->tokenable_type === User::class &&
                (int) $refreshModel->tokenable_id === (int) $request->user()->getKey()
            ) {
                $refreshModel->delete();
            }
        }

        return response()->json([
            'message' => ApiLocalizer::message($request, 'logged_out'),
        ]);
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user()->loadMissing('role');
        $this->ensureFarmerRegionFromFarm($user);

        return new UserResource($user->refresh()->loadMissing('role'));
    }

    private function ensureFarmerRegionFromFarm(User $user): void
    {
        $user->loadMissing('role');
        $roleName = strtolower((string) optional($user->role)->name);
        if ($roleName !== 'farmer' || ! empty($user->region_id)) {
            return;
        }

        $regionId = $user->farms()
            ->whereNotNull('region_id')
            ->orderByDesc('id')
            ->value('region_id');

        if ($regionId === null) {
            return;
        }

        $user->forceFill(['region_id' => (int) $regionId])->save();
    }
}
