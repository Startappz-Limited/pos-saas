<?php

namespace App\Console\Commands;

use App\Actions\Platform\CreatePlatformOperator as CreateOperator;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a platform operator (decision 4.7, Phase 5): a login outside every
 * business, which runs the platform console at /platform. Not seeded: run it
 * once per operator.
 */
class CreatePlatformOperator extends Command
{
    protected $signature = 'platform:create-operator
        {--name= : The operator\'s name}
        {--email= : The operator\'s sign-in email}
        {--generate-password : Generate a strong password and print it once, instead of asking}
        {--promote : Make an existing login with no business membership an operator (its password is kept)}';

    protected $description = 'Create a platform operator, outside every business';

    public function handle(CreateOperator $create): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
        $promote = (bool) $this->option('promote');

        $errors = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ])->errors();

        if ($errors->isNotEmpty()) {
            foreach ($errors->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $generated = null;
        $password = null;

        if (! $promote) {
            $password = $this->option('generate-password') ? ($generated = Str::password(24)) : $this->askPassword();

            if ($password === null) {
                return self::FAILURE;
            }
        }

        try {
            $operator = $create->execute($name, $email, $password, $promote);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Platform operator {$operator->email} is ready. They sign in as usual and open /platform.");

        if ($generated !== null) {
            $this->warn("Password (shown once, store it safely): {$generated}");
        }

        return self::SUCCESS;
    }

    /**
     * Asks for the password twice, hidden; null when it is refused.
     */
    private function askPassword(): ?string
    {
        $password = (string) $this->secret('Password (at least 12 characters, with letters, numbers and a symbol)');

        $errors = Validator::make(['password' => $password], [
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ])->errors();

        if ($errors->isNotEmpty()) {
            foreach ($errors->all() as $error) {
                $this->error($error);
            }

            return null;
        }

        if ((string) $this->secret('Password again') !== $password) {
            $this->error('The passwords don\'t match.');

            return null;
        }

        return $password;
    }
}
