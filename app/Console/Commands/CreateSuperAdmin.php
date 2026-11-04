<?php

namespace App\Console\Commands;

use App\Actions\Authorization\GrantSystemRole;
use App\Actions\Fortify\PasswordValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperAdmin extends Command
{
    use PasswordValidationRules;

    protected $signature = 'skuul:create-super-admin {--from-env : Read credentials from private environment variables}';

    protected $description = 'Creates or promotes a platform super admin';

    public function handle(GrantSystemRole $grantSystemRole): int
    {
        if ($this->option('from-env')) {
            if (getenv('TL_BOOTSTRAP_ADMIN') !== '1') {
                $this->error('Bootstrap mode is not enabled.');

                return self::FAILURE;
            }

            $name = (string) getenv('TL_BOOTSTRAP_ADMIN_NAME');
            $email = (string) getenv('TL_BOOTSTRAP_ADMIN_EMAIL');
            $password = (string) getenv('TL_BOOTSTRAP_ADMIN_PASSWORD');
        } else {
            $this->newLine();
            $this->alert('Creating super admin');
            $this->info('Fill in the following details. You can modify the profile later.');
            $name = text('Full name?', required: true);
            $email = text('Email?', required: true);
            $password = password('What is your password?', required: true, placeholder: 'Minimum 8 characters...');
            $confirmation = password('Confirm your password?', required: true);
            if (!hash_equals($password, $confirmation)) {
                $this->error('The password confirmation does not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ], [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $user = User::query()->where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'address' => 'super admin street',
                    'birthday' => '1970-01-01',
                    'nationality' => 'nigeria',
                    'state' => 'lagos',
                    'city' => 'lagos',
                    'gender' => 'male',
                ]);
            }

            $grantSystemRole->grant($user, Role::PlatformAdmin);
            $this->info('Platform admin account is ready.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Could not create the platform admin. Check the application logs.');

            return self::FAILURE;
        }
    }
}
