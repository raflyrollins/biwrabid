<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * `UserSeeder` goes first and `AuctionChatSeeder` second on purpose: the
     * auction demo reuses the accounts `UserSeeder` creates, so running it on
     * its own would leave two sets of users with the same emails.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AuctionChatSeeder::class,
        ]);
    }
}
