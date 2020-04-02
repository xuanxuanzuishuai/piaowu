<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/4/3
 * Time: 3:45 PM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\AIPlayRecordService;
use App\Services\AIPlayReportService;
use Slim\Http\Request;
use Slim\Http\Response;

class PlayReport extends ControllerBase
{

    /**
     * 练琴日报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function dayReport(Request $request, Response $response)
    {
        $params = $request->getParams();

        $studentId = $this->ci['user_info']['user_id'];

        try {
            $result = AIPlayReportService::getDayReport($studentId, $params["date"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }


    /**
     * 练琴日报(分享)
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sharedDayReport(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            $result = AIPlayReportService::getSharedDayReport($params["share_token"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }
}