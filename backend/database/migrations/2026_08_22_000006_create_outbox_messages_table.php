<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_key', 190);
            $table->string('channel', 32);
            $table->string('event_type', 100);
            $table->string('aggregate_type', 120)->nullable();
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('available_at')->nullable()->index();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique('message_key');
            $table->index(['organization_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
