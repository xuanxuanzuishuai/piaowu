<?php
namespace App\Services;

use App\Libs\DictConstants;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;

class RefereeAwardService
{
    /**
     * 前端传相应的期望节点
     */
    const EXPECT_REGISTER          = 1; //注册
    const EXPECT_TRAIL_PAY         = 2; //付费体验卡
    const EXPECT_YEAR_PAY          = 3; //付费年卡
    const EXPECT_FIRST_NORMAL      = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    /**
     * 节点对应的所有task
     * @param $node
     * @return false|string[]
     */
    public static function getNodeRelateTask($node)
    {
        return explode(',', DictConstants::get(DictConstants::NODE_RELATE_TASK, $node));
    }

    /**
     * @return int
     * 当前生效的转介绍注册任务
     */
    public static function getDssRegisterTaskId()
    {
        $arr = self::getNodeRelateTask(self::EXPECT_REGISTER);
        return reset($arr);
    }

    /**
     * @param int $index
     * @return int
     * 当前生效的体验付费任务
     */
    public static function getDssTrailPayTaskId($index = 0)
    {
        $arr = self::getNodeRelateTask(self::EXPECT_TRAIL_PAY);
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return reset($arr);
    }

    /**
     * @param int $index
     * @return int
     * 当前生效的年卡付费任务
     */
    public static function getDssYearPayTaskId($index = 0)
    {
        $arr = self::getNodeRelateTask(self::EXPECT_YEAR_PAY);
        if (isset($arr[$index])) {
            return $arr[$index];
        }
        return reset($arr);
    }

    /**
     * 判断是否应该完成任务及发放奖励
     * @param $student
     * @param $package
     * @return bool
     */
    public static function dssShouldCompleteEventTask($student, $package)
    {
        // 真人业务不发奖
        if ($package['app_id'] != DssPackageExtModel::APP_AI) {
            return false;
        }

        // 升级
        if ($package['package_type'] > $student['has_review_course']) {
            return true;
        } else {
            // 年包 && 首购智能陪练正式课
            if ($package['package_type'] == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
                $res = DssGiftCodeModel::hadPurchasePackageByType($student['id'], DssPackageExtModel::PACKAGE_TYPE_NORMAL);
                $hadPurchaseCount = count($res);
                if ($hadPurchaseCount <= 1) {
                    return true;
                }
            }
        }
        return false;
    }
}