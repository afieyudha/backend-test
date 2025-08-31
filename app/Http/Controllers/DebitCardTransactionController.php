<?php

namespace App\Http\Controllers;

use App\Http\Requests\DebitCardTransactionCreateRequest;
use App\Http\Requests\DebitCardTransactionShowIndexRequest;
use App\Http\Requests\DebitCardTransactionShowRequest;
use App\Http\Resources\DebitCardTransactionResource;
use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DebitCardTransactionController extends BaseController
{
    /**
     * Get debit card transactions list
     *
     * @param DebitCardTransactionShowIndexRequest $request
     *
     * @return JsonResponse
     */
    public function index(DebitCardTransactionShowIndexRequest $request): JsonResponse
    {
        $query = DebitCardTransaction::query()
            ->whereHas('debitCard', function ($query) {
                $query->where('user_id', auth()->id());
            });

        // If specific debit card is requested, filter by it
        if ($request->has('debit_card_id')) {
            $query->where('debit_card_id', $request->input('debit_card_id'));
        }

        $debitCardTransactions = $query->get();

        return response()->json([
            'data' => DebitCardTransactionResource::collection($debitCardTransactions)
        ], HttpResponse::HTTP_OK);
    }

    /**
     * Create a new debit card transaction
     *
     * @param DebitCardTransactionCreateRequest $request
     *
     * @return JsonResponse
     */
    public function store(DebitCardTransactionCreateRequest $request): JsonResponse
    {
        $debitCard = DebitCard::find($request->input('debit_card_id'));

        $debitCardTransaction = $debitCard->debitCardTransactions()->create([
            'amount' => $request->input('amount'),
            'currency_code' => $request->input('currency_code'),
            'description' => $request->input('description'),
        ]);

        return response()->json([
            'data' => new DebitCardTransactionResource($debitCardTransaction)
        ], HttpResponse::HTTP_CREATED);
    }

    /**
     * Show a debit card transaction
     *
     * @param DebitCardTransactionShowRequest $request
     * @param DebitCardTransaction            $debitCardTransaction
     *
     * @return JsonResponse
     */
    public function show(DebitCardTransactionShowRequest $request, DebitCardTransaction $debitCardTransaction): JsonResponse
    {
        return response()->json([
            'data' => new DebitCardTransactionResource($debitCardTransaction)
        ], HttpResponse::HTTP_OK);
    }
}