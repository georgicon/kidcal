<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('url');
            $table->text('normalized_url');
            $table->string('color', 7)->default('#2563eb');
            $table->boolean('enabled')->default(true);
            $table->string('sync_status', 20)->default('pending');
            $table->text('sync_error')->nullable();
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sources');
    }
};
