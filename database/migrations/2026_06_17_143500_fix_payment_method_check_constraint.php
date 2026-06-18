<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Laravel's ->change() does NOT remove existing PostgreSQL CHECK constraints.
     * We must drop the old constraint manually and add a new one that includes 'khqr'.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop the old check constraint (created by the original enum migration)
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');

            // Add a new check constraint that includes khqr
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('credit_card', 'paypal', 'google_pay', 'khqr'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');

            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('credit_card', 'paypal', 'google_pay'))");
        }
    }
};
