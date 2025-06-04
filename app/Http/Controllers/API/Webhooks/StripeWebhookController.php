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
        try {
            // Get the raw payload and headers
            $payload = $request->getContent();
            $headers = $request->headers->all();

            // Normalize header keys to lowercase for consistency
            $normalizedHeaders = [];
            foreach ($headers as $key => $value) {
                $normalizedHeaders[strtolower($key)] = is_array($value) ? $value[0] : $value;
            }

            Log::info('Stripe webhook received', [
                'headers' => $normalizedHeaders,
                'payload_length' => strlen($payload)
            ]);

            // Create the Stripe service and handle the webhook
            $service = SubscriptionServiceFactory::create('stripe');
            $success = $service->handleWebhook($payload, $normalizedHeaders);

            if ($success) {
                Log::info('Stripe webhook processed successfully');
                return response('Webhook processed successfully', 200);
            } else {
                Log::error('Failed to process Stripe webhook');
                return response('Failed to process webhook', 400);
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook error: ' . $e->getMessage(), 500);
        }
    }
}
