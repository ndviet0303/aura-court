<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_customers')) {
            Schema::create('tenant_customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->boolean('is_guest')->default(false)->index();
                $table->string('name', 160);
                $table->string('phone', 50)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('status', 30)->default('active')->index();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['organization_id', 'user_id']);
                $table->unique(['organization_id', 'phone']);
                $table->index(['organization_id', 'status']);
                $table->index(['organization_id', 'is_guest']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_customers');
    }
};
