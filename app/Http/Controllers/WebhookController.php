<?php

namespace App\Http\Controllers;

use App\Services\Payment\PayPalPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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
