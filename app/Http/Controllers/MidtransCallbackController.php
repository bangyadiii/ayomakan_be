<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\PaymentLog;
use App\Models\Transactions;
use App\Services\Midtrans\Midtrans;
use App\Services\TransactionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Midtrans\Config;
use Midtrans\Notification;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MidtransCallbackController extends Controller
{
    public function __construct()
    {
        Midtrans::configureMidtrans();
    }

    public function callback(Request $request)
    {
        $notif = new Notification();
        $validSignatureKey =  hash(
            'sha512',
            $request->order_id .
                $request->status_code .
                $request->gross_amount .
                Config::$serverKey
        );

        if ($validSignatureKey !== $request->signature_key && !\app()->environment("local")) {
            throw new BadRequestHttpException("Signature key is not valid");
        }

        $transaction = $notif->transaction_status;
        $fraud = $notif->fraud_status;
        $orderId = \app()->environment("local") ? $request->order_id : $notif->order_id;

        try {
            $trx = Transactions::find($orderId);
            \abort_if(!$trx, 404, "Transaction not found");

            if ($transaction == 'capture') {
                if ($fraud == 'challenge') {
                    $trx->status = "pending";
                } elseif ($fraud == 'accept') {
                    $trx->status = "success";
                }
            } elseif ($transaction == 'settlement') {
                $trx->status = "success";
            } elseif (
                $transaction == 'cancel' ||
                $transaction == 'deny' ||
                $transaction == 'expire'
            ) {
                $trx->status = "failed";
            } elseif ($transaction == 'pending') {
                $trx->status = "pending";
            }

            PaymentLog::create([
                "trx_id" => $trx->id,
                "md_trx_id" => $notif->transaction_id,
                "gross_amount" => $notif->gross_amount,
                "raw" => \json_encode($notif),
            ]);

            $trx->saveOrFail();

            return ResponseFormatter::success("Transaction success", 200, [
                "transaction_detail" => $trx
            ]);
        } catch (Exception $e) {
            return ResponseFormatter::error("Transaction failed", 500, $e->getMessage());
        }
    }
}