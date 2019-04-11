<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2018/12/13
 * Time: 下午2:14
 */

namespace App\Libs;


class RefundBill
{
    public $data;//[bill_id => fee]
    public $operatorId;
    public $operatorName;

    public function __construct($data, $operatorId, $operatorName)
    {
        $this->data         = $data;
        $this->operatorId   = $operatorId;
        $this->operatorName = $operatorName;
    }
}