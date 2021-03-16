<?php


namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\StudentAccountAwardPointsFileModel;
use App\Models\StudentAccountAwardPointsLogModel;
use App\Services\StudentAccountAwardPointsLogService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class StudentAccount extends ControllerBase
{

    /**
     * 批量导入学生积分
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function batchImportRewardPoints(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'account_name',    // app_id + sub_type
                'type' => 'required',
                'error_code' => 'account_name_is_required'
            ],
            [
                'key' => 'remark',    // app_id + sub_type
                'type' => 'required',
                'error_code' => 'remark_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'lengthMax',
                'value' => 50,
                'error_code' => 'student_account_import_remark_len'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $employeeId = $this->ci['employee']['id'];

            //检查是否已经存在导入
            $where = ['operator_id' => $employeeId, 'status' => [StudentAccountAwardPointsFileModel::STATUS_CREATE,StudentAccountAwardPointsFileModel::STATUS_EXEC]];
            $importInfo = StudentAccountAwardPointsFileModel::getRecords($where);
            if (!empty($importInfo)) {
                throw new RunTimeException(['batch_reward_points_running']);
            }

            //排除掉现金方式，其他的可以，并且判断参数account_name是否在这个数组里，不在不能导入，给出错误提示
            $accountNameList = StudentAccountAwardPointsLogService::getAccountName();
            if (!isset($params['account_name'], $accountNameList)) {
                throw new RunTimeException(['account_name_is_error']);
            }

            //上传文件
            $file = $_FILES['filename'];
            if (empty($file)) {
                throw new RunTimeException(['share_poster_add_fail']);
            }
            $extension = strtolower(pathinfo($_FILES['filename']['name'])['extension']);
            if (!in_array($extension, ['xls', 'xlsx'])) {
                throw new RunTimeException(['upload_file_not_excel']);
            }
            //临时文件完整存储路径
            $filename = '/tmp/import_student_account_award_points_' . md5(rand() . time()) . '.' . $extension;
            if (move_uploaded_file($_FILES['filename']['tmp_name'], $filename) == false) {
                throw new RunTimeException(['ali_oss_upload_fail']);
            }

            // 处理数据 -上传excel文件
            $saveRes = StudentService::saveUploadExcel($filename,[
                'employee_id' => $employeeId,
                'app_id' => explode(StudentAccountAwardPointsLogModel::APPID_SUBTYPE_EXPLODE, $params['account_name'])[0],
                'sub_type' => explode(StudentAccountAwardPointsLogModel::APPID_SUBTYPE_EXPLODE, $params['account_name'])[1],
                'remark' => $params['remark']

            ]);
            if (!$saveRes) {
                throw new RunTimeException(['ali_oss_upload_fail']);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        } finally {
            // 删除临时文件
            if (file_exists($filename)) {
                unlink($filename);
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    /**
     * 获取当前用户是否有正在导入的文件，以及返回积分账户列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function importRewardPointsInfo(Request $request, Response $response)
    {
        try {
            $employeeId = $this->ci['employee']['id'];

            // 获取当前账户是有有正在执行导入和等待执行导入的excel
            $where = ['operator_id' => $employeeId, 'status' => [StudentAccountAwardPointsFileModel::STATUS_CREATE,StudentAccountAwardPointsFileModel::STATUS_EXEC]];
            $importInfo = StudentAccountAwardPointsFileModel::getRecords($where);
            $accountNameList = StudentAccountAwardPointsLogService::getAccountName();
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => [
                    'import_status' => empty($importInfo) ? 0 : 1,
                    'account_name_list' => $accountNameList,
                ],
            ]);
        } catch (RuntimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
    }

    /**
     * 获取导入学生积分奖励列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function importRewardPointsList(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'page',    // app_id + sub_type
                    'type' => 'integer',
                    'error_code' => 'page_is_integer'
                ],
                [
                    'key' => 'count',
                    'type' => 'integer',
                    'error_code' => 'count_is_integer'
                ],
            ];
            $params = $request->getParams();
            $result = Valid::validate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            $awardPointsLogArr = StudentAccountAwardPointsLogService::getList($params, $params['page'], $params['count']);

            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => [
                    'total' => $awardPointsLogArr['total'],
                    'award_points_log_list' => $awardPointsLogArr['list'],
                    'page' => $params['page'],
                    'count' => $params['count'],
                ],
            ]);
        } catch (RuntimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
    }

    /**
     * 获取批量发放积分导入模板地址
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function batchImportRewardPointsTemplate(Request $request, Response $response)
    {
        $url = DictConstants::get(DictConstants::ORG_WEB_CONFIG,'batch_import_reward_points_template');
        $ossUrl = AliOSS::replaceCdnDomainForDss($url);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['url' => $ossUrl]
        ]);
    }
}