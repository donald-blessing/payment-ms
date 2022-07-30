<?php

namespace App\Api\V1\Controllers\Application;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

/**
 * Class TransactionController
 *
 * @package App\Api\V1\Controllers
 */
class TransactionController extends Controller
{
    /**
     *  Display a listing of the band
     *
     * @OA\Get(
     *     path="/app/transactions",
     *     description="Get all transactions",
     *     tags={"Application | Payment Orders Transactions"},
     *
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items()
     *          ),
     *     ),
     *
     *     @OA\Response(
     *         response="500",
     *         description="Unknown error"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Not Found"
     *     ),
     * )
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        try {
            $transaction = Transaction::with(['paymentOrders'])
                ->latest()
                ->paginate($request->get('limit', config('settings.pagination_limit')));

            return response()->jsonApi([
                'title' => 'Get Transaction List',
                'message' => 'Transaction List',
                'data' => $transaction
            ]);
        } catch (\Throwable $th) {
            return response()->jsonApi([
                'title' => 'Get Transaction List',
                'message' => 'Get Transaction List Failed: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store payment order transaction result
     *
     * @OA\Post(
     *     path="/app/transactions",
     *     summary="Store payment order transaction result",
     *     description="Store payment order transaction result",
     *     tags={"Application | Payment Orders Transactions"},
     *
     *     security={{
     *         "bearerAuth": {},
     *         "apiKey": {}
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/PaymentOrderTransactionSave")
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Payment order transaction saved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Unknown error"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Not Found"
     *     )
     * )
     *
     * @param Request $request
     */
    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'gateway' => 'required|string',
                'payment_order_id' => 'required|string',
                'meta' => 'required|array',
                'meta.trx_id' => 'sometimes|string',
                'meta.wallet' => 'sometimes|string',
                'meta.payment_intent' => 'sometimes|string',
                'meta.payment_intent_client_secret' => 'sometimes|string'
            ]);

            $transaction = new Transaction();
            $transaction->payment_order_id = $request->payment_order_id;
            $transaction->trx_id = $request->trx_id;

            $transaction->save();


            return response()->jsonApi([
                'title' => 'Store transaction',
                'message' => 'transaction saved',
                'data' => $transaction
            ]);
        } catch (\Throwable $th) {
            return response()->jsonApi([
                'title' => 'Store transaction',
                'message' => $th->getMessage()
            ], 500);
        }
    }

     /**
     *  Display a listing of the band
     *
     * @OA\Get(
     *     path="/app/transactions/{id}",
     *     description="Get all transactions",
     *     tags={"Application | Payment Orders Transactions"},
     *
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="id",
     *                  type="number",
     *                  description="id",
     *                  example="90000009-9009-9009-9009-900000000009"
     *              ),
     *              @OA\Property(
     *                  property="trx_id",
     *                  type="string",
     *                  description="trx_id",
     *                  example="PAY_INT_ULTRA62e19abcca0c5"
     *              ),
     *              @OA\Property(
     *                  property="payment_order_id",
     *                  type="string",
     *                  description="payment_order_id",
     *                  example="96e17ebc-5404-43ee-b1c9-323ed169f935"
     *              )
     *          ),
     *     ),
     *
     *     @OA\Response(
     *         response="500",
     *         description="Unknown error"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Not Found"
     *     ),
     * )
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function show($id)
    {
        try {
            $transaction = Transaction::with(['paymentOrders'])
                ->where('id', $id)
                ->first();

            return response()->jsonApi([
                'title' => 'Get Transaction',
                'message' => 'Transaction',
                'data' => $transaction
            ]);
        } catch (\Throwable $th) {
            return response()->jsonApi([
                'title' => 'Get a Transaction',
                'message' => 'Get a Transaction Failed: ' . $th->getMessage(),
            ], 500);
        }
    }
}