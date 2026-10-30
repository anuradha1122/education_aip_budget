<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FirstUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->error(
                'This seeder can only be run in the local environment.'
            );

            return;
        }

        if (User::where('is_admin', true)->exists()) {
            $this->command->error(
                'An administrator already exists. No account was created.'
            );

            return;
        }

        $name = trim((string) $this->command->ask(
            'Enter the administrator name'
        ));

        $email = strtolower(trim((string) $this->command->ask(
            'Enter the administrator email'
        )));

        $password = $this->command->secret(
            'Enter a password'
        );

        $confirmation = $this->command->secret(
            'Confirm the password'
        );

        if ($name === '' || mb_strlen($name) > 255) {
            $this->command->error(
                'Please provide a name with no more than 255 characters.'
            );

            return;
        }

        if (
            strlen($email) > 255
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $this->command->error(
                'Please provide a valid email address.'
            );

            return;
        }

        if (! is_string($password) || strlen($password) < 8) {
            $this->command->error(
                'The password must contain at least 8 characters.'
            );

            return;
        }

        if ($password !== $confirmation) {
            $this->command->error(
                'The passwords do not match.'
            );

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command->error(
                'A user with this email already exists. No changes were made.'
            );

            return;
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();

        $this->command->info(
            "Administrator created successfully: {$user->email}"
        );
    }
}
