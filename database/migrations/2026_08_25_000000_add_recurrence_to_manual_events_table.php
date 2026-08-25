<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_events', function (Blueprint $table) {
            $table->string('recurrence_type', 16)->nullable()->after('all_day');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_type');
            $table->json('recurrence_weekdays')->nullable()->after('recurrence_interval');
            $table->date('recurrence_until')->nullable()->after('recurrence_weekdays');
        });
    }

    public function down(): void
    {
        Schema::table('manual_events', function (Blueprint $table) {
            $table->dropColumn(['recurrence_type', 'recurrence_interval', 'recurrence_weekdays', 'recurrence_until']);
        });
    }
};
