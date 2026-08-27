<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        $usedSlugs = [];

        DB::table('events')->orderBy('id')->get(['id', 'title'])->each(function (object $event) use (&$usedSlugs): void {
            $base = Str::slug($event->title);
            $slug = $base;
            $suffix = 1;

            while (in_array($slug, $usedSlugs, strict: true)) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }

            $usedSlugs[] = $slug;

            DB::table('events')->where('id', $event->id)->update(['slug' => $slug]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
