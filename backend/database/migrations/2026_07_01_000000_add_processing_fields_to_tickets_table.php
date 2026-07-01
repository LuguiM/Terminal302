<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'ticket_image_path')) {
                $table->string('ticket_image_path')->nullable()->after('ticket_plantilla_id');
            }
        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'processing_status')) {
                $table->string('processing_status', 20)->nullable()->after('ticket_image_path');
            }

            if (! Schema::hasColumn('tickets', 'processing_error')) {
                $table->text('processing_error')->nullable()->after('processing_status');
            }

            if (! Schema::hasColumn('tickets', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processing_error');
            }

            if (! Schema::hasColumn('tickets', 'processing_event_path')) {
                $table->string('processing_event_path')->nullable()->after('processed_at');
            }

        });

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'processing_status')) {
                $table->index('processing_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'processing_status')) {
                $table->dropIndex(['processing_status']);
            }

            foreach (['processing_event_path', 'processed_at', 'processing_error', 'processing_status'] as $column) {
                if (Schema::hasColumn('tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
