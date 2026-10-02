<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * The password shared by every seeded local account.
     */
    private const PASSWORD = 'password';

    /**
     * Seed the administrator account and a handful of demo members.
     */
    public function run(): void
    {
        $admin = [
            'name' => 'Admin Biwrabid',
            'email' => 'admin@biwrabid.test',
        ];

        $members = [
            ['name' => 'Budi Santoso', 'email' => 'budi@biwrabid.test'],
            ['name' => 'Siti Aminah', 'email' => 'siti@biwrabid.test'],
            ['name' => 'Rizky Pratama', 'email' => 'rizky@biwrabid.test'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi@biwrabid.test'],
            ['name' => 'Agus Setiawan', 'email' => 'agus@biwrabid.test'],
        ];

        $emails = [$admin['email'], ...array_column($members, 'email')];

        User::query()->whereIn('email', $emails)->delete();

        User::factory()->admin()->create([
            ...$admin,
            'password' => self::PASSWORD,
        ]);

        foreach ($members as $member) {
            User::factory()->create([
                ...$member,
                'password' => self::PASSWORD,
            ]);
        }
    }
}
