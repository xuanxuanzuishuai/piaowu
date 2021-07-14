<?php
/**
 * 计数任务奖品配置
 *
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:35 AM
 */

namespace App\Models;


use App\Libs\SimpleLogger;

class CountingAwardConfigModel extends Model
{
    public static $table = "counting_award_config";

    //状态
    const NORMAL_STATUS = 1; //有效
    const INVALID_STATUS = 2; //无效

    //类型
    const GOLD_LEAF_TYPE = 1; //类型 金叶子
    const PRODUCT_TYPE = 2; //类型 实物


    const UNIQUE_ID_PREFIX = '1001';


    public static function grantAward(array $activityAward,int $signId)
    {
        if (empty($activityAward) || empty($signId)) return false;
        $insertRow = self::batchInsert($activityAward);

        if (empty($insertRow)) {
            SimpleLogger::error('counting award config insert fail', $activityAward);
            return false;
        }

        $updateRow = CountingActivitySignModel::batchUpdateRecord([
            'award_status' => CountingActivitySignModel::AWARD_STATUS_RECEIVED,
            'award_time'   => time(),
            'update_time'  => time(),
        ], [
            'id'           => $signId,
            'award_status' => CountingActivitySignModel::AWARD_STATUS_PENDING,
        ]);
        if (empty($updateRow)) {
            SimpleLogger::error('update counting_activity_sign data error', [$signId]);
            return false;
        }

        return true;

    }
}
