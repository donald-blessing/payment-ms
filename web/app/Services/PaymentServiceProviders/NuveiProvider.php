<?php

namespace App\Services\PaymentServiceProviders;

use App\Contracts\PaymentServiceContract;
use App\Models\PaymentOrder;
use Exception;
use Illuminate\Http\Request;
use SafeCharge\Api\Environment;
use SafeCharge\Api\RestClient;

/**
 * Class NuveiProvider
 * @package App\Services\PaymentServiceProviders
 */
class NuveiProvider implements PaymentServiceContract
{
    /**
     * @var RestClient
     */
    protected $service;

    /**
     * @var string
     */
    private object $settings;

    /**
     * StripeProvider constructor.
     * @param Object $settings
     * @throws Exception
     */
    public function __construct(object $settings)
    {
        $this->settings = $settings;

        try {
            $this->service = new RestClient();
            $this->service->initialize([
                'enviroment' => Environment::TEST,
                'merchantId' => '<your merchantId>',
                'merchantSiteId' => '<your merchantSiteId>',
                'merchantSecretKey' => '<your merchantSecretKey>',
            ]);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getPaymentStatus($paymentId)
    {
        $paymentStatus = $this->service->getPaymentService()->getPaymentStatus([
            'paymentId' => $paymentId,
        ]);

        return $paymentStatus;
    }

    public function openOrder($data = [])
    {
        $order = $this->service->getPaymentService()->openOrder([
            'userTokenId' => $data['userTokenId'],
            'clientUniqueId' => $data['clientUniqueId'],
            'clientRequestId' => $data['clientRequestId'],
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'billingAddress' => [
                'country' => $data['country'],
                "email" => $data['email'],
            ],
        ]);

        return $order;
    }

    public function initPayment(array $data)
    {
        $response = $this->service->getPaymentService()->initPayment([
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'userTokenId' => $data['userTokenId'],
            'clientUniqueId' => $data['clientUniqueId'],
            'clientRequestId' => $data['clientRequestId'],
            'paymentOption' => [
                'card' => [
                    'cardNumber' => $data['cardNumber'],
                    'cardHolderName' => $data['cardHolderName'],
                    'expirationMonth' => $data['expirationMonth'],
                    'expirationYear' => $data['expirationYear'],
                    'CVV' => $data['CVV'],
                    'threeD' => [
                        'methodNotificationUrl' => $data['methodNotificationUrl'],
                    ]
                ]
            ],
            'deviceDetails' => [
                "ipAddress" => $data['ipAddress'],
            ],
        ]);
    }

    public function payment(array $data)
    {
        $payment = $this->service->getPaymentService()->createPayment([
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'userTokenId' => $data['userTokenId'],
            'clientRequestId' => $data['clientRequestId'],
            'clientUniqueId' => $data['clientUniqueId'],
            'paymentOption' => [
                'card' => [
                    'cardNumber' => $data['cardNumber'],
                    'cardHolderName' => $data['cardHolderName'],
                    'expirationMonth' => $data['expirationMonth'],
                    'expirationYear' => $data['expirationYear'],
                    'CVV' => $data['CVV'],
                ]
            ],
            'billingAddress' => [
                'country' => $data['country'],
                "email" => $data['email'],
            ],
            'deviceDetails' => [
                'ipAddress' => $data['ipAddress'],
            ]
        ]);

        return $payment;
    }

    /**
     * @return string
     */
    public static function key(): string
    {
        return 'nuvei';
    }

    /**
     * @return string
     */
    public static function title(): string
    {
        return 'Nuvei Payment Technology Partner';
    }

    /**
     * @return string
     */
    public static function description(): string
    {
        return 'We are the payment technology partner of thriving brands. We provide the payment intelligence and technology businesses need to succeed locally and globally, through one integration — propelling them further, faster';
    }

    /**
     * Make one-time charge money to system
     *
     * @param PaymentOrder $order
     * @param object $inputData
     * @return mixed
     */
    public function charge(PaymentOrder $order, object $inputData): mixed
    {
        // TODO: Implement charge() method.
    }

    public function handlerWebhook(Request $request): mixed
    {
        // TODO: Implement handlerWebhook() method.
    }

    /**
     * @param object $payload
     * @return mixed
     */
    public function checkTransaction(object $payload): mixed
    {
        // TODO: Implement checkTransaction() method.
    }
}
