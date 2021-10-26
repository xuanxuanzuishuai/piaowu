<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 15:41
 */

namespace App\Controllers\Real;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Models\RealLandingPromotionRecordModel;
use App\Services\RealLandingPromotionService;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * 真人业务线学生端推广页面接口控制器文件
 * Class StudentActivity
 * @package App\Routers
 */
class LandingPromotion extends ControllerBase
{
    /**
     * 主课领课数据记录:2021.10.17
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function mainCoursePromotedRecordV1(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['student_uuid'] = $params['uuid'] ?? 0;
        $params['channel_id'] = $params['channel_id'] ?? 0;
        $result = RealLandingPromotionService::takeLessonRecord(
            $params['student_uuid'],
            RealLandingPromotionRecordModel::MAIN_COURSE_PROMOTED_V1,
            $params['channel_id'],
            true);
        return HttpHelper::buildResponse($response, $result);
    }
}
