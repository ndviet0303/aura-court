<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receiving_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 32)->default('sepay');
            $table->uuid('routing_id')->unique();
            $table->string('bank_code', 32);
            $table->text('account_name_ciphertext');
            $table->text('account_number_ciphertext');
            $table->char('account_last_four', 4);
            $table->string('status', 32)->default('draft')->index();
            $table->text('webhook_secret_ciphertext')->nullable();
            $table->unsignedInteger('credential_version')->default(0);
            $table->timestamp('owner_attested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'venue_id', 'status']);
        });

        Schema::create('receiving_connection_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_connection_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48);
            $table->unsignedInteger('credential_version')->default(0);
            $table->char('event_fingerprint', 64)->nullable();
            $table->longText('evidence_ciphertext')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['receiving_connection_id', 'event_fingerprint']);
            $table->index(['receiving_connection_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_connection_evidence');
        Schema::dropIfExists('receiving_connections');
    }
};
