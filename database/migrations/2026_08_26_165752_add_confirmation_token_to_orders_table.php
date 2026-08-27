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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token')->nullable()->after('id');
        });

        DB::table('orders')->orderBy('id')->pluck('id')->each(function (int $id): void {
            DB::table('orders')->where('id', $id)->update(['confirmation_token' => Str::random(48)]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('confirmation_token');
        });
    }
};
