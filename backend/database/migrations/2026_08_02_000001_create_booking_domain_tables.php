<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('court_price_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedBigInteger('price');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['court_id', 'weekday', 'starts_at']);
        });

        Schema::create('court_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'starts_at', 'ends_at']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('total_amount');
            $table->string('status')->index();
            $table->dateTime('payment_expires_at')->nullable()->index();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'status']);
        });

        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('unit_price');
            $table->string('status')->index();
            $table->timestamps();
            $table->index(['court_id', 'starts_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('sepay');
            $table->string('external_transaction_id')->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('transfer_code')->nullable()->index();
            $table->string('status')->index();
            $table->json('payload')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending')->index();
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });

        // A slot may have historical rows, but only one held/confirmed row at a time.
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement("CREATE UNIQUE INDEX booking_slots_active_unique ON booking_slots (court_id, starts_at) WHERE status IN ('held', 'confirmed')");
        }
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS booking_slots_active_unique');
        }
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('court_blocks');
        Schema::dropIfExists('court_price_slots');
        Schema::dropIfExists('courts');
    }
};
