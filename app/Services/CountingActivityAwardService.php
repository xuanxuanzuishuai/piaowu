<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:39 AM
 */

namespace App\Services;

use App\Libs\SimpleLogger;
use App\Models\CountingActivityAwardModel;

class CountingActivityAwardService
{
    /**
     * 任务列表
     *
     * @param int $signId
     * @return bool
     */
    public static function grantCountingAward(int $signId): bool
    {
        $sign = CountingActivityAwardModel::getRecords([
            'sign_id' => $signId,
            'award_status' => CountingActivityAwardModel::SHIPPING_STATUS_BACK,
        ]);


        if (empty($sign)){
            SimpleLogger::error('select counting_activity_award data not found', ['sign_id' => $sign]);
            return false;
        }

        foreach ($sign as $item){

            switch ($item['type']) {
                case CountingActivityAwardModel::TYPE_GOLD_LEAF:
                    CountingActivityAwardService::grantGoldLeaf($item);
                    break;
                case CountingActivityAwardModel::TYPE_ENTITY:
                    CountingActivityAwardService::grantEntity($item);
                    break;
                default:
                    SimpleLogger::error('counting_activity_award data type error', [$item]);
            }
        }

        return true;
    }


    /**
     * 发放金叶子
     * @param array $data
     */
    private static function grantGoldLeaf(array $data)
    {


        CountingActivityAwardModel::updateStatus($data['id']);
    }

    /**
     * 请求erp邮递实物
     * @param array $data
     */
    private static function grantEntity(array $data)
    {

        CountingActivityAwardModel::updateStatus($data['id']);
    }




}
