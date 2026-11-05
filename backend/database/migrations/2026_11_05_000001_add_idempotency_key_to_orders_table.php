<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order remembers the Idempotency-Key it was placed with, so a double submit or a retry
 * returns the same order instead of creating a second one (README section 5).
 *
 * The unique index is what makes this safe under concurrency: when two requests with the same key
 * race, the database lets exactly one insert through. The request hash detects a key reused for a
 * different cart. Both columns are nullable because the header is optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique()->after('customer_id');
            $table->char('idempotency_request_hash', 64)->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'idempotency_request_hash']);
        });
    }
};
