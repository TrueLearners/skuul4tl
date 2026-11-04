<?php

namespace App\\Http\\Controllers\\Auth;

use App\\Enums\\Role;
use App\\Http\\Controllers\\Controller;
use App\\Models\\User;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Support\\Facades\\Auth;
use Laravel\\Socialite\\Facades\\Socialite;
use Throwable;

class OktaController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(
            filled(config('services.okta.client_id')) && filled(config('services.okta.client_secret')),
            503,
            'Okta sign-in has not been configured yet.'
        );

        return Socialite::driver('okta')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $identity = Socialite::driver('okta')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'email' => 'Okta sign-in could not be completed. Please try again or use your password.',
            ]);
        }

        $claims = $identity->getRaw();
        // Okta tenants can return the sign-in name in preferred_username
        // while the email claim is mapped separately.
        $email = strtolower(trim((string) (
            $identity->getEmail()
            ?: ($claims['email'] ?? $claims['preferred_username'] ?? '')
        )));
        $domainAllowed = filter_var($email, FILTER_VALIDATE_EMAIL)
            && str_ends_with($email, '@truelearners.org');
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$domainAllowed || !$emailVerified) {
            return redirect()->route('login')->withErrors([
                'email' => 'Use a verified @truelearners.org account to sign in with Okta.',
            ]);
        }

        // Match the existing Skuul account by normalized email. Okta's
        // verified claim is authoritative for email verification; it does
        // not create accounts or grant any roles.
        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if (!$user || !$user->hasActiveAccount()) {
            return redirect()->route('login')->withErrors([
                'email' => 'No active Skuul account matches this TrueLearners email. Contact an administrator.',
            ]);
        }

        $isStaff = $user->worksAsStaff()
            || $user->hasRole(Role::PlatformAdmin)
            || $user->hasRole(Role::OrganizationAdmin);

        if (!$isStaff) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your Skuul account is not approved for staff SSO yet. Contact a Skuul administrator.',
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::guard('web')->login($user);
        request()->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
