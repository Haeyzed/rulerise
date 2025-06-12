<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\TrialEnding;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyTrialEndingSoon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:notify-trial-ending {days=3 : Days before trial ends}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications to users whose trial is ending soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->argument('days');
        $date = Carbon::now()->addDays($days)->startOfDay();
        $nextDay = Carbon::now()->addDays($days + 1)->startOfDay();

        $subscriptions = Subscription::where('is_trial', true)
            ->where('trial_ended', false)
            ->whereNotNull('trial_end_date')
            ->whereBetween('trial_end_date', [$date, $nextDay])
            ->get();

        $this->info("Found {$subscriptions->count()} subscriptions with trials ending in {$days} days.");

        foreach ($subscriptions as $subscription) {
            $this->info("Sending notification for subscription #{$subscription->id} to {$subscription->employer->user->email}");
            $subscription->employer->notify(new TrialEnding($subscription));
        }

        $this->info('Notifications sent successfully!');
        return 0;
    }
}
