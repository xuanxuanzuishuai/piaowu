<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/02/15
 * Time: 15:41
 */

namespace App\Controllers\RealStudentOverseas;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\RealStudentOverseas\DeliveryService;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 海外业务：真人业务线学生端推广页面接口控制器文件
 * Class Delivery
 * @package App\Routers
 */
class Delivery extends ControllerBase
{

    /**
     * 投放
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function deliveryV1(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required'
            ],
            [
                'key' => 'social_account',
                'type' => 'required',
                'error_code' => 'social_account_is_required'
            ],
            [
                'key' => 'social_account_type',
                'type' => 'required',
                'error_code' => 'social_account_type_is_required'
            ],
            [
                'key' => 'social_account_type',
                'type' => 'in',
                'value' => [1, 2],
                'error_code' => 'social_account_type_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['login_type'] = Constants::REAL_STUDENT_LOGIN_TYPE_MAIN_LESSON_H5;
        try {
            $studentData = DeliveryService::deliveryV1(['params' => $params]);
        } Catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $studentData);
    }

}
