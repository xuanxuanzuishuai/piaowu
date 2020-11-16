<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/29
 * Time: 4:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Valid;
use App\Libs\HttpHelper;
use App\Services\StudentCertificateService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 学生证书控制器
 * Class Collection
 * @package App\Controllers\OrgWeb
 */
class StudentCertificate extends ControllerBase
{
    /**
     * 生成毕业证书
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createCertificate(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ],
            [
                'key' => 'student_name',
                'type' => 'required',
                'error_code' => 'student_name_is_required'
            ],
            [
                'key' => 'student_name',
                'type' => 'lengthMax',
                'value' => 10,
                'error_code' => 'student_name_length_more_then_10'
            ],
            [
                'key' => 'certificate_date',
                'type' => 'required',
                'error_code' => 'certificate_date_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $certificateData = StudentCertificateService::add($params['student_id'], $params['student_name'], $params['certificate_date'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $certificateData);
    }

    /**
     * 证书模板列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function certificateTemplate(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $certificateData = StudentCertificateService::certificateTemplate($params['type']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $certificateData);
    }
}