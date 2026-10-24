<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customized_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('unique_code', 10)->unique();
            $table->json('custom_data');
            $table->timestamp('order_clicked_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->boolean('is_locked_by_admin')->default(false);
            $table->timestamp('last_accessed_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customized_previews');
    }
};
