<?php

namespace App\Filament\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Illuminate\Auth\Events\Login as LoginEvent;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

class Login extends FilamentLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email or phone')
            ->placeholder('Enter your email address or phone number')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $identifier = trim((string) $data['email']);
        $credentialKey = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if ($credentialKey === 'phone') {
            $digits = preg_replace('/\D+/', '', $identifier) ?? '';

            if ($digits !== '') {
                if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
                    $identifier = '0' . $digits;
                } elseif (strlen($digits) === 12 && str_starts_with($digits, '2519')) {
                    $identifier = '0' . substr($digits, 3);
                } else {
                    $identifier = $digits;
                }
            }
        }

        return [
            $credentialKey => $identifier,
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); // @phpstan-ignore-line
        $credentials = $this->getCredentialsFromFormData($data);

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        // Check panel access before writing anything to the session.
        if ($user instanceof FilamentUser) {
            if (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
                $this->fireFailedEvent($authGuard, $user, $credentials);
                $this->throwFailureValidationException();
            }
        }

        // Write the user ID into the existing session without calling login(),
        // which internally calls session()->regenerate(). Inside a Livewire AJAX
        // request the regenerated session ID is returned in a Set-Cookie header
        // that browsers silently ignore on XHR responses, so the browser follows
        // the redirect with the old cookie and finds no auth data.
        $session = $authGuard->getSession();
        $session->put($authGuard->getName(), $user->getAuthIdentifier());
        $authGuard->setUser($user);

        // "Remember me" — queue the recaller cookie through the cookie jar so it
        // is attached to the final redirect response, not stored in the session.
        $remember = (bool) ($data['remember'] ?? false);
        if ($remember) {
            // Ensure the user has a remember token; generate one if missing.
            if (empty($user->getRememberToken())) {
                $user->setRememberToken(\Illuminate\Support\Str::random(60));
                $user->save();
            }

            $recallerValue = implode('|', [
                $user->getAuthIdentifier(),
                $user->getRememberToken(),
                $user->getAuthPassword(),
            ]);

            cookie()->queue(
                cookie()->forever(
                    $authGuard->getRecallerName(),
                    $recallerValue,
                    '/',
                    null,
                    false,
                    true,  // httpOnly
                    false,
                    'lax',
                )
            );
        }

        event(new LoginEvent($authGuard->getName(), $user, $remember));

        return app(LoginResponse::class);
    }
}
