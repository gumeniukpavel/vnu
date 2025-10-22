<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

final class MicrosoftController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('azure')->scopes([
            'openid', 'profile', 'email', 'offline_access', 'User.Read',
            'Calendars.Read', 'Mail.Read', 'Mail.Send', 'Contacts.Read', 'Files.Read',
        ])->redirect();
    }

    public function callback(): RedirectResponse
    {
        $mUser = Socialite::driver('azure')->user();

        $email = $mUser->getEmail() ?: ($mUser->user['preferred_username'] ?? null);
        $name = $mUser->getName() ?: (explode('@', (string)$email)[0] ?? 'Student');

        if (!$email) {
            return redirect()->route('login')->withErrors(['email' => 'Microsoft не повернув email.']);
        }

        $azureId = $mUser->getId();
        $tenantId = $mUser->user['tid'] ?? null;
        $accessToken = $mUser->token;
        $refreshToken = $mUser->refreshToken;
        $expiresIn = (int)($mUser->expiresIn ?? 3600);

        $graph = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me')->json();
        $givenName = $graph['givenName'] ?? null;
        $surname = $graph['surname'] ?? null;
        $jobTitle = $graph['jobTitle'] ?? null;
        $department = $graph['department'] ?? null;
        $phone = $graph['mobilePhone'] ?? null;

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => bcrypt(Str::random(24))]
        );

        $user->fill([
            'azure_id' => $azureId,
            'azure_tenant_id' => $tenantId,
            'azure_access_token' => $accessToken,
            'azure_refresh_token' => $refreshToken,
            'azure_token_expires_at' => now()->addSeconds($expiresIn),
            'azure_provider' => 'azure',
            'given_name' => $givenName,
            'surname' => $surname,
            'job_title' => $jobTitle,
            'department' => $department,
            'phone' => $phone,
        ])->save();

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended('/');
    }
}
