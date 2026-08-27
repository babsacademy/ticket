<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buyers = User::factory()->count(10)->create();

        Event::all()->each(function (Event $event) use ($buyers): void {
            collect(range(1, 5))->each(function () use ($event, $buyers): void {
                Order::factory()
                    ->for($event)
                    ->for($buyers->random())
                    ->paid()
                    ->create();
            });
        });
    }
}
