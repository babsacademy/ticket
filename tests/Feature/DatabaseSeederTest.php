<?php

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the database seeder creates the admin and scanner accounts', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'thiambabs77@gmail.com')->first();
    $scanner = User::where('email', 'scanner@scanticket.sn')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->name)->toBe('Babacar Thiam')
        ->and($scanner)->not->toBeNull()
        ->and($scanner->role)->toBe(UserRole::Scanner)
        ->and($scanner->name)->toBe('Agent Scanner');
});

test('the database seeder creates the two real events with their ticket types', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Event::count())->toBe(2)
        ->and(Event::where('status', EventStatus::Published)->count())->toBe(2);

    $wally = Event::where('title', 'Wally B. Seck en Concert')->firstOrFail();
    $youssou = Event::where('title', 'Youssou Ndour — Grand Concert')->firstOrFail();

    expect($wally->slug)->toBe('wally-b-seck-en-concert')
        ->and($wally->venue)->toBe('Stade Léopold Sédar Senghor')
        ->and($wally->city)->toBe('Dakar')
        ->and($wally->capacity)->toBe(500)
        ->and($wally->ticketTypes)->toHaveCount(3)
        ->and($wally->ticketTypes->sum('quantity'))->toBe(500)
        ->and((float) $wally->ticketTypes->firstWhere('name', 'VIP')->price)->toBe(100000.0);

    expect($youssou->venue)->toBe('Grand Théâtre National')
        ->and($youssou->ticketTypes)->toHaveCount(3)
        ->and($youssou->ticketTypes->sum('quantity'))->toBe(500)
        ->and((float) $youssou->ticketTypes->firstWhere('name', 'VIP')->price)->toBe(30000.0);
});

test('the database seeder does not create any orders or tickets', function () {
    $this->seed(DatabaseSeeder::class);

    expect(TicketType::sum('sold_count'))->toBe(0)
        ->and(Order::count())->toBe(0)
        ->and(Ticket::count())->toBe(0);
});
