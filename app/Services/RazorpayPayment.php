<?php
namespace App\Services;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;

class RazorpayPayment implements PaymentGatewayInterface
{
    public function processPayment($amount, array $data, array $config)
    {
        $keyId     = $config['key_id'];
        $keySecret = $config['key_secret'];
        $currency  = strtoupper($config['currency'] ?? 'INR');

        // Razorpay expects amount in paise (smallest currency unit)
        $amountInPaise = (int) round($amount * 100);

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount'          => $amountInPaise,
                'currency'        => $currency,
                'receipt'         => 'receipt_' . uniqid(),
                'payment_capture' => 1,
            ]);

        if ($response->failed()) {
            return redirect()->back()->with('error', 'Razorpay order creation failed: ' . $response->body());
        }

        $order = $response->json();

        return redirect()->route('razorpay.payment.checkout', array_merge($data, [
            'order_id' => $order['id'],
            'currency' => $currency,
        ]));
    }

    public function paymentCancel()
    {
        return redirect(config('app.frontend_url') . '/subscription-plans?status=cancel');
    }
}
