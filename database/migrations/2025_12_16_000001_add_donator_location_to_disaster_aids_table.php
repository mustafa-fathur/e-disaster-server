<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('disaster_aids', function (Blueprint $table) {
            $table->string('donator', 100)->nullable()->after('reported_by');
            $table->string('location', 100)->nullable()->after('donator');
        });
    }

    public function down(): void
    {
        Schema::table('disaster_aids', function (Blueprint $table) {
            $table->dropColumn(['donator', 'location']);
        });
    }
};
