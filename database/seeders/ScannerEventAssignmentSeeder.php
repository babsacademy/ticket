<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ScannerEventAssignmentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Assign every demo scanner account to every seeded event, so the
     * scanner app has something to list/download against out of the box.
     * Runs after RealEventsSeeder; silently does nothing if there are no
     * scanner accounts or events yet (e.g. a bare test database).
     */
    public function run(): void
    {
        $scanners = User::query()->where('role', UserRole::Scanner)->get();
        $eventIds = Event::query()->pluck('id');

        if ($scanners->isEmpty() || $eventIds->isEmpty()) {
            return;
        }

        foreach ($scanners as $scanner) {
            $scanner->assignedEvents()->syncWithoutDetaching($eventIds);
        }
    }
}
