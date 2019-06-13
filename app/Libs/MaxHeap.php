<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/6/12
 * Time: 14:13
 */

namespace App\Libs;

use SplHeap;

/**
 * 大根堆实现，用于对数组对象排序后的nLargest问题的场景
 * Class MaxHeap
 * @package App\Libs
 */
class MaxHeap extends SplHeap
{

    public function __construct($orderKey=null)
    {
        $this->orderKey = $orderKey;
    }

    public function compare($value1, $value2)
    {
        if(!empty($this->orderKey)){
            $value1 = $value1[$this->orderKey];
            $value2 = $value2[$this->orderKey];
        }
        if ($value1 === $value2){
            return 0;
        }
        return $value1 < $value2 ? -1 : 1;
    }

    public static function nLargest($heap, $n=3)
    {
        $ret = [];
        if($heap->isEmpty()){
            return $ret = [];
        }
        $heap->top();
        while ($heap->valid() and $n) {
            $item = $heap->current();
            $heap->next();
            array_push($ret, $item);
            $n -= 1;
        }
        return $ret;
    }
}
