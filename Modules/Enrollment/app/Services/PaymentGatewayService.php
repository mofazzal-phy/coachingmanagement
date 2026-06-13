<?php

namespace Modules\Enrollment\app\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Payment Gateway Service
 *
 * Abstraction layer for various payment gateways (bKash, Nagad, Rocket, etc.).
 * Each gateway implements a standard interface for payment processing.
 */
class PaymentGatewayService
{
    /**
     * Available gateway configurations.
     */
    protected array $gateways = [];

    public function __construct()
    {
        $this->gateways = config('payment_gateways', [
            'bkash' => [
                'enabled' => env('BKASH_ENABLED', false),
                'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
                'app_key' => env('BKASH_APP_KEY', ''),
                'app_secret' => env('BKASH_APP_SECRET', ''),
                'username' => env('BKASH_USERNAME', ''),
                'password' => env('BKASH_PASSWORD', ''),
            ],
            'nagad' => [
                'enabled' => env('NAGAD_ENABLED', false),
                'base_url' => env('NAGAD_BASE_URL', 'https://sandbox.mynagad.com/api'),
                'merchant_id' => env('NAGAD_MERCHANT_ID', ''),
                'merchant_number' => env('NAGAD_MERCHANT_NUMBER', ''),
                'public_key' => env('NAGAD_PUBLIC_KEY', ''),
                'private_key' => env('NAGAD_PRIVATE_KEY', ''),
            ],
            'rocket' => [
                'enabled' => env('ROCKET_ENABLED', false),
                'base_url' => env('ROCKET_BASE_URL', ''),
                'api_key' => env('ROCKET_API_KEY', ''),
            ],
            'stripe' => [
                'enabled' => env('STRIPE_ENABLED', false),
                'secret_key' => env('STRIPE_SECRET', ''),
                'public_key' => env('STRIPE_KEY', ''),
            ],
        ]);
    }

    /**
     * Check if we are in development/local environment with no real API keys.
     * When true, gateway methods return mock success responses instead of making real API calls.
     */
    public function isDevMockMode(): bool
    {
        return app()->environment('local') && empty(env('BKASH_APP_KEY'));
    }

    /**
     * Initiate a payment through the specified gateway.
     * Returns a payment URL or token that the user can use to complete payment.
     */
    public function initiatePayment(string $gateway, array $params): array
    {
        if (!isset($this->gateways[$gateway]) || !$this->gateways[$gateway]['enabled']) {
            throw new \Exception("Payment gateway '{$gateway}' is not enabled or configured.");
        }

        // In dev mode with no API keys, return mock success
        if ($this->isDevMockMode()) {
            return $this->mockInitiate($gateway, $params);
        }

        return match ($gateway) {
            'bkash' => $this->initiateBkash($params),
            'nagad' => $this->initiateNagad($params),
            'rocket' => $this->initiateRocket($params),
            'stripe' => $this->initiateStripe($params),
            default => throw new \Exception("Unsupported payment gateway: {$gateway}"),
        };
    }

    /**
     * Execute a payment (for manual/admin payments that don't need gateway redirect).
     */
    public function executePayment(string $gateway, array $params): array
    {
        // For manual payments (cash, bank, etc.), just return success
        if (in_array($gateway, ['cash', 'bank', 'manual'])) {
            return [
                'success' => true,
                'transaction_id' => $params['transaction_id'] ?? 'MANUAL-' . strtoupper(uniqid()),
                'gateway_response' => ['method' => $gateway, 'manual' => true],
            ];
        }

        // In dev mode with no API keys, return mock success
        if ($this->isDevMockMode()) {
            return [
                'success' => true,
                'transaction_id' => 'DEV-' . strtoupper(uniqid()),
                'gateway_response' => ['gateway' => $gateway, 'mock' => true],
            ];
        }

        // For gateway payments, delegate to the specific gateway
        return match ($gateway) {
            'bkash' => $this->executeBkash($params),
            'nagad' => $this->executeNagad($params),
            'rocket' => $this->executeRocket($params),
            'stripe' => $this->executeStripe($params),
            default => throw new \Exception("Unsupported payment gateway: {$gateway}"),
        };
    }

    /**
     * Verify a payment transaction.
     */
    public function verifyPayment(string $gateway, string $transactionId): array
    {
        // In dev mode with no API keys, return mock success
        if ($this->isDevMockMode()) {
            return [
                'success' => true,
                'data' => [
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'status' => 'completed',
                    'mock' => true,
                ],
            ];
        }

        return match ($gateway) {
            'bkash' => $this->verifyBkash($transactionId),
            'nagad' => $this->verifyNagad($transactionId),
            'rocket' => $this->verifyRocket($transactionId),
            'stripe' => $this->verifyStripe($transactionId),
            default => ['success' => false, 'message' => 'Unsupported gateway'],
        };
    }

    /**
     * Get list of enabled gateways with their display info.
     */
    public function getEnabledGateways(): array
    {
        $enabled = [];
        $gatewayInfo = [
            'bkash' => ['name' => 'bKash', 'logo' => 'bkash.png', 'type' => 'mobile_banking'],
            'nagad' => ['name' => 'Nagad', 'logo' => 'nagad.png', 'type' => 'mobile_banking'],
            'rocket' => ['name' => 'Rocket', 'logo' => 'rocket.png', 'type' => 'mobile_banking'],
            'stripe' => ['name' => 'Stripe', 'logo' => 'stripe.png', 'type' => 'card'],
        ];

        foreach ($this->gateways as $key => $config) {
            if (!empty($config['enabled'])) {
                $enabled[$key] = array_merge(
                    $gatewayInfo[$key] ?? ['name' => ucfirst($key), 'logo' => null, 'type' => 'other'],
                    ['key' => $key]
                );
            }
        }

        return $enabled;
    }

    /**
     * Return mock success response for development mode.
     * This allows testing the payment flow without real gateway credentials.
     */
    protected function mockInitiate(string $gateway, array $params): array
    {
        $transactionId = 'DEV-' . strtoupper(uniqid());

        Log::info("[DEV MODE] Mock {$gateway} payment initiated", [
            'amount' => $params['amount'],
            'reference' => $params['reference'] ?? 'N/A',
            'transaction_id' => $transactionId,
        ]);

        return [
            'success' => true,
            'gateway' => $gateway,
            'payment_url' => null,
            'redirect_url' => null,
            'transaction_id' => $transactionId,
            'gateway_response' => [
                'gateway' => $gateway,
                'mock' => true,
                'message' => "DEV MODE: {$gateway} payment initiated (no real charge)",
            ],
        ];
    }

    /**
     * bKash: Initiate payment (tokenized checkout).
     */
    protected function initiateBkash(array $params): array
    {
        $config = $this->gateways['bkash'];

        try {
            // Step 1: Grant token
            $tokenResponse = Http::post("{$config['base_url']}/tokenized/checkout/token/grant", [
                'app_key' => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('bKash token grant failed', ['response' => $tokenResponse->body()]);
                return ['success' => false, 'message' => 'Failed to authenticate with bKash'];
            }

            $idToken = $tokenResponse->json('id_token');

            // Step 2: Create payment
            $paymentResponse = Http::withToken($idToken)
                ->post("{$config['base_url']}/tokenized/checkout/create", [
                    'mode' => '0011',
                    'payerReference' => $params['reference'] ?? 'payment',
                    'callbackURL' => $params['callback_url'] ?? url('/api/v1/payment/bkash/callback'),
                    'amount' => (string) $params['amount'],
                    'currency' => 'BDT',
                    'intent' => 'sale',
                    'merchantInvoiceNumber' => $params['invoice_no'] ?? 'INV-' . time(),
                ]);

            if (!$paymentResponse->successful()) {
                Log::error('bKash payment creation failed', ['response' => $paymentResponse->body()]);
                return ['success' => false, 'message' => 'Failed to create bKash payment'];
            }

            return [
                'success' => true,
                'gateway' => 'bkash',
                'payment_url' => $paymentResponse->json('bkashURL'),
                'transaction_id' => $paymentResponse->json('paymentID'),
                'gateway_response' => $paymentResponse->json(),
            ];
        } catch (\Exception $e) {
            Log::error('bKash initiate error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * bKash: Execute payment after callback.
     */
    protected function executeBkash(array $params): array
    {
        $config = $this->gateways['bkash'];
        $paymentId = $params['payment_id'] ?? '';

        try {
            $tokenResponse = Http::post("{$config['base_url']}/tokenized/checkout/token/grant", [
                'app_key' => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ]);

            if (!$tokenResponse->successful()) {
                return ['success' => false, 'message' => 'bKash auth failed'];
            }

            $idToken = $tokenResponse->json('id_token');

            $executeResponse = Http::withToken($idToken)
                ->post("{$config['base_url']}/tokenized/checkout/execute", [
                    'paymentID' => $paymentId,
                ]);

            if (!$executeResponse->successful()) {
                return ['success' => false, 'message' => 'bKash execution failed'];
            }

            return [
                'success' => true,
                'transaction_id' => $executeResponse->json('trxID'),
                'gateway_response' => $executeResponse->json(),
            ];
        } catch (\Exception $e) {
            Log::error('bKash execute error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * bKash: Verify transaction.
     */
    protected function verifyBkash(string $transactionId): array
    {
        $config = $this->gateways['bkash'];

        try {
            $tokenResponse = Http::post("{$config['base_url']}/tokenized/checkout/token/grant", [
                'app_key' => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ]);

            if (!$tokenResponse->successful()) {
                return ['success' => false, 'message' => 'bKash auth failed'];
            }

            $idToken = $tokenResponse->json('id_token');

            $verifyResponse = Http::withToken($idToken)
                ->post("{$config['base_url']}/tokenized/checkout/payment/status", [
                    'paymentID' => $transactionId,
                ]);

            return [
                'success' => $verifyResponse->successful(),
                'data' => $verifyResponse->json(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Nagad: Initiate payment.
     */
    protected function initiateNagad(array $params): array
    {
        $config = $this->gateways['nagad'];

        try {
            // Generate a unique merchant invoice number
            $merchantInvoiceNo = 'INV-' . time() . '-' . uniqid();

            // Prepare sensitive data for encryption
            $sensitiveData = [
                'merchantId' => $config['merchant_id'],
                'invoiceNo' => $merchantInvoiceNo,
                'amount' => (string) $params['amount'],
                'currency' => 'BDT',
                'merchantCallbackURL' => $params['callback_url'] ?? url('/api/v1/payment/nagad/callback'),
            ];

            // In production, encrypt with Nagad's public key
            $sensitiveDataEncoded = base64_encode(json_encode($sensitiveData));

            $paymentRequest = [
                'merchantId' => $config['merchant_id'],
                'invoiceNo' => $merchantInvoiceNo,
                'amount' => (string) $params['amount'],
                'currency' => 'BDT',
                'merchantCallbackURL' => $params['callback_url'] ?? url('/api/v1/payment/nagad/callback'),
                'sensitiveData' => $sensitiveDataEncoded,
                'signature' => hash('sha256', $sensitiveDataEncoded . $config['merchant_number']),
            ];

            $response = Http::post("{$config['base_url']}/checkout/initialize", $paymentRequest);

            if (!$response->successful()) {
                Log::error('Nagad initiate failed', ['response' => $response->body()]);
                return ['success' => false, 'message' => 'Failed to initiate Nagad payment'];
            }

            return [
                'success' => true,
                'gateway' => 'nagad',
                'payment_url' => $response->json('callbackUrl'),
                'transaction_id' => $merchantInvoiceNo,
                'gateway_response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Nagad initiate error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Nagad: Execute payment.
     */
    protected function executeNagad(array $params): array
    {
        // Nagad handles execution via callback, so this is a verification step
        return $this->verifyNagad($params['transaction_id'] ?? '');
    }

    /**
     * Nagad: Verify payment.
     */
    protected function verifyNagad(string $transactionId): array
    {
        $config = $this->gateways['nagad'];

        try {
            $response = Http::get("{$config['base_url']}/checkout/status", [
                'merchantId' => $config['merchant_id'],
                'invoiceNo' => $transactionId,
            ]);

            return [
                'success' => $response->successful() && $response->json('status') === 'Success',
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Rocket: Initiate payment.
     */
    protected function initiateRocket(array $params): array
    {
        $config = $this->gateways['rocket'];

        try {
            $response = Http::post("{$config['base_url']}/payment/initiate", [
                'api_key' => $config['api_key'],
                'amount' => $params['amount'],
                'mobile' => $params['mobile'] ?? '',
                'reference' => $params['reference'] ?? 'payment',
                'callback_url' => $params['callback_url'] ?? url('/api/v1/payment/rocket/callback'),
            ]);

            if (!$response->successful()) {
                return ['success' => false, 'message' => 'Rocket payment initiation failed'];
            }

            return [
                'success' => true,
                'gateway' => 'rocket',
                'payment_url' => $response->json('payment_url'),
                'transaction_id' => $response->json('transaction_id'),
                'gateway_response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('Rocket initiate error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Rocket: Execute payment.
     */
    protected function executeRocket(array $params): array
    {
        return $this->verifyRocket($params['transaction_id'] ?? '');
    }

    /**
     * Rocket: Verify payment.
     */
    protected function verifyRocket(string $transactionId): array
    {
        $config = $this->gateways['rocket'];

        try {
            $response = Http::post("{$config['base_url']}/payment/status", [
                'api_key' => $config['api_key'],
                'transaction_id' => $transactionId,
            ]);

            return [
                'success' => $response->successful(),
                'data' => $response->json(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Stripe: Initiate payment.
     */
    protected function initiateStripe(array $params): array
    {
        $config = $this->gateways['stripe'];

        try {
            \Stripe\Stripe::setApiKey($config['secret_key']);

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'bdt',
                        'product_data' => [
                            'name' => $params['description'] ?? 'Fee Payment',
                        ],
                        'unit_amount' => (int) ($params['amount'] * 100), // Convert to cents/paisa
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $params['success_url'] ?? url('/api/v1/payment/stripe/success'),
                'cancel_url' => $params['cancel_url'] ?? url('/api/v1/payment/stripe/cancel'),
                'metadata' => [
                    'reference' => $params['reference'] ?? '',
                    'enrollment_id' => $params['enrollment_id'] ?? '',
                ],
            ]);

            return [
                'success' => true,
                'gateway' => 'stripe',
                'payment_url' => $session->url,
                'transaction_id' => $session->id,
                'gateway_response' => $session->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Stripe initiate error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Stripe: Execute payment.
     */
    protected function executeStripe(array $params): array
    {
        return $this->verifyStripe($params['session_id'] ?? '');
    }

    /**
     * Stripe: Verify payment.
     */
    protected function verifyStripe(string $sessionId): array
    {
        $config = $this->gateways['stripe'];

        try {
            \Stripe\Stripe::setApiKey($config['secret_key']);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            return [
                'success' => $session->payment_status === 'paid',
                'data' => $session->toArray(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
