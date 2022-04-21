<?php

namespace App\Services\Activity\Lottery\LotteryServices;

use App\Libs\Erp;
use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\LotteryAwardRecordModel;
use App\Services\Queue\ErpStudentAccountTopic;
use App\Services\Queue\QueueService;

/**
 * 发送虚拟奖品
 * Class LotteryGrantAwardService
 * @package App\Services\Activity\Lottery\LotteryServices
 */
class LotteryGrantAwardService
{
    public static function grantAward($params)
    {
        switch ($params['type']) {
            case Constants::AWARD_TYPE_TIME:
                $res = self::grantTime($params);
                break;
            case Constants::AWARD_TYPE_GOLD_LEAF:
                $res = self::grantGoldLeaf($params);
                break;
            case Constants::AWARD_TYPE_MAGIC_STONE:
                $res = self::grantMagicStone($params);
                break;
            case Constants::AWARD_TYPE_TYPE_ENTITY:
                $res = self::grantEntity($params);
                break;
            case Constants::AWARD_TYPE_TYPE_LESSON:
                $res = self::grantLesson($params);
                break;
            case Constants::AWARD_TYPE_TYPE_NOTE:
                $res = self::grantNote($params);
                break;
            default:
                return false;
        }
        if ($res) {
            $update = [
                'batch_id'    => $params['batch_id'] ?? '',
                'grant_state' => Constants::STATUS_TRUE
            ];
            LotteryAwardRecordModel::updateRecord($params['record_id'], $update);
        }
        return true;
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
        $response = (new Erp())->grantGoldLeafNote($request);
        if ($response['code'] != 0) {
            return false;
        }
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

        $response = (new Erp())->deliverGoods($params);
        if ($response['code'] != 0) {
            return false;
        }
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
        $response = (new Erp())->grantCourse($request);
        if ($response['code'] != 0) {
            return false;
        }
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
        $response = (new Erp())->grantGoldLeafNote($request);
        if ($response['code'] != 0) {
            return false;
        }
        return true;
    }
}