<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_source_id')->constrained()->cascadeOnDelete();
            $table->string('uid', 512);
            $table->string('occurrence_key', 64);
            $table->text('title');
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('all_day')->default(false);
            $table->string('status', 20)->nullable();
            $table->timestamps();

            $table->unique(['calendar_source_id', 'occurrence_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_events');
    }
};
