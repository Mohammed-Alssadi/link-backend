<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('salla'); // 'salla' or 'zid'
            $table->string('merchant')->nullable();       // Merchant/Store ID
            $table->string('store_name')->nullable();     // Store name
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->text('authorization_token')->nullable(); // Zid Manager Token (authorization)
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_tokens');
    }
};
