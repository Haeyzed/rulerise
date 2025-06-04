<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook events
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $headers = $request->header();

        Log::info('Stripe webhook received', [
            'headers' => $headers,
        ]);

        try {
            $service = SubscriptionServiceFactory::create('stripe');
            $success = $service->handleWebhook($payload, $headers);

            if ($success) {
                return response('Webhook processed successfully', 200);
            } else {
                return response('Webhook processing failed', 422);
            }
        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook processing error: ' . $e->getMessage(), 500);
        }
    }
}
