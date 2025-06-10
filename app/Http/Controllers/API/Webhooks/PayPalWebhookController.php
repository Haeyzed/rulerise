<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PayPalSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    protected PayPalSubscriptionService $paypalService;

    public function __construct(PayPalSubscriptionService $paypalService)
    {
        $this->paypalService = $paypalService;
    }

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        Log::info('PayPal webhook received', [
            'headers' => $headers,
            'payload_length' => strlen($payload)
        ]);

        try {
            $success = $this->paypalService->handleWebhook($payload, $headers);

            return response('', $success ? 200 : 400);
        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook processing failed', 500);
        }
    }
}
