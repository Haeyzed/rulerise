<?php

namespace App\Http\Controllers\API\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Subscription\SubscriptionServiceFactory;
use Illuminate\Http\Request;
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
        Log::info('PayPal webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->getContent()
        ]);

        try {
            $service = SubscriptionServiceFactory::create('paypal');
            $success = $service->handleWebhook(
                $request->getContent(),
                $request->headers->all()
            );

            if (!$success) {
                return response()->json(['error' => 'Webhooks processing failed'], 400);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('PayPal webhook error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
