<?php

namespace App\Services\Payments;

use App\Contracts\PaymentSystemContract;
use App\Models\Currency;
use App\Models\Payment;
use CoinbaseCommerce\ApiClient;
use CoinbaseCommerce\Resources\Charge;
use CoinbaseCommerce\Webhook;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CoinbaseManager implements PaymentSystemContract
{
    /**
     * Charge statuses
     */
    // New charge is created
    const STATUS_CHARGE_CREATED = 1;

    //	Charge has been detected but has not been confirmed yet
    const STATUS_CHARGE_PENDING = 2;

    //	Charge has been confirmed and the associated payment is completed
    const STATUS_CHARGE_CONFIRMED = 3;

    //	Charge failed to complete
    const STATUS_CHARGE_FAILED = 4;

    //	Charge received a payment after it had been expired
    const STATUS_CHARGE_DELAYED = 5;

    //	Charge has been resolved
    const STATUS_CHARGE_RESOLVED = 6;

    /**
     * @var int[]
     */
    public static $statuses = [
        self::STATUS_CHARGE_CREATED,
        self::STATUS_CHARGE_PENDING,
        self::STATUS_CHARGE_CONFIRMED,
        self::STATUS_CHARGE_FAILED,
        self::STATUS_CHARGE_DELAYED,
        self::STATUS_CHARGE_RESOLVED,
    ];

    /**
     * @var \CoinbaseCommerce\ApiClient
     */
    private $gateway;

    /**
     * CoinbaseManager constructor.
     */
    public function __construct()
    {
        try {
            $this->gateway = ApiClient::init(config('payments.coinbase.api_key'));
            $this->gateway->setTimeout(3);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function name(): string
    {
        return 'Coinbase';
    }

    public static function description(): string
    {
        return 'Coinbase Commerce is..';
    }

    public static function type(): string
    {
        return 'coinbase';
    }

    /**
     * Wrapper for create coinbase invoice for charge money
     *
     * @param array $data
     *
     * @return mixed|void
     */
    public function createInvoice(array $data): array
    {
        try {
            // Create check code
            $checkCode = Payment::getCheckCode();

            $currency_id = $data['currency']['id'] ?? Currency::$currencies[mb_strtoupper($data['currency']['code'])];

            // Create internal order
            $payment = Payment::create([
                'type' => Payment::TYPE_INVOICE,
                'gateway' => self::type(),
                'amount' => $data['amount'],
                'currency_id' => $currency_id,
                'check_code' => $checkCode,
                'order_id' => $data['order_id'],
                'service' => $data['replay_to'],
                'user_id' => $data['user_id'] ?? Auth::user()->getAuthIdentifier(),
                'status' => self::STATUS_CHARGE_CREATED
            ]);

            // Create new charge
            $chargeObj = Charge::create([
                'name' => 'Charge Balance',
                'description' => 'Charge Balance for Sumra User',
                'pricing_type' => 'fixed_price',
                'local_price' => [
                    'amount' => $data['amount'],
                    'currency' => $data['currency']['code']
                ],
                'metadata' => [
                    'code' => $checkCode,
                    'payment_id' => $payment->id
                ],
                'redirect_url' => config('payments.coinbase.redirect_url'),
                'cancel_url' => config('payments.coinbase.cancel_url')
            ]);

            // Update payment transaction data
            $payment->document_id = $chargeObj->id;
            $payment->save();

            return [
                'status' => 'success',
                'invoice_url' => $chargeObj->hosted_url
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => sprintf("Unable to create a charge. Error: %s \n", $e->getMessage())
            ];
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|string[]
     */
    public function handlerWebhookInvoice(Request $request): array
    {
        // Get event
        try {
            $event = Webhook::buildEvent(
                trim(file_get_contents('php://input')),
                $request->header('X-Cc-Webhook-Signature', null),
                config('payments.coinbase.webhook_key')
            );
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        // Get event data
        $paymentData = $event->data;
        if ($paymentData === null) {
            return [
                'status' => 'error',
                'message' => 'Empty / Incorrect event data'
            ];
        }

        // Find payment transaction
        $payment = Payment::where('type', Payment::TYPE_INVOICE)
            ->where('id', $paymentData->metadata->payment_id ?? null)
            ->where('document_id', $paymentData->id)
            ->where('check_code', $paymentData->metadata->code ?? null)
            ->where('gateway', self::type())
            ->first();

        if (!$payment) {
            return [
                'status' => 'error',
                'message' => 'Payment transaction not found in Payment Microservice database'
            ];
        }

        // Update payment transaction status
        $status = 'STATUS_' . mb_strtoupper(Str::snake(str_replace(':', ' ', $event->type)));
        $payment->status = constant("self::{$status}");
        $payment->payload = $paymentData;
        $payment->save();

        // Return result
        return [
            'status' => 'success',
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'service' => $payment->service,
            'payment_status' => ''
        ];
    }
}
