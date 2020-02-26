<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/25
 * Time: 11:08 AM
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Models\StudentModel;
use App\Services\ErpReferralService;
use Slim\Http\Request;
use Slim\Http\Response;

class Referral extends ControllerBase
{

    /**
     * 转介绍奖励列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function userAwardList(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModel::getById($studentId);

        if (empty($student)) {
            $e = new RunTimeException(['student_not_exist']);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        try {
            $ret = ErpReferralService::getUserAwardList($student['uuid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }

}