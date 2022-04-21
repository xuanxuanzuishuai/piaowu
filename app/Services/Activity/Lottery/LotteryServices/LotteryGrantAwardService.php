<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Erp;
use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Services\Queue\ErpStudentAccountTopic;
use App\Services\Queue\QueueService;

/**
 * 发送虚拟奖品
 * Class LotteryGrantAwardService
 * @package App\Services\Activity\Lottery\LotteryServices
 */
class LotteryGrantAwardService
{
    public static function grantAward($awardType, $params)
    {
        switch ($awardType) {
            case Constants::AWARD_TYPE_TIME:
                return self::grantTime($params);
            case Constants::AWARD_TYPE_GOLD_LEAF:
                return self::grantGoldLeaf($params);
            case Constants::AWARD_TYPE_MAGIC_STONE:
                return self::grantMagicStone($params);
            case Constants::AWARD_TYPE_TYPE_ENTITY:
                return self::grantEntity($params);
            case Constants::AWARD_TYPE_TYPE_LESSON:
                return self::grantLesson($params);
            case Constants::AWARD_TYPE_TYPE_NOTE:
                return self::grantNote($params);
            default:
                return false;
        }
    }

    /**
     * 智能业务
     * 赠送时长
     * @param $params
     * @return bool
     */
    public static function grantTime($params)
    {
        QueueService::giftDuration($params['uuid'],
            DssGiftCodeModel::APPLY_TYPE_AUTO,
            $params['common_award_amount'],
            DssGiftCodeModel::BUYER_TYPE_STUDENT
        );
        return true;
    }

    /**
     * 智能业务
     * 方法金叶子
     * @param $params
     * @return bool
     */
    public static function grantGoldLeaf($params)
    {
        $request = [
            'app_id'        => Constants::SMART_APP_ID,
            'student_uuid'  => $params['uuid'],
            'sub_type'      => Constants::ERP_ACCOUNT_NAME_GOLD_LEFT,
            'source_type'   => ErpStudentAccountModel::LOTTERY_ACTION,
            'num'           => $params['common_award_amount'],
            'remark'        => $params['remark'],
            'batch_id'      => $params['batch_id'],
            'operator_type' => 0,
            'operator_id'   => 10000,
        ];
        (new Erp())->grantGoldLeafNote($request);
        return true;
    }

    /**
     * 真人业务
     * 发放魔法石
     * @param $params
     * @return bool
     */
    public static function grantMagicStone($params)
    {
        try {
            $data = [
                'app_id'       => Constants::REAL_APP_ID,
                'student_uuid' => $params['uuid'],
                'sub_type'     => Constants::ERP_ACCOUNT_NAME_MAGIC,
                'source_type'  => ErpStudentAccountModel::LOTTERY_ACTION,
                'remark'       => $params['remark'],
                'num'          => $params['common_award_amount'],
                'batch_id'     => $params['batch_id'],
            ];
            (new ErpStudentAccountTopic())->erpNormalCredited($data)->publish();
        } catch (\Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 真人/智能业务线
     * 发放实物奖品
     * @param $params
     * @return bool
     */
    public static function grantEntity($params)
    {
        $params = [
            'order_id'   => $params['unique_id'],
            'plat_id'    => Constants::UNIQUE_ID_PREFIX,
            'app_id'     => $params['app_id'],
            'sale_shop'  => $params['sale_shop'],
            'goods_id'   => $params['goods_id'],
            'goods_code' => $params['goods_code'],
            'mobile'     => $params['mobile'],
            'uuid'       => $params['uuid'],
            'num'        => $params['amount'],
            'address_id' => $params['erp_address_id'],
        ];

        (new Erp())->deliverGoods($params);
        return true;

    }

    /**
     * 真热业务线
     * 赠送课程
     * @param $params
     * @return bool
     */
    public static function grantLesson($params)
    {
        $request = [
            'course_id'   => $params['common_award_id'],
            'student_id'  => $params['student_id'],
            'free_num'    => $params['common_award_amount'],
            'course_type' => ErpStudentAccountModel::TYPE_LOTTERY_ACTIVE_HIS_AWARD_COURSE,
            'remark'      => $params['remark']
        ];
        (new Erp())->grantCourse($request);
        return true;
    }

    /**
     * 赠送音符
     * @param $params
     * @return bool
     */
    public static function grantNote($params)
    {
        $request = [
            'app_id'        => Constants::SMART_APP_ID,
            'student_uuid'  => $params['uuid'],
            'sub_type'      => Constants::ERP_ACCOUNT_NAME_MAGIC,
            'source_type'   => ErpStudentAccountModel::LOTTERY_ACTION,
            'num'           => $params['common_award_amount'],
            'remark'        => $params['remark'],
            'batch_id'      => $params['batch_id'],
            'operator_type' => 0,
            'operator_id'   => 10000,
        ];
        (new Erp())->grantGoldLeafNote($request);
        return true;
    }
}