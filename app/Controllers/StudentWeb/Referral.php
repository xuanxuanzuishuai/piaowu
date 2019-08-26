<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/26
 * Time: 1:41 PM
 */

namespace App\Controllers\StudentWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\StudentServiceForWeb;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Referral extends ControllerBase
{
    public function referrerInfo(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => '/^[0-9]{11}$/',
                'error_code' => 'mobile_format_error'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $data = StudentServiceForWeb::referrerInfo($params['mobile']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $data);
    }
}