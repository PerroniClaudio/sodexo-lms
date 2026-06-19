<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\User;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private const SESSION_USER_ID_KEY = 'onboarding.user_id';

    private const EMAIL_RESEND_LIMIT_SECONDS = 60;

    public function index(): View
    {
        return view('auth.onboarding.index');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_code' => ['required', 'string', 'size:16'],
        ]);

        $user = User::query()
            ->where('fiscal_code', strtoupper(trim($validated['fiscal_code'])))
            ->first();

        if (! $user instanceof User) {
            return back()
                ->withInput()
                ->with('error', __('Non abbiamo trovato alcun account associato al codice fiscale inserito.'));
        }

        $request->session()->put(self::SESSION_USER_ID_KEY, $user->getKey());

        if (blank($user->email)) {
            return redirect()
                ->route('onboarding.email.show')
                ->with('status', __('Abbiamo trovato il tuo account. Inserisci la tua email per proseguire.'));
        }

        if (! $user->hasVerifiedEmail()) {
            $this->sendVerificationEmail($user);

            return redirect()
                ->route('onboarding.email.show')
                ->with('status', __('Ti abbiamo inviato una mail di verifica per completare l\'attivazione.'));
        }

        return redirect()
            ->route('onboarding.email.show')
            ->with('status', __('Il tuo account è già attivo. Se vuoi, possiamo inviarti il recupero password.'));
    }

    public function showEmailForm(Request $request): View|RedirectResponse
    {
        $user = $this->resolveSessionUser($request);

        if (! $user instanceof User) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', __('Per iniziare inserisci prima il tuo codice fiscale.'));
        }

        $mode = $this->resolveMode($request, $user);

        return view('auth.onboarding.email', [
            'user' => $user,
            'mode' => $mode,
            'maskedEmail' => $user->maskedEmail(),
            'resendAvailableIn' => $mode === 'verification_sent'
                ? RateLimiter::availableIn($this->verificationRateLimitKey($user))
                : 0,
        ]);
    }

    public function storeEmail(Request $request): RedirectResponse
    {
        $user = $this->resolveSessionUser($request);

        if (! $user instanceof User) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', __('Per iniziare inserisci prima il tuo codice fiscale.'));
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
        ]);

        $normalizedEmail = strtolower(trim($validated['email']));

        $user->forceFill([
            'email' => $normalizedEmail,
            'email_verified_at' => null,
            'data_richiesta_mail' => now(),
        ])->save();

        RateLimiter::clear($this->verificationRateLimitKey($user));
        $this->sendVerificationEmail($user);

        return redirect()
            ->route('onboarding.email.show')
            ->with('status', __('Ti abbiamo inviato una mail di verifica per completare l\'attivazione.'));
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $this->resolveSessionUser($request);

        if (! $user instanceof User || blank($user->email)) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', __('Per proseguire inserisci prima il tuo codice fiscale.'));
        }

        if (RateLimiter::tooManyAttempts($this->verificationRateLimitKey($user), 1)) {
            return back()->with('error', __('Attendi ancora :seconds secondi prima di inviare una nuova email.', [
                'seconds' => RateLimiter::availableIn($this->verificationRateLimitKey($user)),
            ]));
        }

        $this->sendVerificationEmail($user);

        return back()->with('status', __('Ti abbiamo inviato una nuova mail di verifica.'));
    }

    public function sendPasswordReset(Request $request): RedirectResponse
    {
        $user = $this->resolveSessionUser($request);

        if (! $user instanceof User || blank($user->email) || ! $user->hasVerifiedEmail()) {
            return redirect()
                ->route('onboarding.index')
                ->with('error', __('Non è possibile inviare il recupero password per questo account.'));
        }

        $status = Password::broker(config('fortify.passwords'))
            ->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        return back()->with('status', __('Ti abbiamo inviato una mail per il recupero password.'));
    }

    /**
     * Legacy authenticated onboarding page.
     */
    public function show(Request $request): View
    {
        return view('auth.complete-profile', [
            'user' => $request->user(),
            'availableCountries' => WorldCountry::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Legacy authenticated onboarding completion.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone_prefix' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before:today'],
            'birth_place' => ['required', 'string', 'max:255'],
            'citizenship_country_id' => ['nullable', 'integer', 'exists:world_countries,id'],
            'gender' => ['nullable', 'string', 'in:M,F'],
            'nation' => ['nullable', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $request->user();

        $country = null;
        $region = null;
        $province = null;
        $city = null;

        if (! empty($validated['country'])) {
            $country = WorldCountry::where('code', $validated['country'])->first();
        }
        if (! empty($validated['region'])) {
            $region = WorldDivision::where('name', $validated['region'])->first();
        }
        if (! empty($validated['province'])) {
            $province = Province::where('code', $validated['province'])
                ->orWhere('name', $validated['province'])->first();
        }
        if (! empty($validated['city'])) {
            $city = WorldCity::where('name', $validated['city'])->first();
        }

        $user->update([
            ...$validated,
            'home_country_id' => $country?->id,
            'home_region_id' => $region?->id,
            'home_province_id' => $province?->id,
            'home_city_id' => $city?->id,
        ]);
        $user->markProfileAsCompleted();

        if ($user->hasRole('user')) {
            return redirect()->route('user.courses.index')->with('status', __('Profilo completato con successo!'));
        }
        if ($user->hasRole('admin') || $user->hasRole('superadmin')) {
            return redirect()->route('admin.courses.index')->with('status', __('Profilo completato con successo!'));
        }

        return redirect('/')->with('status', __('Profilo completato con successo!'));
    }

    private function resolveSessionUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_USER_ID_KEY);

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function resolveMode(Request $request, User $user): string
    {
        if ($request->boolean('edit') || blank($user->email)) {
            return 'collect_email';
        }

        if (! $user->hasVerifiedEmail()) {
            return 'verification_sent';
        }

        return 'account_active';
    }

    private function sendVerificationEmail(User $user): void
    {
        if (blank($user->email)) {
            return;
        }

        if (RateLimiter::tooManyAttempts($this->verificationRateLimitKey($user), 1)) {
            return;
        }

        $user->forceFill([
            'data_richiesta_mail' => now(),
        ])->save();

        $user->sendEmailVerificationNotification();
        RateLimiter::hit($this->verificationRateLimitKey($user), self::EMAIL_RESEND_LIMIT_SECONDS);
    }

    private function verificationRateLimitKey(User $user): string
    {
        return 'onboarding-verification:'.$user->getKey();
    }
}
