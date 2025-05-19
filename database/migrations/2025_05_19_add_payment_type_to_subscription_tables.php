<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SubscriptionPlan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('payment_type')->default(SubscriptionPlan::PAYMENT_TYPE_RECURRING)->after('is_featured');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_type')->nullable()->after('is_active');
            // Make end_date nullable for one-time subscriptions
            $table->dateTime('end_date')->nullable()->change();
        });

        // Update existing subscription plans
//        DB::table('subscription_plans')
//            ->where('name', 'like', '%20 Resume Package%')
//            ->update(['payment_type' => SubscriptionPlan::PAYMENT_TYPE_ONE_TIME]);
//
//        DB::table('subscription_plans')
//            ->where('name', 'like', '%Unlimited Resume Access%')
//            ->update(['payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING]);
//
//        // Update existing subscriptions based on their plan
//        $subscriptions = DB::table('subscriptions')->get();
//        foreach ($subscriptions as $subscription) {
//            $plan = DB::table('subscription_plans')->where('id', $subscription->subscription_plan_id)->first();
//            if ($plan) {
//                DB::table('subscriptions')
//                    ->where('id', $subscription->id)
//                    ->update(['payment_type' => $plan->payment_type]);
//            }
//        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('payment_type');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('payment_type');
            // Revert end_date to non-nullable
            $table->dateTime('end_date')->nullable(false)->change();
        });
    }
};
