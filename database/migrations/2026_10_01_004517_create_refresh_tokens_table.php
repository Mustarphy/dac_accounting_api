<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opaque, long-lived refresh tokens — deliberately NOT the JWT itself.
     * Only a hash is stored (never the raw token), so a database leak alone
     * doesn't let an attacker mint sessions. The raw token only ever exists
     * in the HttpOnly cookie on the client and in memory at issuance time.
     */
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->boolean('remember')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
