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

        if (!$endpointSecret) {
            Log::error('Stripe webhook secret not configured');
            return response('Webhook secret not configured', 500);
        }

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
            Log::info('Processing Stripe webhook', [
                'event_id' => $event->id,
                'event_type' => $event->type
            ]);

            $this->stripeService->handleWebhook($event->toArray());

            Log::info('Stripe webhook processed successfully', [
                'event_id' => $event->id,
                'event_type' => $event->type
            ]);

            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Webhook handling failed', 500);
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function handlePayPalWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        // Verify webhook signature in production
        if (config('app.env') !== 'local') {
            if (!$this->paypalService->verifyWebhookSignature($payload, $headers)) {
                Log::error('PayPal webhook signature verification failed');
                return response('Invalid signature', 400);
            }
        }

        try {
            $event = $request->all();

            if (!isset($event['event_type'])) {
                Log::error('PayPal webhook missing event_type', ['payload' => $event]);
                return response('Invalid webhook payload', 400);
            }

            Log::info('Processing PayPal webhook', [
                'event_type' => $event['event_type'],
                'event_id' => $event['id'] ?? 'unknown'
            ]);

            $this->paypalService->handleWebhook($event);

            Log::info('PayPal webhook processed successfully', [
                'event_type' => $event['event_type'],
                'event_id' => $event['id'] ?? 'unknown'
            ]);

            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('PayPal webhook handling failed', [
                'event_type' => $event['event_type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);
            return response('Webhook handling failed', 500);
        }
    }
}
