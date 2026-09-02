<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which events a scanner account may see/download tickets for. Without
     * this, any authenticated scanner token could list every published
     * event and download every other event's paid tickets' tokens
     * (IDOR) — there was no relationship at all tying a scanner to the
     * event(s) they're actually working.
     */
    public function up(): void
    {
        Schema::create('scanner_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scanner_event');
    }
};
