<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 07 D8, FR-118: one conversation per pair (customer–customer, customer–venue, organization owner–platform
 * admin). The venue, owner and admin sides are shared, so access is checked per request instead of stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('pair_key', 60)->unique();
            $table->foreignId('user_a_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index('user_a_id');
            $table->index('user_b_id');
            $table->index('venue_id');
            $table->index('organization_id');
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // BR-158: who actually sent it, also when the message shows under the venue or organization name.
            $table->foreignId('sender_user_id')->constrained('users');
            $table->string('sender_side', 10);
            $table->text('body')->nullable();
            $table->text('image_url')->nullable();
            // FR-111: what the gifted code offered when it was sent; sending never records a voucher use.
            $table->json('voucher')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
