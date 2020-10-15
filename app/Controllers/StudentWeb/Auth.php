<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 7:48 PM
 */
namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Models\StudentModel;
use App\Services\CommonServiceForApp;
use App\Services\StudentService;
use App\Services\StudentServiceForApp;
use App\Services\StudentServiceForWeb;
use App\Services\TrackService;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Auth extends ControllerBase
{
    public function register(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'validate_code_is_required'
            ],
            [
                'key' => 'code',
                'type' => 'regex',
                'value' => '/[0-9]{4}/',
                'error_code' => 'validate_code_error'
            ],
            [
                'key' => 'ref_mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();

        try {
            $db->beginTransaction();
            $data = StudentServiceForWeb::register($params['mobile'],
                $params['code'],
                $params['ref_mobile'],
                $params['country_code']);
            $db->commit();

        } catch (RunTimeException $e) {
            $db->rollBack();
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    public function AIReferrerRegister(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules  = [
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'regex',
                'value'      => '/^[0-9]{11}$/',
                'error_code' => 'mobile_format_error'
            ],
            [
                'key'        => 'code',
                'type'       => 'required',
                'error_code' => 'validate_code_is_required'
            ],
            [
                'key'        => 'code',
                'type'       => 'regex',
                'value'      => '/[0-9]{4}/',
                'error_code' => 'validate_code_error'
            ],
            [
                'key'        => 'channel',
                'type'       => 'required',
                'error_code' => 'channel_is_required'
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (!CommonServiceForApp::checkValidateCode($params['mobile'], $params['code'], $params['country_code'])) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'));
        }

        $stu = StudentService::getStudentByMobile($params['mobile']);
        if(!empty($stu)) {
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => ['has_register' => true],
            ]);
        }

        list($lastId, $isNew) = StudentServiceForApp::studentRegister(
            $params['mobile'],
            $params['channel'],
            null,
            $params['referee_id'],
            $params['country_code']
        );
        if(empty($lastId)) {
            return $response->withJson(Valid::addAppErrors([], 'student_register_fail'));
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['has_register' => !$isNew]
        ]);
    }

    public function validateCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'country_code',
                'type' => 'integer',
                'error_code' => 'country_code_must_be_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'],
            CommonServiceForApp::SIGN_STUDENT_APP, $params['country_code']);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return HttpHelper::buildResponse($response, []);
    }

    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'validate_code_is_required'
            ],
            [
                'key' => 'code',
                'type' => 'regex',
                'value' => '/[0-9]{4}/',
                'error_code' => 'validate_code_error'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();

        $channelId = $params['channel_id'] ?? StudentModel::CHANNEL_SPACKAGE_LANDING;
        $adChannel = $params['ad'];
        $adParams = [];

        switch ($adChannel) {
            case TrackService::CHANNEL_GDT: // 广点通和微信参数共通
            case TrackService::CHANNEL_WX:
                if (!empty($params['qz_gdt'])) {
                    $adParams['qz_gdt'] = $params['qz_gdt'];
                    $adParams['callback'] = $params['qz_gdt'];
                } elseif (!empty($params['gdt_vid'])) {
                    $adParams['gdt_vid'] = $params['gdt_vid'];
                    $adParams['callback'] = $params['gdt_vid'];
                }
                $adParams['wx_code'] = $params['wx_code'] ?? '';
                break;

            case TrackService::CHANNEL_OCEAN_LEADS: // 抖音
                $adParams['ad_id'] = $params['adid'] ?? 0;
                $adParams['callback'] = urlencode($params['ref']) ?? '';
                break;

            case TrackService::CHANNEL_BAIDU: // 百度
                $adParams['callback'] = urlencode($params['ref']) ?? '';
                break;

            case TrackService::CHANNEL_KS: // 快手
                $adParams['callback'] = $params['callback'] ?? '';
                break;
        }

        try {
            $db->beginTransaction();
            $data = StudentServiceForWeb::mobileLogin(
                $params['mobile'],
                $params['code'],
                $channelId,
                $adChannel,
                $adParams,
                $params['referee_id'] ?? NULL,
                $params['country_code'] ?? NULL
            );
            $db->commit();

        } catch (RunTimeException $e) {
            $db->rollBack();
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'id' => $data['id'],
            'uuid' => $data['uuid']
        ]);
    }

    public function getWxAppId(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $appInfo = WeChatService::getWeCHatAppIdSecret(
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT);

        return HttpHelper::buildResponse($response, [
            'appId' => $appInfo["app_id"]
        ]);
    }
}