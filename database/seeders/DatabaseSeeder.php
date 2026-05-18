<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GameSeeder::class,
            GameBetTypeSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DevFixturesSeeder::class);

            // DevFixturesSeeder only seeds users + wallets. Draws come
            // from the existing crons; remind devs how to populate them.
            $this->command?->info(
                'Next: php artisan draws:generate-upcoming --days=7  '
                .'(real 2/5/9 PM slots).',
            );
        }
    }
}
