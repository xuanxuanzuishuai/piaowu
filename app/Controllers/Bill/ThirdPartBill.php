<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2020/7/10
 * Time: 下午2:55
 */

namespace App\Controllers\Bill;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Models\ThirdPartBillModel;
use App\Services\DictService;
use App\Services\ThirdPartBillService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

class ThirdPartBill extends ControllerBase
{
    public function import(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'parent_channel_id',
                'type'       => 'required',
                'error_code' => 'parent_channel_id_is_required'
            ],
            [
                'key'        => 'channel_id',
                'type'       => 'required',
                'error_code' => 'channel_id_is_required'
            ],
            [
                'key'        => 'package_id',
                'type'       => 'required',
                'error_code' => 'package_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $file = $_FILES['filename'];
        if(empty($file)) {
            return $response->withJson(Valid::addErrors([], 'import', 'filename_is_required'));
        }
        $extension = strtolower(pathinfo($_FILES['filename']['name'])['extension']);
        if(!in_array($extension, ['xls', 'xlsx'])) {
            return $response->withJson(Valid::addErrors([], 'bill', 'must_excel_format'));
        }

        $filename = '/tmp/import_trade_no_' . md5(rand() . time()) . '.' . $extension;
        if(move_uploaded_file($_FILES['filename']['tmp_name'], $filename) == false) {
            return $response->withJson(Valid::addErrors([], 'bill', 'move_file_fail'));
        }

        $employeeId = $this->ci['employee']['id'];

        // 检查是否重复购买，手机号是否重复
        $data = ThirdPartBillService::checkDuplicate($filename, $employeeId);
        if($data instanceof RunTimeException) {
            return HttpHelper::buildOrgWebErrorResponse($response, $data->getWebErrorData(), $data->getData());
        }

        foreach($data as $k => $v) {
            $v['parent_channel_id'] = $params['parent_channel_id'];
            $v['channel_id'] = $params['channel_id'];
            $v['package_id'] = $params['package_id'];
            $v['package_v1'] = ThirdPartBillModel::PACKAGE_V1;
            $data[$k] = $v;
        }

        // 表格内容发送至消息队列
        ThirdPartBillService::sendMessages($data);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['total' => count($data)],
        ]);
    }

    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();

        list($page, $count) = Util::formatPageCount($params);

        list($total, $records) = ThirdPartBillService::list($params, $page, $count);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'total_count' => $total,
                'records' => $records,
            ],
        ]);
    }

    public function downloadTemplate(Request $request, Response $response, $args)
    {
        $domain = DictService::getKeyValue(Constants::DICT_TYPE_SYSTEM_ENV, Constants::DICT_KEY_STATIC_FILE_URL);
        $url = $domain . '/excel/import_third_part_bill_template.xlsx';
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['url' => $url]
        ]);
    }
}