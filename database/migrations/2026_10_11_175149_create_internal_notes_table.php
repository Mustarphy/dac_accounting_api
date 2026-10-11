<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff-only. Never returned by any client-facing endpoint or
     * resource — enforced by simply never including this table/model in
     * anything under Api\V1\Client\*, not by a frontend filter.
     */
    public function up(): void
    {
        Schema::create('internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notes');
    }
};
