<?php

use App\Models\User;
use Database\Seeders\UserSeeder;

test('the user seeder creates an admin and demo members', function () {
    $this->seed(UserSeeder::class);

    $admin = User::query()->where('email', 'admin@biwrabid.test')->firstOrFail();

    expect($admin->is_admin)->toBeTrue()
        ->and(User::query()->where('is_admin', false)->count())->toBe(5);
});

test('the user seeder is idempotent', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(6);
});
