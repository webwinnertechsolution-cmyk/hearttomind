<?php

namespace App\Http\Controllers\API;

use App\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Repositories\SubscriptionRepository;
use App\Http\Resources\SubscriptionResouorce;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\PaymentGateway;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class SubscriptionController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {


    }
    public function index()
    {
        $subscriptionPlans = (new SubscriptionPlanRepository())->getAll();

        return $this->json('Subscription plan list', [
            'plans' => SubscriptionPlanResource::collection($subscriptionPlans)
        ]);
    }

     public function buySubscription(SubscribeRequest $request)
    {
        $user = auth()->user();

        $plan = (new SubscriptionRepository())->storeByRequest($request);


        $user = auth()->user();
        $amount = $plan->amount;

        $successUrl = route('payment.success');
        $cancelUrl  = route('payment.cancel');
        $fairUrl = route('aamrpay.payment.fail');


        switch ($request->payment_method) {
            case 'stripe':

                $paymentMethod = PaymentGateway::where('name','stripe')->first();

                $publishedKey =json_decode($paymentMethod->config)->publishable_key;
                $secretKey = json_decode($paymentMethod->config)->secret_key;

                if (!$publishedKey || !$secretKey) {
                    return $this->json('Stripe credentials not configured.' ,[], 500);
                }

                Stripe::setApiKey($secretKey);

                try {
                    $callbackUrl = $successUrl . '?' . http_build_query([
                        'payment' => 'stripe',
                        'plan_id' => $request->plan_id,
                        'user_id' => $user->id
                    ]);

                    $session = Session::create([
                        'payment_method_types' => ['card'],
                        'line_items' => [[
                            'price_data' => [
                                'currency' => 'usd',
                                'product_data' => ['name' => 'Plan #' . $request->plan_id],
                                'unit_amount' => (int)($amount * 100),
                            ],
                            'quantity' => 1,
                        ]],
                        'mode' => 'payment',
                        'success_url' => $callbackUrl . '&session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url' => $cancelUrl . '?payment=stripe',
                        'metadata' => [
                            'plan_id' => $request->plan_id,
                            'user_id' => $user->id,
                        ],
                    ]);

                      return $this->json('Redirecting to payment gateway.' ,[
                            'redirectUrl' => $session->url
                        ], 201);

                } catch (\Exception $e) {
                    return $this->json('Stripe error: ' . $e->getMessage() ,[], 500);
                }
            case 'paypal':
                $paymentMethod = PaymentGateway::where('name', 'paypal')->first();

                $clientId = json_decode($paymentMethod->config)->client_id;
                $secret = json_decode($paymentMethod->config)->client_secret;

                $environment = new SandboxEnvironment($clientId, $secret);
                $client = new PayPalHttpClient($environment);

                $orderData = [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => 'default',
                            'amount' => [
                                'currency_code' => 'USD',
                                'value' => number_format($amount, 2),
                            ],
                            'description' => 'Plan #' . $request->plan_id,
                            'custom_id' => $request->plan_id,
                        ]
                    ],
                    'application_context' => [
                        'return_url' => $successUrl . '?' . http_build_query([
                            'payment' => 'paypal',
                            'plan_id' => $request->plan_id,
                            'user_id' => $user->id,
                        ]),
                        'cancel_url' => $cancelUrl . '?payment=paypal',
                    ]
                ];

                $request = new OrdersCreateRequest();
                $request->body = $orderData;


                try {
                    $response = $client->execute($request);
                    $approvalUrl = '';

                    foreach ($response->result->links as $link) {
                        if ($link->rel == 'approve') {
                            $approvalUrl = $link->href;
                            break;
                        }
                    }

                    return $this->json('Redirecting to payment gateway.', [
                        'redirectUrl' => $approvalUrl
                    ], 201);

                } catch (\Exception $e) {
                    return $this->json('PayPal error: ' . $e->getMessage(), [], 500);
                }
            case 'twocheckout':
                $paymentMethod = PaymentGateway::where('name', 'twocheckout')->first();
                $accountNumber = json_decode($paymentMethod->config)->merchant_id;

                $paymentParams = [
                    'sid' => $accountNumber,
                    'mode' => 'sandbox',
                    'li_0_name' => 'Subscription Plan',
                    'li_0_price' => $amount,
                    'li_0_quantity' => 1,
                    'currency_code' => 'USD',
                    'return_url' => $successUrl . '?plan_id=' . $request->plan_id,
                    'cancel_url' => $cancelUrl . '?plan_id=' . $request->plan_id,
                    'order_id' => uniqid('order_'),
                    'merchant_order_id' => uniqid('order_'),
                    'email' => $user->email,
                ];


                $queryString = http_build_query($paymentParams);
                $paymentUrl = 'https://www.2checkout.com/checkout/purchase?' . $queryString;

                return $this->json('Redirecting to payment gateway.' ,[
                            'redirectUrl' => $paymentUrl
                        ], 201);



            case 'aamarpay':
                $paymentMethod = PaymentGateway::where('name', 'aamarpay')->first();
                $config = json_decode($paymentMethod->config);

                $url = "https://sandbox.aamarpay.com/jsonpost.php";

                $tran_id = 'ORD_' . time() . '_' . uniqid();

                $postData = [
                    "store_id"       => $config->store_id,
                    "signature_key"  => $config->signature_key,
                    "amount"         => $amount,
                    "currency"       => "BDT",
                    "tran_id"        => $tran_id,
                    "desc"           => "Subscription Payment",
                    "cus_name"       => $user->name ?? 'Customer',
                    "cus_email"      => $user->email,
                    "cus_phone"      => $user->phone ?? '01700000000',
                    "cus_add1"       => "Dhaka",
                    "cus_city"       => "Dhaka",
                    "cus_country"    => "Bangladesh",

                    "success_url"    => route('aamrpay.payment.success',['plan_id'=> $request->plan_id,'user_id'=> $user->id, 'tran_id'=> $tran_id]),
                    "fail_url"       => $fairUrl,
                    "cancel_url"     => $cancelUrl,

                    "type"           => "json"
                ];

                $curl = curl_init();

                curl_setopt_array($curl, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => "",
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => "POST",
                    CURLOPT_POSTFIELDS     => json_encode($postData),
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json'
                    ],
                ]);

                $response = curl_exec($curl);
                $err      = curl_error($curl);
                curl_close($curl);



                if ($err) {
                    return $this->json('cURL Error: ' . $err, [], 500);
                }

                $responseObj = json_decode($response);

                if ($responseObj && isset($responseObj->payment_url)) {
                    return $this->json('Redirecting to AamarPay...', [
                        'redirectUrl' => $responseObj->payment_url
                    ], 200);
                }

                return $this->json('AamarPay Payment Failed', [
                    'error'    => $responseObj->message ?? 'Unknown error',
                    'response' => $responseObj
                ], 500);



            case 'razorpay':
                $paymentMethod = PaymentGateway::where('name', 'razorpay')->first();

                if (!$paymentMethod) {
                    return $this->json('Razorpay not configured.', [], 500);
                }

                $config    = json_decode($paymentMethod->config, true);
                $keyId     = $config['key_id'] ?? null;
                $keySecret = $config['key_secret'] ?? null;
                $currency  = strtoupper($config['currency'] ?? 'INR');

                if (!$keyId || !$keySecret) {
                    return $this->json('Razorpay credentials not configured.', [], 500);
                }

                $rpResponse = Http::withBasicAuth($keyId, $keySecret)
                    ->post('https://api.razorpay.com/v1/orders', [
                        'amount'          => (int) round($amount * 100),
                        'currency'        => $currency,
                        'receipt'         => 'receipt_' . uniqid(),
                        'payment_capture' => 1,
                    ]);

                if ($rpResponse->failed()) {
                    return $this->json('Razorpay order creation failed: ' . $rpResponse->body(), [], 500);
                }

                $rpOrder = $rpResponse->json();

                $checkoutUrl = route('razorpay.payment.checkout', [
                    'order_id' => $rpOrder['id'],
                    'plan_id'  => $request->plan_id,
                    'user_id'  => $user->id,
                ]);

                return $this->json('Redirecting to payment gateway.', [
                    'redirectUrl' => $checkoutUrl
                ], 201);

            default:
               return $this->json('Redirecting to payment gateway.' ,[
                    'status' => 'error',
                    'message' => 'Invalid payment method'
                ], 400);
        }
    }




    public function paymentView(string $transaction_id)
    {
        $transaction = Transaction::query()->where('transaction_id', '=', $transaction_id)->first();

        if ($transaction) {
            $planDetails = SubscriptionPlan::query()->where('id', '=', $transaction->plan_id)->first();
            return $this->paymentService->processPayment($planDetails->amount, [
                'gateway' => $transaction->payment_method,
                'transaction_id' => base64_encode($transaction->transaction_id),
                'product' => [
                    'product' => $planDetails->name,
                    'price' => $planDetails->amount,
                ],
                'customer' => [
                    'name' => $transaction->user?->name ?? 'N/A',
                    'email' => $transaction->user?->email ?? 'N/A',
                    'phone' => $transaction->user?->phone ?? 'N/A',
                ]
            ]);
        }
        return $this->json('Transaction not found', null, 404);


    }

    public function myPlans()
    {
        $user = auth()->user();
        $now = now();

        // Show the plan the user is CURRENTLY on FIRST: active (not expired, not
        // refunded) subscriptions before lapsed ones, and within each group the
        // most recently purchased first. The app reads the first entry as the
        // "current" plan, so without this ordering a user who bought a new plan
        // could still see an older still-active row (e.g. a short sandbox plan).
        $subscriptions = $user->subscriptions()
            ->where('is_paid', 1)
            ->get()
            ->sortByDesc(function ($s) use ($now) {
                $active = $s->status !== 'refunded'
                    && $s->expired_at !== null
                    && $s->expired_at->greaterThanOrEqualTo($now);
                // Recency = last time the row was touched by a verify/renewal, so
                // the plan the user MOST RECENTLY changed to wins (more correct
                // than row id when an older row keeps getting updated in place).
                $recency = $s->updated_at ? $s->updated_at->timestamp : $s->id;
                return ($active ? 1 : 0) * 1e15 + $recency;
            })
            ->values();

        return $this->json('My subscription plan list', [
            'subscriptions' => SubscriptionResouorce::collection($subscriptions)
        ]);
    }

    /**
     * DEPRECATED for granting entitlement.
     *
     * Previously this created a paid-equivalent subscription from a plan_id
     * alone, which let any authenticated client mark itself premium for free.
     * Entitlement is now server-authoritative:
     *   - mobile in-app purchases must go through POST /v1/purchase/verify
     *     (Apple receipt / Google purchase-token validation), and
     *   - web payments are granted only by the verified gateway callbacks in
     *     PaymentController (which set is_paid = 1).
     *
     * This endpoint is retained for backward compatibility but NO LONGER grants
     * access; it just reports the caller's current entitlement state.
     */
    public function store(SubscribeRequest $request)
    {
        $user = auth()->user();
        $active = Subscription::activeFor($user);

        return $this->json('Current subscription status', [
            'has_subscribed' => (bool) $active,
            'subscription' => $active ? SubscriptionResouorce::make($active) : null,
        ]);
    }
}
