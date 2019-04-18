<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/18
 * Time: 上午10:03
 */

namespace App\Libs;


class ResponseError
{
    private $errorMsg;

    public function __construct($errorMsg)
    {
        $this->errorMsg = $errorMsg;
    }

    public function getErrorMsg()
    {
        return $this->errorMsg;
    }
}