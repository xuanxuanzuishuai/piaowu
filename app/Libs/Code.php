<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/25
 * Time: 3:55 PM
 */

namespace App\Libs;


class Code
{
    public $id;
    public $activeTime;
    public $duration;
    public $start;
    public $end;

    public function __construct($id, $activeTime, $duration)
    {
        $this->id = $id;
        $this->activeTime = $activeTime;
        $this->duration = $duration;
    }
}