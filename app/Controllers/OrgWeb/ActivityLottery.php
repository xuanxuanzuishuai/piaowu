<?php /** @noinspection ALL */

/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/03/12
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\Activity\Lottery\LotteryAdminService;
use App\Services\UniqueIdGeneratorService\DeliverIdGeneratorService;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use function Qiniu\base64_urlSafeEncode;

class ActivityLottery extends ControllerBase
{
    /**
     * 添加/编辑活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addOrUpdate(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'integer',
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 30,
                'error_code' => 'name_max_length_is_30'
            ],
            [
                'key'        => 'title_url',
                'type'       => 'required',
                'error_code' => 'title_url_is_required'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'min',
                'value'      => time(),
                'error_code' => 'start_time_must_greater_than_current_time'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'min',
                'value'      => time(),
                'error_code' => 'end_time_error'
            ],
            [
                'key'        => 'activity_desc',
                'type'       => 'required',
                'error_code' => 'activity_desc_is_required'
            ],
            [
                'key'        => 'activity_desc',
                'type'       => 'lengthMax',
                'value'      => 5000,
                'error_code' => 'activity_desc_length_error'
            ],
            [
                'key'        => 'user_source',
                'type'       => 'required',
                'error_code' => 'user_source_is_required'
            ],
            [
                'key'        => 'user_source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'user_source_value_error'
            ],
            [
                'key'        => 'app_id',
                'type'       => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key'        => 'app_id',
                'type'       => 'in',
                'value'      => [1, 8],
                'error_code' => 'app_id_is_error'
            ],
            [
                'key'        => 'max_hit_type',
                'type'       => 'required',
                'error_code' => 'max_hit_is_required'
            ],
            [
                'key'        => 'max_hit_type',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'max_hit_type_value_error'
            ],
            [
                'key'        => 'max_hit',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'max_hit_min_value_is_error'
            ],
            [
                'key'        => 'day_max_hit_type',
                'type'       => 'required',
                'error_code' => 'day_max_hit_is_required'
            ],

            [
                'key'        => 'day_max_hit_type',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'day_max_hit_type_value_error'
            ],
            [
                'key'        => 'day_max_hit',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'day_max_hit_min_value_is_error'
            ],
            [
                'key'        => 'awards',
                'type'       => 'required',
                'error_code' => 'award_params_is_required'
            ],
            [
                'key'        => 'enable_status',
                'type'       => 'in',
                'value'      => [2],
                'error_code' => 'enable_status_is_invalid'
            ],


        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['employee_uuid'] = $this->ci['employee']['uuid'];
        try {
            $res = LotteryAdminService::addOrUpdate($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 追加导流账户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function appendImportUser(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'is_cover',
                'type'       => 'in',
                'value'      => [0, 1],
                'error_code' => 'is_cover_value_error'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['is_cover'] = isset($params['is_cover']) ? $params['is_cover'] : Constants::STATUS_FALSE;
        try {
            $res = LotteryAdminService::appendImportUserData($params['op_activity_id'], $this->ci['employee']['uuid'],
                $params['is_cover']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 30,
                'error_code' => 'name_max_length_is_30'
            ],
            [
                'key'        => 'user_source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'user_source_value_error'
            ],
            [
                'key'        => 'show_status',
                'type'       => 'in',
                'value'      => [1, 3, 4, 5, 6],
                'error_code' => 'show_status_value_error'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $pageSize) = Util::formatPageCount($params);
        try {
            $res = LotteryAdminService::list($params, $page, $pageSize);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 活动详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::detail($params['op_activity_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 中奖信息记录列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function joinRecords(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $pageSize) = Util::formatPageCount($params);
        try {
            $res = LotteryAdminService::joinRecords($params, $page, $pageSize);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 修改奖品的收获地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateShippingAddress(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'record_id',
                'type'       => 'required',
                'error_code' => 'record_id_is_required'
            ],
            [
                'key'        => 'record_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'record_id_id_is_integer'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'student_name_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'regex',
                'value'      => Constants::MOBILE_REGEX,
                'error_code' => 'student_mobile_format_is_error'
            ],
            [
                'key'        => 'country_code',
                'type'       => 'required',
                'error_code' => 'country_code_is_required',
            ],
            [
                'key'        => 'province_code',
                'type'       => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key'        => 'city_code',
                'type'       => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key'        => 'district_code',
                'type'       => 'required',
                'error_code' => 'district_code_is_required'
            ],
            [
                'key'        => 'address',
                'type'       => 'required',
                'error_code' => 'student_address_is_required',
            ],
            [
                'key'        => 'is_default',
                'type'       => 'required',
                'error_code' => 'address_default_is_required',
            ],
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::updateShippingAddress($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 取消发货
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function cancelDeliver(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'record_id',
                'type'       => 'required',
                'error_code' => 'record_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::cancelDeliver($params, $this->ci['employee']['uuid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取物流详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function expressDetail(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'unique_id',
                'type'       => 'required',
                'error_code' => 'unique_id_is_required'
            ],
            [
                'key'        => 'unique_id',
                'type'       => 'length',
                'value'      => '14',
                'error_code' => 'unique_id_length_error'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::expressDetail($params['op_activity_id'], $params['unique_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 修改活动启用状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateEnableStatus(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'in',
                'value'      => [1, 2, 3],
                'error_code' => 'status_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = LotteryAdminService::updateEnableStatus($params['op_activity_id'], $params['status'],
                $this->ci['employee']['uuid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 导出中奖信息记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function exportRecords(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key'        => 'op_activity_id',
                'type'       => 'required',
                'error_code' => 'op_activity_id_is_required'
            ],
            [
                'key'        => 'op_activity_id',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'op_activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $fileName = LotteryAdminService::exportRecords($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        //        header(‘Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet‘);//告诉浏览器输出07Excel文件
        ////header(‘Content-Type:application/vnd.ms-excel‘);//告诉浏览器将要输出Excel03版本文件
        //        header(‘Content-Disposition: attachment;filename="01simple.xlsx"‘);//告诉浏览器输出浏览器名称
        //        header(‘Cache-Control: max-age=0‘);//禁止缓存
        //        $writer = new Xlsx($spreadsheet);
        //        $writer->save(‘php://output‘);
        return $response
            ->withHeader('Cache-Control', 'max-age=0')
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=UTF8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $fileName . '.xlsx"');
    }

    /**
     * 获取模板下载地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function templateDownloadUrl(
        /** @noinspection PhpUnusedParameterInspection */ Request $request,
        Response $response
    ) {
        $url = DictConstants::get(DictConstants::ORG_WEB_CONFIG, 'lottery_import_user_template');
        $ossUrl = AliOSS::replaceCdnDomainForDss($url);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['url' => $ossUrl]
        ]);
    }
}
