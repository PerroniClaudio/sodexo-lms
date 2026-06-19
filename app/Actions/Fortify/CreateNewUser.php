<?php

namespace App\Actions\Fortify;

use App\Models\LanguageLevel;
use App\Models\User;
use App\Support\NeedsLanguageLevelVerificationResolver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly NeedsLanguageLevelVerificationResolver $needsLanguageLevelVerificationResolver,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'declared_language_level_id' => LanguageLevel::defaultOrFirst()?->getKey(),
            'needs_language_level_verification' => $this->needsLanguageLevelVerificationResolver
                ->resolve($input['is_foreigner_or_immigrant'] ?? false),
        ]);
    }
}
