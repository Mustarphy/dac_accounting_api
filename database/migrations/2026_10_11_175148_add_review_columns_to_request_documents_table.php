<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only meaningful for client-submitted documents (source=client) —
     * DAC-issued deliverables are never "reviewed". Nullable throughout:
     * null review_status means "not yet reviewed", not "rejected".
     */
    public function up(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->string('review_status')->nullable()->after('size')->index();
            $table->foreignId('reviewed_by_id')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('request_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_id');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_notes']);
        });
    }
};
