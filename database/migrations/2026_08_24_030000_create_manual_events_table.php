<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('location', 200)->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('all_day')->default(false);
            $table->string('color', 7)->default('#173f35');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_events');
    }
};
