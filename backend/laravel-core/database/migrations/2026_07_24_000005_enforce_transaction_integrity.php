<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->whereNull('payment_method')
            ->update([
                'payment_method' => DB::raw(
                    "CASE WHEN payment_type = 'pay_at_salon' THEN 'pay_at_salon' ELSE 'manual' END"
                ),
            ]);
        DB::table('bookings')
            ->whereNull('booking_date')
            ->update(['booking_date' => DB::raw('COALESCE(DATE(created_at), CURRENT_DATE)')]);
        DB::table('bookings')
            ->where('total_duration', '<', 0)
            ->update(['total_duration' => 0]);
        DB::table('bookings')
            ->where('total_price', '<', 0)
            ->update(['total_price' => 0]);
        DB::table('bookings')
            ->where('participant_count', '<', 1)
            ->update(['participant_count' => 1]);
        DB::table('booking_services')
            ->where('price', '<', 0)
            ->update(['price' => 0]);
        DB::table('booking_services')
            ->where('estimated_duration', '<=', 0)
            ->update(['estimated_duration' => 30]);
        DB::table('booking_participants')
            ->where('position', '<', 1)
            ->update(['position' => 1]);
        DB::table('booking_participants')
            ->where('total_duration', '<', 0)
            ->update(['total_duration' => 0]);
        DB::table('booking_participants')
            ->where('total_price', '<', 0)
            ->update(['total_price' => 0]);
        DB::table('booking_participant_services')
            ->where('price', '<', 0)
            ->update(['price' => 0]);
        DB::table('booking_participant_services')
            ->where('estimated_duration', '<=', 0)
            ->update(['estimated_duration' => 30]);
        DB::table('payments')
            ->where('amount', '<', 0)
            ->update(['amount' => 0]);
        DB::table('branch_reviews')
            ->whereNotBetween('rating', [1, 5])
            ->update(['rating' => DB::raw('LEAST(5, GREATEST(1, rating))')]);
        DB::table('staff_reviews')
            ->whereNotBetween('rating', [1, 5])
            ->update(['rating' => DB::raw('LEAST(5, GREATEST(1, rating))')]);

        DB::statement('ALTER TABLE payments MODIFY payment_method VARCHAR(40) NOT NULL');
        DB::statement('ALTER TABLE bookings MODIFY booking_date DATE NOT NULL');

        $this->addCheckIfMissing(
            'bookings',
            'bookings_non_negative_totals_check',
            'total_duration >= 0 AND total_price >= 0 AND participant_count >= 1'
        );
        $this->addCheckIfMissing(
            'booking_services',
            'booking_services_snapshot_values_check',
            'price >= 0 AND estimated_duration > 0'
        );
        $this->addCheckIfMissing(
            'booking_participants',
            'booking_participants_totals_check',
            'total_duration >= 0 AND total_price >= 0 AND position >= 1'
        );
        $this->addCheckIfMissing(
            'booking_participant_services',
            'participant_services_snapshot_values_check',
            'price >= 0 AND estimated_duration > 0'
        );
        $this->addCheckIfMissing('payments', 'payments_amount_non_negative_check', 'amount >= 0');
        $this->addCheckIfMissing('branch_reviews', 'branch_reviews_rating_range_check', 'rating BETWEEN 1 AND 5');
        $this->addCheckIfMissing('staff_reviews', 'staff_reviews_rating_range_check', 'rating BETWEEN 1 AND 5');
    }

    public function down(): void
    {
        $this->dropCheckIfExists('staff_reviews', 'staff_reviews_rating_range_check');
        $this->dropCheckIfExists('branch_reviews', 'branch_reviews_rating_range_check');
        $this->dropCheckIfExists('payments', 'payments_amount_non_negative_check');
        $this->dropCheckIfExists('booking_participant_services', 'participant_services_snapshot_values_check');
        $this->dropCheckIfExists('booking_participants', 'booking_participants_totals_check');
        $this->dropCheckIfExists('booking_services', 'booking_services_snapshot_values_check');
        $this->dropCheckIfExists('bookings', 'bookings_non_negative_totals_check');

        DB::statement('ALTER TABLE bookings MODIFY booking_date DATE NULL');
        DB::statement('ALTER TABLE payments MODIFY payment_method VARCHAR(255) NULL');
    }

    private function addCheckIfMissing(string $table, string $constraint, string $expression): void
    {
        if ($this->constraintExists($table, $constraint)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
    }

    private function dropCheckIfExists(string $table, string $constraint): void
    {
        if (! $this->constraintExists($table, $constraint)) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CHECK {$constraint}");
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }
};
