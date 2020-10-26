<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/24
 * Time: 7:30 PM
 */

namespace App\Libs;

class CodeConsume
{

    public $list;

    const DAY = 86400;

    public function __construct($list)
    {
        $this->list = $list;
        foreach ($this->list as $k => $v) {
            if ($k == 0) {
                $v->start = $v->activeTime;
                $v->end = $v->activeTime + $v->duration * self::DAY;
            } else {
                $prev = $this->list[$k - 1];
                if ($v->activeTime <= $prev->end) {
                    $v->start = $prev->end + 1;
                } else {
                    $v->start = $v->activeTime;
                }
                $v->end = $v->start + $v->duration * self::DAY;
            }
            $this->list[$k] = $v;
        }
    }


    public function between($time, Code $v)
    {
        return $time >= $v->start && $time <= $v->end;
    }

    // _consume返回正在使用哪个包
    // 返回使用包的ID，已经消耗了多少天，还能使用多少天
    public function _consume()
    {
        $now = time();
        foreach ($this->list as $v) {
            if (self::between($now, $v)) {
                return [$v->id, intval(($now - $v->start) / self::DAY), intval(($v->end - $now) / self::DAY)];
            }
        }
        return false;
    }

    // consume返回指定包已经消耗了多少天，还能使用多少天
    public function consume($codeId)
    {
        $current = self::_consume();
        if ($current !== false && $current[0] == $codeId) {
            return $current;
        }

        $map = array_column($this->list, null, 'id');
        $v = $map[$codeId];

        $now = time();

        if (self::between($now, $v)) {
            return [$v->id, intval(($now - $v->start) / self::DAY), intval(($v->end - $now) / self::DAY)];
        } elseif ($now <= $v->start) {
            return [$v->id, 0, $v->duration];
        }
        return [$v->id, $v->duration, 0];
    }
}
