<?php

namespace App\Listeners;

use App\Models\LogPaymentRequest;
use App\Models\LogPaymentRequestError;
use App\Services\PaymentService;
use App\Models\PaymentOrder as PayModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Class RechargeBalanceRequestListener
 *
 * @package App\Listeners
 */
class RechargeBalanceRequestListener
{
    /**
     * @var string
     */
    private const RECEIVER_LISTENER = 'rechargeBalanceResponse';

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param array $inputData
     *
     * @return void
     */
    public function handle(array $inputData)
    {
        $validation = Validator::make($inputData, [
            'gateway' => 'string|required',
            'amount' => 'integer|required',
            'currency' => 'string|required',
            'replay_to' => 'string|required',
            'order_id' => 'string|required',
            'user_id' => 'string|required',
        ]);

        if ($validation->fails()) {
            \PubSub::publish(self::RECEIVER_LISTENER, [
                'status' => 'error',
                'order_id' => $inputData['order_id'],
                'message' => $validation->errors()
            ], $inputData['replay_to']);

            exit();
        }

        // Payment Log
        try {
            LogPaymentRequest::create([
                'gateway' => $inputData['gateway'],
                'service' => $inputData['replay_to'],
                'payload' => $inputData
            ]);
        }
        catch (\Exception $e) {
            Log::info('Log of invoice failed: ' . $e->getMessage());
        }

        // Init manager
        try {
            $payment = PayModel::create([
                'type' => PayModel::TYPE_PAYIN,
                'amount' => $inputData['amount'],
                'gateway' => $inputData['gateway'],
                'user_id' => $inputData['user_id'],
                'service' => $inputData['replay_to'],
                'currency' => $inputData['currency'],
                'payload' => $inputData
            ]);

            $paymentGateway = PaymentService::getInstance($inputData['gateway']);
        }
        catch(\Exception $e) {
            \PubSub::publish(self::RECEIVER_LISTENER, [
                'status' => 'error',
                'order_id' => $inputData['order_id'],
                'message' => $e->getMessage(),
            ], $inputData['replay_to']);

            exit();
        }

        // Create invoice
        $result = $paymentGateway->createInvoice($payment, (object) $inputData);

        // Return response
        if ($result['type'] === 'error') {
            LogPaymentRequestError::create([
                'gateway' => $inputData['gateway'],
                'payload' => $result['message']
            ]);
        }

        // Send payment request to payment gateway
        \PubSub::publish(self::RECEIVER_LISTENER, array_merge($result, [
            'order_id' => $inputData['order_id'],
        ]), $inputData['replay_to']);
    }
}
