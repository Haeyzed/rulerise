<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\StripeSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    protected StripeSubscriptionService $stripeService;

    public function __construct(StripeSubscriptionService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        Log::info('Stripe webhook received', [
            'headers' => $headers,
            'payload_length' => strlen($payload)
        ]);

        try {
            $success = $this->stripeService->handleWebhook($payload, $headers);

            return response('', $success ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook processing failed', 500);
        }
    }
}
