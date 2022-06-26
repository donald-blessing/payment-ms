<?php

/**
 * @var Laravel\Lumen\Routing\Router $router
 */
$router->group([
    'prefix' => env('APP_API_VERSION', ''),
    'namespace' => '\App\Api\V1\Controllers'
], function ($router) {
    /**
     * PUBLIC ACCESS
     */
    /**
     * Payments webhooks
     */
    $router->group([
        'prefix' => 'webhooks',
        'namespace' => 'Webhooks'
    ], function ($router) {
        $router->post('{gateway}/invoices', 'WebhookController@handlerWebhook');
    });

    /**
     * USER APPLICATION PRIVATE ACCESS
     */
    $router->group([
        'middleware' => 'checkUser',
        'namespace' => 'Application'
    ], function ($router) {
        /**
         * Payment actions
         */
        $router->get('payments/{id:[\d]+}', 'PaymentController@show');
        $router->post('payments/charge', 'PaymentController@charge');

        /**
         * Payment systems list
         */
        $router->get('payment-systems', 'PaymentSystemController');
    });

    /**
     * ADMIN PANEL ACCESS
     */
    $router->group([
        'prefix' => 'admin',
        'namespace' => 'Admin',
        'middleware' => [
            'checkUser',
            'checkAdmin'
        ]
    ], function ($router) {
        $router->get('/payments', 'PaymentController@index');
        $router->get('/payments/lost', 'PaymentController@lost');
        $router->post('/payments/{id:[\d]+}', 'PaymentController@update');

        //Manage all payment system
        $router->get('/payment-system',          'PaymentSystemController@index');
        $router->get('/payment-system/{id}',     'PaymentSystemController@show');
        $router->post('/payment-system',         'PaymentSystemController@store');
        $router->put('/payment-system/{id}',     'PaymentSystemController@update');
        $router->delete('/payment-system/{id}',  'PaymentSystemController@destroy');

        //Manage all payment setting
        $router->get('/payment-setting',          'PaymentSettingController@index');
        $router->post('/payment-setting',         'PaymentSettingController@index');
    });
});
