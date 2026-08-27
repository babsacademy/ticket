<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()
            ->organizer()
            ->count(3)
            ->create()
            ->each(function (User $organizer): void {
                Event::factory()
                    ->for($organizer, 'organizer')
                    ->published()
                    ->count(3)
                    ->create();
            });
    }
}
