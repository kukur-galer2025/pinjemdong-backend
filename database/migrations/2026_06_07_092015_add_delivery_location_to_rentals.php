<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->double('delivery_latitude')->nullable()->after('delivery_address');
            $table->double('delivery_longitude')->nullable()->after('delivery_latitude');
            $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('delivery_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['delivery_latitude', 'delivery_longitude', 'delivery_distance_km']);
        });
    }
};
