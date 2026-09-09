<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('audience', 32)->default('organization'); // 'organization', 'friends'
            $table->text('content');
            $table->boolean('is_hidden')->default(false)->index();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hidden_reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_hidden', 'created_at']);
            $table->index(['user_id', 'is_hidden']);
        });

        Schema::create('feed_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->constrained('feed_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_hidden')->default(false)->index();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hidden_reason')->nullable();
            $table->timestamps();

            $table->index(['feed_post_id', 'is_hidden', 'created_at']);
        });

        Schema::create('feed_reactions', function (Blueprint $table) {
            $table->id();
            $table->string('reactable_type', 120);
            $table->unsignedBigInteger('reactable_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reaction_type', 32); // 'like', 'celebrate', 'love', 'fire'
            $table->timestamps();

            $table->unique(['reactable_type', 'reactable_id', 'user_id', 'reaction_type'], 'feed_reaction_unique');
            $table->index(['reactable_type', 'reactable_id']);
        });

        Schema::create('feed_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type', 120);
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 500);
            $table->string('status', 32)->default('pending'); // 'pending', 'resolved', 'dismissed'
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('action_taken', 64)->nullable(); // 'hidden', 'restored', 'dismissed'
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_reports');
        Schema::dropIfExists('feed_reactions');
        Schema::dropIfExists('feed_comments');
        Schema::dropIfExists('feed_posts');
    }
};
