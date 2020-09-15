<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/30
 * Time: 5:21 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\GiftCodeModel;
use App\Models\GoodsV1Model;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Models\PackageExtModel;
use App\Models\StudentModelForApp;
use App\Services\AIBillService;
use App\Services\CommonServiceForApp;
use App\Services\DictService;
use App\Services\ErpService;
use App\Services\GiftCodeService;
use App\Services\Queue\QueueService;
use App\Services\ReviewCourseService;
use App\Services\UserPlayServices;
use App\Services\AppVersionService;
use App\Models\AppVersionModel;
use App\Services\StudentService;
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
                'error_code' => 'duration_num_is_required'
            ],
            [
                'key' => 'duration_unit',
                'type' => 'in',
                'value' => [GiftCodeModel::CODE_TIME_DAY, GiftCodeModel::CODE_TIME_MONTH, GiftCodeModel::CODE_TIME_YEAR],
                'error_code' => 'duration_unit_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $giftCodeData = GiftCodeService::getGiftCodeByBill($params['bill_id'], $params['parent_bill_id']);
        if(!empty($giftCodeData)) {
            return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => ['gift_codes' => []]], StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        if (empty($params['package_id'])) {
            // 赠单没有package_id
            $package = null;

        } else {
            $package = PackageExtModel::getByPackageId($params['package_id']);
            if (empty($package)) {
                Util::errorCapture('DSS package not found', ['$params' => $params]);
            }
        }

        // TODO: get duration from local package data
        if (empty($params['duration_num']) || empty($params['duration_unit'])) {
            // 默认订单和换购激活码时长1年
            $giftCodeNum = 1;
            $giftCodeUnit = GiftCodeModel::CODE_TIME_YEAR;
        } else {
            $giftCodeNum = $params['duration_num'];
            $giftCodeUnit = $params['duration_unit'];
        }

        $autoApply = true;

        // 非DSS发起的订单，不自动发货
        $parentBillId = !empty($params['parent_bill_id']) ? $params['parent_bill_id'] : $params['bill_id'];
        if (!AIBillService::autoApply($parentBillId)) {
            $autoApply = false;
        };

        if (!empty($package) && $package['apply_type'] == PackageExtModel::APPLY_TYPE_SMS) {
            $autoApply = false;
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
                'employee_uuid' => $params['employee_uuid'] ?? ''
            ]
        );

        if (!empty($errorCode)) {
            $db->rollBack();
            Util::errorCapture('DSS exchangeGiftCode error', [
                '$params' => $params,
                '$errorCode' => $errorCode,
                '$giftCodes' => $giftCodes,
            ]);

            $result = Valid::addErrors([], 'uuid', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        if (!$autoApply) {
            // 换购上线前已经提前发送激活码的用户
            $sms->sendExchangeGiftCode($params['mobile'],
                implode(',', $giftCodes),
                CommonServiceForApp::SIGN_STUDENT_APP, $params['country_code']);
        }

        if (!empty($package)) {
            // 更新用户点评课数据，转介绍任务，付费通知:增加新旧分配规则切换标志防止新功能上线出现问题（待新功能稳定，删除此判断）
            $isLeadsPoolAllot = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_COLLECTION_ALLOT_TYPE,'leads_pool_allot');
            if (empty($isLeadsPoolAllot)) {
                ReviewCourseService::updateStudentReviewCourseStatusV1($params['uuid'],
                    $package['package_type'],
                    $package['trial_type'],
                    $package['app_id'],
                    $package);
            } else {
                QueueService::newLeads([
                    'uuid' => $params['uuid'],
                    'package' => $package
                ]);
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


        $student = StudentModelForApp::getStudentInfo(null, null, $params['uuid']);
        if (empty($student)) {
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => [
                    'lesson_count' => 0, // 总练习曲目
                    'days' => 0, // 总练习天数
                    'token' => '',
                    'lessons' => []
                ]
            ], StatusCode::HTTP_OK);
        }

        $appVersion = AppVersionService::getPublishVersionCode(
            AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS);
        $ret = UserPlayServices::pandaPlayRecord($student['id'], $appVersion, 7, $params['time']);

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

        $student = StudentModelForApp::getStudentInfo(null, null, $params['uuid']);
        if (empty($student)) {
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => [
                    'is_ai_student' => false,
                    'days' => 0,
                    'lesson_count' => 0
                ]
            ], StatusCode::HTTP_OK);
        }

        $ret = UserPlayServices::pandaPlayRecordBrief($student['id'], 7, $params['time']);
        $ret['is_ai_student'] = $student['sub_start_date'] > 0;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $ret
        ], StatusCode::HTTP_OK);
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

    public function createGiftCodeV1(Request $request, Response $response)
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
                'value' => [GiftCodeModel::BUYER_TYPE_ERP_ORDER, GiftCodeModel::BUYER_TYPE_AI_REFERRAL, GiftCodeModel::BUYER_TYPE_STUDENT],
                'error_code' => 'exchange_type_invalid'
            ],
            [
                'key' => 'bill_amount',
                'type' => 'required',
                'error_code' => 'bill_amount_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $giftCodeData = GiftCodeService::getGiftCodeByBill($params['bill_id'], $params['parent_bill_id']);
        if(!empty($giftCodeData)) {
            return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => ['gift_codes' => []]], StatusCode::HTTP_OK);
        }

        if (!empty($params['package_id'])) {
            // 购买产品包
            $package = ErpPackageV1Model::getPackage($params['package_id']);
            if (empty($package)) {
                Util::errorCapture('DSS package not found', ['$params' => $params]);
                return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => ['gift_codes' => []]], StatusCode::HTTP_OK);
            }

            $durationNum = $package['duration_num'];
            $autoApply = ($package['apply_type'] == PackageExtModel::APPLY_TYPE_AUTO) ? true : false;
        } elseif (!empty($params['goods_id'])) {
            // 赠送产品
            $goods = GoodsV1Model::getGoods($params['goods_id']);
            $durationNum = $goods['duration_num'];
            $autoApply = ($goods['apply_type'] == PackageExtModel::APPLY_TYPE_AUTO) ? true : false;
        } else {
            Util::errorCapture('no package_id and goods_id', ['$params' => $params]);
            return $response->withJson(['code' => Valid::CODE_SUCCESS, 'data' => ['gift_codes' => []]], StatusCode::HTTP_OK);
        }


        $db = MysqlDB::getDB();
        $db->beginTransaction();

        list($errorCode, $giftCodes) = ErpService::exchangeGiftCode(
            [
                'uuid' => $params['uuid'],
                'mobile' => $params['mobile'],
                'name' => $params['name'],
                'gender' => $params['gender'],
                'birthday' => $params['birthday']
            ],
            $params['type'],
            $durationNum,
            GiftCodeModel::CODE_TIME_DAY,
            $autoApply,
            [
                'bill_id' => $params['bill_id'] ?? '',
                'parent_bill_id' => $params['parent_bill_id'] ?? '',
                'bill_amount' => (int)$params['bill_amount'],
                'bill_app_id' => (int)$params['app_id'],
                'bill_package_id' => (int)$params['package_id'],
                'employee_uuid' => $params['employee_uuid'] ?? '',
                'package_v1' => GiftCodeModel::PACKAGE_V1
            ]
        );

        if (!empty($errorCode)) {
            $db->rollBack();
            Util::errorCapture('DSS exchangeGiftCode error', [
                '$params' => $params,
                '$errorCode' => $errorCode,
                '$giftCodes' => $giftCodes,
            ]);

            $result = Valid::addErrors([], 'uuid', $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        if (!$autoApply) {
            // 换购上线前已经提前发送激活码的用户
            $sms->sendExchangeGiftCode($params['mobile'],
                implode(',', $giftCodes),
                CommonServiceForApp::SIGN_STUDENT_APP, $params['country_code']);
        }

        if (!empty($package)) {
            // 更新用户点评课数据，转介绍任务，付费通知:增加新旧分配规则切换标志防止新功能上线出现问题（待新功能稳定，删除此判断）
            $isLeadsPoolAllot = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_COLLECTION_ALLOT_TYPE,'leads_pool_allot');
            if (empty($isLeadsPoolAllot)) {
                ReviewCourseService::updateStudentReviewCourseStatusV1($params['uuid'],
                    $package['package_type'],
                    $package['trial_type'],
                    $package['app_id'],
                    $package);
            } else {
                QueueService::newLeads([
                    'uuid' => $params['uuid'],
                    'package' => $package
                ]);
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'gift_codes' => $giftCodes
            ]
        ], StatusCode::HTTP_OK);
    }
}