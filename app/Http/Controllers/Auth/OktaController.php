<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
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

        $email = strtolower(trim((string) $identity->getEmail()));
        $domainAllowed = filter_var($email, FILTER_VALIDATE_EMAIL)
            && str_ends_with($email, '@truelearners.org');
        $emailVerified = ($identity->user['email_verified'] ?? false) === true;

        if (!$domainAllowed || !$emailVerified) {
            return redirect()->route('login')->withErrors([
                'email' => 'Use a verified @truelearners.org account to sign in with Okta.',
            ]);
        }

        // SSO never creates accounts or grants roles. An administrator must
        // provision the account and its staff permissions in Skuul first.
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $isStaff = $user && (
            $user->worksAsStaff()
            || $user->hasRole(Role::PlatformAdmin)
            || $user->hasRole(Role::OrganizationAdmin)
        );

        if (!$user || !$user->hasActiveAccount() || !$user->hasVerifiedEmail() || !$isStaff) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your TrueLearners account is not approved for staff SSO yet. Contact a Skuul administrator.',
            ]);
        }

        Auth::guard('web')->login($user);
        request()->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
