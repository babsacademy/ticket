<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * qr_image_path used to point at a QR PNG written to the *public* disk
     * (storage/app/public/tickets/{id}.png) — anyone who could guess or
     * enumerate that URL could view, and reuse, another buyer's ticket QR
     * without ever being authenticated. The QR is now only ever rendered
     * on the fly from Ticket::fullToken() (CheckoutController::ticketPdf()),
     * so nothing reads this column anymore.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('qr_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('qr_image_path')->nullable()->after('signature');
        });
    }
};
