<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matches the frontend's existing contract (POST /auth/email/verify
     * with a single opaque `token`), rather than Laravel's default
     * signed-URL verification route — the frontend already ships against
     * that contract and this backend is built to match it, not the other
     * way round. Only the hash is stored, same reasoning as refresh_tokens.
     */
    public function up(): void
    {
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
