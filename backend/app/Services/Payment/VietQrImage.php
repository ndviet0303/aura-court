<?php

namespace App\Services\Payment;

/** VietQR image URL for a fixed amount and transfer content, shared by owner attempts and P2P resale. */
final class VietQrImage
{
    public static function url(string $bankCode, string $accountNumber, string $accountName, int $amount, string $content): string
    {
        $bankCode = strtoupper($bankCode);
        $bankCode = $bankCode === 'MBBANK' ? 'MB' : $bankCode;
        $query = http_build_query([
            'amount' => $amount,
            'addInfo' => $content,
            'accountName' => $accountName,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://img.vietqr.io/image/'.rawurlencode($bankCode).'-'.rawurlencode($accountNumber).'-compact2.png?'.$query;
    }
}
