<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/1
 * Time: 下午3:07
 */

namespace App\Controllers\ExamMinApp;

use App\Libs\HttpHelper;
use App\Services\StudentForMinAppService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use App\Controllers\ControllerBase;

class Student extends ControllerBase
{
    // https://developers.weixin.qq.com/miniprogram/dev/framework/open-ability/getPhoneNumber.html
    public function register(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'iv',
                'type'       => 'required',
                'error_code' => 'iv_is_required'
            ],
            [
                'key'        => 'encrypted_data',
                'type'       => 'required',
                'error_code' => 'encrypted_data_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $lastId = StudentForMinAppService::register(
            $this->ci['exam_openid'], $params['iv'], $params['encrypted_data'], $this->ci['exam_session_key']
        );

        return HttpHelper::buildResponse($response, ['last_id' => $lastId]);
    }
}