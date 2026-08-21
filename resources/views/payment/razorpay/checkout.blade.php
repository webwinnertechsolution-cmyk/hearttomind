<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Razorpay Checkout</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #16324f;
        }
        .card {
            width: min(480px, calc(100vw - 32px));
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(22, 50, 79, 0.12);
            padding: 28px;
            text-align: center;
        }
        .button {
            display: inline-block;
            width: 100%;
            border: 0;
            border-radius: 999px;
            padding: 14px 18px;
            background: #0d6efd;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }
        .muted {
            color: #5d7288;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>Complete Your Payment</h2>
        <p class="muted">Subscription plan: {{ $plan->name }}<br>Amount: {{ $currency }} {{ number_format($plan->amount, 2) }}</p>
        <button class="button" id="pay-button" type="button">Pay with Razorpay</button>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const options = {
            key: @json($keyId),
            amount: @json($amount),
            currency: @json($currency),
            name: "Heart 2 Mind",
            description: @json($plan->name),
            order_id: @json($orderId),
            prefill: {
                name: @json($user->name),
                email: @json($user->email),
                contact: @json($user->phone ?? '')
            },
            handler: function (response) {
                const params = new URLSearchParams({
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature,
                    plan_id: @json((string) $plan->id),
                    user_id: @json((string) $user->id)
                });
                window.location.href = @json($verifyUrl) + '?' + params.toString();
            },
            modal: {
                ondismiss: function () {
                    window.location.href = @json($cancelUrl);
                }
            },
            theme: {
                color: "#0d6efd"
            }
        };

        const razorpay = new Razorpay(options);
        document.getElementById('pay-button').addEventListener('click', function () {
            razorpay.open();
        });
        window.addEventListener('load', function () {
            razorpay.open();
        });
    </script>
</body>
</html>
