<?php

namespace App\Http\Controllers\Wallet;

use App\Domain\Wallet\Actions\GetCustomerWallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\ShowWalletRequest;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function show(ShowWalletRequest $request, GetCustomerWallet $action): JsonResponse
    {
        $organizationId = $request->filled('organization_id') ? $request->integer('organization_id') : null;

        return response()->json($action->execute($request->user()->id, $organizationId));
    }
}
