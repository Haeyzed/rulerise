<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    /**
     * Handle PayPal webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $headers = $request->header();

        Log::info('PayPal webhook received', [
            'payload' => json_decode($payload, true),
            'headers' => $headers
        ]);

        try {
            $service = SubscriptionServiceFactory::create('paypal');
            $success = $service->handleWebhook($payload, $headers);

            if ($success) {
                return response()->json(['status' => 'success']);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Failed to process webhook'], 400);
            }
        } catch (\Exception $e) {
            Log::error('PayPal webhook error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
