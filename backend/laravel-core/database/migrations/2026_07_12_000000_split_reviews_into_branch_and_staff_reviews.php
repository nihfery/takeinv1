<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_reviews')) {
            Schema::create('branch_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('provider_branches')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique('booking_id');
                $table->index(['branch_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('staff_reviews')) {
            Schema::create('staff_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('provider_branches')->cascadeOnDelete();
                $table->foreignId('staff_id')->constrained('provider_staffs')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique(['booking_id', 'staff_id']);
                $table->index(['staff_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('reviews')) {
            return;
        }

        DB::table('reviews')->orderBy('id')->each(function (object $review): void {
            $branchReview = [
                'booking_id' => $review->booking_id,
                'customer_id' => $review->customer_id,
                'provider_id' => $review->provider_id,
                'branch_id' => $review->branch_id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ];

            DB::table('branch_reviews')->updateOrInsert(
                ['booking_id' => $review->booking_id],
                $branchReview,
            );

            if ($review->staff_id) {
                DB::table('staff_reviews')->updateOrInsert(
                    [
                        'booking_id' => $review->booking_id,
                        'staff_id' => $review->staff_id,
                    ],
                    [...$branchReview, 'staff_id' => $review->staff_id],
                );
            }
        });

        Schema::drop('reviews');
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('provider_branches')->cascadeOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('provider_staffs')->nullOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique('booking_id');
            });
        }

        if (Schema::hasTable('branch_reviews')) {
            DB::table('branch_reviews')->orderBy('id')->each(function (object $review): void {
                DB::table('reviews')->updateOrInsert(
                    ['booking_id' => $review->booking_id],
                    [
                        'customer_id' => $review->customer_id,
                        'provider_id' => $review->provider_id,
                        'branch_id' => $review->branch_id,
                        'staff_id' => null,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at,
                        'updated_at' => $review->updated_at,
                    ],
                );
            });
        }

        Schema::dropIfExists('staff_reviews');
        Schema::dropIfExists('branch_reviews');
    }
};
