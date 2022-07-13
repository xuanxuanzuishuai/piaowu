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
use App\Libs\Util;
use App\Services\ThirdPartBillService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

class Bill extends ControllerBase
{
    /**
     * 第三方订单导入
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function thirdBillImport(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'parent_channel_id',
                'type' => 'required',
                'error_code' => 'parent_channel_id_is_required'
            ],
            [
                'key' => 'channel_id',
                'type' => 'required',
                'error_code' => 'channel_id_is_required'
            ],
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required'
            ],
            [
                'key' => 'business_id',
                'type' => 'required',
                'error_code' => 'business_id_is_required'
            ],
            [
                'key' => 'target_business_id',
                'type' => 'required',
                'error_code' => 'target_business_id_is_required'
            ],
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
            return $response->withJson(Valid::addErrors([], 'bill', 'must_excel_format'));
        }
        //临时文件完整存储路径
        $filename = '/tmp/import_trade_no_' . md5(rand() . time()) . '.' . $extension;
        if (move_uploaded_file($_FILES['filename']['tmp_name'], $filename) == false) {
            return $response->withJson(Valid::addErrors([], 'bill', 'move_file_fail'));
        }
        $data = [];
        try {
            $employeeId = $this->ci['employee']['id'];
            // 检查订单数据
            $data = ThirdPartBillService::checkDuplicate($filename, $employeeId, $params);
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
     * 第三方导入订单列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function thirdBillList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        $records = ThirdPartBillService::thirdBillList($params, $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records,
        ]);
    }

    /**
     * 第三方订单导入表格模板文件
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function thirdBillDownloadTemplate(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        $url = DictConstants::get(DictConstants::ORG_WEB_CONFIG,'third_part_import_bill_template');
        $ossUrl = AliOSS::replaceCdnDomainForDss($url);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['url' => $ossUrl]
        ]);
    }
}