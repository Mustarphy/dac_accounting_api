<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `requested_by_id` is nullable because, in this client-only phase,
     * nothing in the app creates these yet (no staff/admin panel) —
     * records only exist via seeded test data until that panel exists.
     */
    public function up(): void
    {
        Schema::create('information_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->string('status')->default('pending')->index();
            $table->text('response_message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('information_requests');
    }
};
