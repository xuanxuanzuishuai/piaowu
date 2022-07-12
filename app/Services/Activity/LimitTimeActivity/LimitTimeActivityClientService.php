<?php

namespace App\Services\Activity\LimitTimeActivity;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\TemplatePosterModel;
use App\Services\Activity\LimitTimeActivity\TraitService\DssService;
use App\Services\MiniAppQrService;
use App\Services\PosterService;
use App\Services\PosterTemplateService;
use App\Services\StudentService;
use App\Services\WeekActivityService;

/**
 * 限时有奖活动客户端功能服务类
 */
class LimitTimeActivityClientService
{
    /**
     * 获取服务实例
     * @param int $appId
     * @param array $studentInfo
     * @param string $fromType
     * @return DssService
     * @throws RunTimeException
     */
    public static function getServiceObj(int $appId, string $fromType, array $studentInfo): DssService
    {
        switch ($appId) {
            case Constants::SMART_APP_ID:
                $serviceObj = new DssService($studentInfo, $fromType);
                break;
            case Constants::REAL_APP_ID:
                echo 99;
                break;
            default:
                throw new RunTimeException(['app_id_invalid']);
        }
        return $serviceObj;
    }

    /**
     * 获取活动基础数据
     * @param DssService $serviceObj
     * @return array
     * @throws RunTimeException
     */
    public static function baseData(DssService $serviceObj): array
    {
        $data = [
            'list'                  => [],// 海报列表
            'activity'              => [],// 活动详情
            'student_info'          => [],// 学生详情
            "is_have_activity"      => false,//是否有可参与的活动
            "no_re_activity_reason" => WeekActivityService::ACTIVITY_RETRY_UPLOAD_NO,//是否有补卡资格
        ];
        //获取活动数据
        $serviceObj->getActivity($data);
        if (!empty($data['activity'])) {
            $data['is_have_activity'] = true;
        }
        return $data;
    }

    public static function joinRecords(DssService $serviceObj, int $page, int $limit):array
    {
        //获取参与记录
        return $serviceObj->joinRecords($page, $limit);
    }


}