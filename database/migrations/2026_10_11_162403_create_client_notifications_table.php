<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A custom, simple notifications table — not Laravel's built-in
     * polymorphic `notifications` table (which stores arbitrary JSON data
     * per notification class and doesn't give us a clean `read_at` /
     * `accounting_request_id` query surface without casting into its
     * data blob). This one is purpose-built for exactly what the client
     * portal needs to list and filter.
     */
    public function up(): void
    {
        Schema::create('client_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->foreignId('accounting_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notifications');
    }
};
