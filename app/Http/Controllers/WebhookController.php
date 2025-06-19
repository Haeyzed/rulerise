<?php

namespace App\Http\Controllers;

use App\Services\Payment\PayPalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use App\Services\Payment\StripePaymentService;

/**
 * Webhook Controller
 *
 * Handles incoming webhooks from payment providers
 * with proper validation and error handling.
 */
class WebhookController extends Controller
{
    public function __construct(
        private PayPalPaymentService $paypalService
    ) {}

    /**
     * Handle PayPal webhooks with enhanced security and logging
     */
    public function handlePayPalWebhook(Request $request): Response
    {
        $startTime = microtime(true);
        $eventId = $request->header('PAYPAL-TRANSMISSION-ID', 'unknown');

        Log::info('PayPal webhook received', [
            'event_id' => $eventId,
            'event_type' => $request->input('event_type'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            // TODO: Implement PayPal webhook signature verification for production
            // $this->verifyPayPalWebhookSignature($request);

            $event = $request->all();

            if (empty($event['event_type'])) {
                Log::warning('PayPal webhook missing event_type', [
                    'event_id' => $eventId,
                    'payload' => $event
                ]);
                return response('Missing event_type', 400);
            }

            $this->paypalService->handleWebhook($event);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('PayPal webhook processed successfully', [
                'event_id' => $eventId,
                'event_type' => $event['event_type'],
                'processing_time_ms' => $processingTime
            ]);

            return response('Webhook handled', 200);

        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('PayPal webhook handling failed', [
                'event_id' => $eventId,
                'event_type' => $request->input('event_type'),
                'error' => $e->getMessage(),
                'processing_time_ms' => $processingTime,
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook handling failed', 500);
        }
    }

    /**
     * Handle Stripe webhooks with enhanced security and logging
     */
    public function handleStripeWebhook(Request $request): Response
    {
        $startTime = microtime(true);
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.endpoint_secret');

        Log::info('Stripe webhook received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_signature' => !empty($sigHeader),
            'has_endpoint_secret' => !empty($endpointSecret),
        ]);

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // Only verify signature if endpoint secret is configured
            if (!empty($endpointSecret) && !empty($sigHeader)) {
                $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            } else {
                // For development/testing - parse event without signature verification
                $event = json_decode($payload, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \UnexpectedValueException('Invalid JSON payload');
                }

                Log::warning('Stripe webhook processed without signature verification', [
                    'reason' => empty($endpointSecret) ? 'No endpoint secret configured' : 'No signature header'
                ]);
            }

            // Create a StripePaymentService instance to handle the webhook
            $stripeService = app(StripePaymentService::class);
            $stripeService->handleWebhook($event);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Stripe webhook processed successfully', [
                'event_type' => $event['type'] ?? 'unknown',
                'event_id' => $event['id'] ?? 'unknown',
                'processing_time_ms' => $processingTime
            ]);

            return response('Webhook handled', 200);

        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Stripe webhook invalid payload', [
                'error' => $e->getMessage(),
                'payload_length' => strlen($payload)
            ]);
            return response('Invalid payload', 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Stripe webhook invalid signature', [
                'error' => $e->getMessage(),
                'has_endpoint_secret' => !empty($endpointSecret),
                'signature_header' => $sigHeader ? 'present' : 'missing'
            ]);
            return response('Invalid signature', 400);
        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Stripe webhook handling failed', [
                'error' => $e->getMessage(),
                'processing_time_ms' => $processingTime,
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook handling failed', 500);
        }
    }

    /**
     * Verify PayPal webhook signature (implement for production)
     */
    private function verifyPayPalWebhookSignature(Request $request): void
    {
        // TODO: Implement PayPal webhook signature verification
        // This is crucial for production security

        $headers = [
            'PAYPAL-AUTH-ALGO' => $request->header('PAYPAL-AUTH-ALGO'),
            'PAYPAL-TRANSMISSION-ID' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'PAYPAL-CERT-ID' => $request->header('PAYPAL-CERT-ID'),
            'PAYPAL-TRANSMISSION-SIG' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'PAYPAL-TRANSMISSION-TIME' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        // Verify signature using PayPal's webhook verification API
        // throw new \Exception('Invalid webhook signature') if verification fails
    }

    /**
     * Health check endpoint for webhook monitoring
     */
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    }
}
