<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/12/15
 * Time: 11:29 AM
 */

namespace App\Services\Queue;

use App\Libs\SimpleLogger;
use Exception;
class QueueService
{

    private static function getDeferMax($count)
    {
        return $count; //红包发送大概一秒一个，目前处理直接定义
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public static function sendRedPack($data)
    {
        try {
            $deferMax = self::getDeferMax(count($data));
            foreach ($data as $award) {
                (new RedPack())->sendRedPack(['award_id' => $award['id']])->publish(rand(0, $deferMax));
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }
}