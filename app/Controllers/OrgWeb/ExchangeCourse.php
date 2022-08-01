<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 10:55
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\SmsCenter\SmsCenter;
use App\Libs\Util;
use App\Services\CommonServiceForApp;
use App\Services\ExchangeCourseService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;

class ExchangeCourse extends ControllerBase
{
    /**
     * 兑课导入接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function import(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'target_app_id',
                'type'       => 'required',
                'error_code' => 'target_app_id_is_required'
            ],
            [
                'key'        => 'import_source',
                'type'       => 'required',
                'error_code' => 'import_source_is_required'
            ],
            [
                'key'        => 'channel_id',
                'type'       => 'required',
                'error_code' => 'channel_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $file = $_FILES['filename'];
        if (empty($file)) {
            return $response->withJson(Valid::addErrors([], 'import', 'filename_is_required'));
        }
        $extension = strtolower(pathinfo($_FILES['filename']['name'])['extension']);
        if (!in_array($extension, ['xls', 'xlsx'])) {
            return $response->withJson(Valid::addErrors([], 'import', 'must_excel_format'));
        }
        //临时文件完整存储路径
        $filename = '/tmp/import_exchange_' . md5(rand() . time()) . '.' . $extension;
        if (move_uploaded_file($_FILES['filename']['tmp_name'], $filename) == false) {
            return $response->withJson(Valid::addErrors([], 'import', 'move_file_fail'));
        }

        $employeeUuid = $this->ci['employee']['uuid'];

        //将文件上传到oss
        if ($_ENV['ENV_NAME'] == 'prod') {
            $fileName = 'import_exchange_' . time() . '.' . $extension;
            $ossPath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_TMP_EXCEL . '/' . $fileName;
            AliOSS::uploadFile($ossPath, $filename);
            $ossPath = AliOSS::replaceCdnDomainForDss($ossPath);
            SimpleLogger::info('import exchange excel upload oss success', ['uuid' => $employeeUuid, 'oss_path' => $ossPath, 'time' => time()]);
        }

        $data = [];
        try {
            // 检查订单数据
            $data = ExchangeCourseService::analysisData($filename, $employeeUuid, $params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        } finally {
            //删除临时文件
            unlink($filename);
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['total' => count($data)],
        ]);
    }

    /**
     * 兑课导入列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $records = ExchangeCourseService::importList($params, $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records,
        ]);
    }

    /**
     * 删除指定的导入记录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function delete(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id_list',
                'type'       => 'required',
                'error_code' => 'id_list_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $uuid = $this->ci['employee']['uuid'];
        ExchangeCourseService::deleteList($params['id_list'],$uuid);
        return HttpHelper::buildResponse($response,[]);
    }

    /**
     * 发送短信
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activateSms(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'id_list',
                'type'       => 'required',
                'error_code' => 'id_list_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        ExchangeCourseService::activateSms($params['id_list']);
        return HttpHelper::buildResponse($response,[]);
    }

    /**
     * 兑课导入表格模板文件
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function downTemplate(Request $request, Response $response)
    {
        $url = DictConstants::get(DictConstants::ORG_WEB_CONFIG, 'import_exchange_template');
        $ossUrl = AliOSS::replaceCdnDomainForDss($url);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['url' => $ossUrl]
        ]);
    }

    /**
     * 兑课确认接口
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function exchangeConfirm(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'country_code',
                'type'       => 'required',
                'error_code' => 'country_code_required'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key'        => 'code',
                'type'       => 'required',
                'error_code' => 'code_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //校验手机号码格式
        if ($params['country_code'] == SmsCenter::DEFAULT_COUNTRY_CODE) {
            $res = Util::isChineseMobile($params['mobile']);
        } else {
            $res = Util::validPhoneNumber($params['mobile'], $params['country_code']);
        }
        if (!$res) {
            return $response->withJson(Valid::addAppErrors([], 'student_mobile_format_is_error'), StatusCode::HTTP_OK);
        }
        // 验证手机验证码
        if (!empty($params['code']) && !CommonServiceForApp::checkValidateCode($params['mobile'], $params['code'],
                $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE)) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }
        try {
			$uuid=ExchangeCourseService::exchangeConfirm($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, ['uuid'=>$uuid]);
    }
}