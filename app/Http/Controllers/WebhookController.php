<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Payment\StripePaymentService;
use App\Services\Payment\PayPalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    public function __construct(
        private StripePaymentService $stripeService,
        private PayPalPaymentService $paypalService
    ) {}

    /**
     * Handle Stripe webhooks
     */
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid Stripe webhook payload', ['error' => $e->getMessage()]);
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        try {
            $this->stripeService->handleWebhook($event->toArray());
            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            return response('Webhook handling failed', 500);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function handlePayPalWebhook(Request $request): Response
    {
        // PayPal webhook verification would go here
        // For production, implement proper webhook signature verification

        try {
            $event = $request->all();
            $this->paypalService->handleWebhook($event);
            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('PayPal webhook handling failed', [
                'event' => $request->all(),
                'error' => $e->getMessage()
            ]);
            return response('Webhook handling failed', 500);
        }
    }
}
