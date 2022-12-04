<?php

namespace App\Models;


class ReceiptLogInfoModel extends Model
{
    public static $table = 'receipt_log_info';

    public static function addLog($receiptId, $log)
    {
        self::insertRecord(
            [
                'receipt_id' => $receiptId,
                'log_info' => $log,
                'create_time' => time()
            ]
        );
    }

}
