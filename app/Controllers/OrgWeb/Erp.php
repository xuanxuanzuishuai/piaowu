<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/30
 * Time: 5:21 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\Valid;
use App\Models\GiftCodeModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModelForApp;
use App\Services\AIBillService;
use App\Services\CommonServiceForApp;
use App\Services\ErpService;
use App\Services\ReviewCourseService;
use App\Services\UserPlayServices;
use App\Services\AppVersionService;
use App\Services\CollectionService;
use App\Models\AppVersionModel;
use App\Services\StudentService;
use App\Services\WeChatCSService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Erp extends ControllerBase
{
    public function exchangeGiftCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'in',
                'value' => [GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE,
                    GiftCodeModel::BUYER_TYPE_ERP_ORDER, GiftCodeModel::BUYER_TYPE_AI_REFERRAL],
                'error_code' => 'exchange_type_invalid'
            ],
            [
                'key' => 'bill_amount',
                'type' => 'required',
                'error_code' => 'bill_amount_is_required'
            ],
            [
                'key' => 'duration_num',
                'type' => 'numeric',
                'error_code' => 'bill_amount_is_required'
            ],
            [
                'key' => 'duration_unit',
                'type' => 'in',
                'value' => [GiftCodeModel::CODE_TIME_DAY, GiftCodeModel::CODE_TIME_MONTH, GiftCodeModel::CODE_TIME_YEAR],
                'error_code' => 'bill_amount_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        if (empty($params['duration_num']) || empty($params['duration_unit'])) {
            // 默认订单和换购激活码时长1年
            $giftCodeNum = 1;
            $giftCodeUnit = GiftCodeModel::CODE_TIME_YEAR;
        } else {
            $giftCodeNum = $params['duration_num'];
            $giftCodeUnit = $params['duration_unit'];
        }

        $autoApply = ($params['auto_apply']) || ($params['app_id'] == ErpService::APP_ID_AI);
        if ($autoApply) {
            $parentBillId = !empty($params['parent_bill_id']) ? $params['parent_bill_id'] : $params['bill_id'];
            $autoApply = AIBillService::autoApply($parentBillId);
        }

        list($errorCode, $giftCodes) = ErpService::exchangeGiftCode(
            [
                'uuid' => $params['uuid'],
                'mobile' => $params['mobile'],
                'name' => $params['name'],
                'gender' => $params['gender'],
                'birthday' => $params['birthday']
            ],
            $params['type'],
            $giftCodeNum,
            $giftCodeUnit,
            $autoApply,
            [
                'bill_id' => $params['bill_id'] ?? '',
                'parent_bill_id' => $params['parent_bill_id'] ?? '',
                'bill_amount' => (int)$params['bill_amount'],
                'bill_app_id' => (int)$params['app_id'],
                'bill_package_id' => (int)$params['package_id'],
            ]
        );

        if (!empty($errorCode)) {
            $result = Valid::addErrors([], 'uuid', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        if (!$autoApply && !empty($giftCodes)) {
            // 换购上线前已经提前发送激活码的用户
            $sms->sendExchangeGiftCode($params['mobile'],
                implode(',', $giftCodes),
                CommonServiceForApp::SIGN_STUDENT_APP);
        }

        // 点评课支付成功，发送点评课短信
        $reviewCourseType = ReviewCourseService::getBillReviewCourseType($params['package_id']);
        if ($reviewCourseType != ReviewCourseModel::REVIEW_COURSE_NO && !empty($giftCodes)) {
            //获取学生信息
            $student = StudentService::getByUuid($params['uuid']);
            $wechatcs = WeChatCSService::getWeChatCS();
            // 更新点评课标记
            $wechatcsId = empty($student['wechatcs_id']) ? $wechatcs['id'] : null;
            ReviewCourseService::updateReviewCourseFlag($student['id'], $reviewCourseType, $wechatcsId);
            //更新学生分配班级信息 一个学员只能分配给一个班级(当前只有体验课课包类型才允许分班)
            if (empty($student['collection_id']) && ($reviewCourseType == ReviewCourseModel::REVIEW_COURSE_49)) {
                ReviewCourseService::updateCollectionData($student, $reviewCourseType,$params['package_id']);
            }
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'gift_codes' => $giftCodes
            ]
        ], StatusCode::HTTP_OK);
    }

    public static function abandonGiftCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'bill_id',
                'type' => 'required',
                'error_code' => 'bill_id_is_required'
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }


        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = ErpService::abandonGiftCode($params['bill_id'], $params['uuid']);
        if (!empty($errorCode)) {
            $db->rollBack();
            $result = Valid::addErrors([], 'bill_id', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS
        ], StatusCode::HTTP_OK);
    }

    public static function recentDetail(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'time',
                'type' => 'required',
                'error_code' => 'time_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $uuid = $params['uuid'];
        $time = $params['time'];
        $student = StudentModelForApp::getStudentInfo(null, null, $uuid);
        if (empty($student)) {
            $ret = ['lessons' => [], 'days' => 0, 'lesson_count' => 0, 'token' => ''];
            return $response->withJson(['code' => 0, 'data' => $ret], StatusCode::HTTP_OK);
        }
        $appVersion = AppVersionService::getPublishVersionCode(
            AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS);
        $ret = UserPlayServices::pandaPlayDetail($student['id'], $appVersion, 7, $time);
        return $response->withJson(['code' => 0, 'data' => $ret], StatusCode::HTTP_OK);
    }


    public static function recentPlayed(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'time',
                'type' => 'required',
                'error_code' => 'time_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $uuid = $params['uuid'];
        $time = $params['time'];
        $student = StudentModelForApp::getStudentInfo(null, null, $uuid);
        if (empty($student)) {
            $ret = ['is_ai_student' => false, 'days' => 0, 'lesson_count' => 0];
            return $response->withJson(['code' => 0, 'data' => $ret], StatusCode::HTTP_OK);
        }
        $ret = UserPlayServices::pandaPlayBrief($student['id'], 7, $time);
        $ret['is_ai_student'] = $student['sub_start_date'] > 0;
        return $response->withJson(['code' => 0, 'data' => $ret], StatusCode::HTTP_OK);
    }

    public function studentGiftCode(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $uuid = $params['uuid'];
        $student = StudentModelForApp::getStudentInfo(null, null, $uuid);
        if (empty($student)) {
            $result = Valid::addErrors([], 'uuid', 'unknown_user');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ret = StudentService::selfGiftCode($student['id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'gift_codes' => $ret
            ]
        ], StatusCode::HTTP_OK);
    }

    public function giftCodeTransfer(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'src_uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'dst_uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errorCode = ErpService::giftCodeTransfer($params['src_uuid'], $params['dst_uuid']);

        if (!empty($errorCode)) {
            $db->rollBack();
            $result = Valid::addErrors([], 'bill_id', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS
        ], StatusCode::HTTP_OK);
    }
}