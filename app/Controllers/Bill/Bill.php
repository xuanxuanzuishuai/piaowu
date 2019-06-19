<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/28
 * Time: 下午6:47
 */

namespace App\Controllers\Bill;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Models\ApprovalModel;
use App\Models\BillExtendModel;
use App\Models\BillModel;
use App\Models\StudentAccountModel;
use App\Services\ApprovalService;
use App\Services\BillService;
use App\Services\StudentAccountService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;
/**
 * 订单
 * Class Bill
 * @package App\Controllers\Bill\
 */
class Bill extends ControllerBase
{
    public function disable(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $id = $params['id'];
        global $orgId;
        $record = BillModel::getBillByOrgAndId($orgId, $id);
        if(empty($record)) {
            return $response->withJson(Valid::addErrors([], 'bill', 'bill_not_exist'));
        }

        //添加订单审核通过的才能废除
        if($record['add_status'] != BillModel::ADD_STATUS_APPROVED || !empty($record['disabled_status'])) {
            return $response->withJson(Valid::addErrors([], 'bill', 'only_approved_bill_can_be_disabled'));
        }

        //已经废除的订单，直接返回
        if($record['is_disabled'] == BillModel::IS_DISABLED) {
            return $response->withJson([
                'code' => Valid::CODE_SUCCESS,
                'data' => []
            ]);
        }

        if(ApprovalService::needApprove(ApprovalModel::TYPE_BILL_DISABLE)) {
            $status = BillModel::DISABLED_STATUS_APPROVING;
        } else {
            $status = BillModel::DISABLED_STATUS_APPROVED;
        }

        if($status == BillModel::DISABLED_STATUS_APPROVED) {
            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $affectRows = BillModel::updateRecord($id, [
                'is_disabled'     => BillModel::IS_DISABLED,
                'disabled_status' => $status,
                'update_time'     => time()
            ],false);

            if($affectRows == 0) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([],'bill', 'update_disabled_fail'));
            }

            if($record['pay_status'] == BillModel::PAY_STATUS_PAID &&
                $record['is_enter_account'] == BillModel::IS_ENTER_ACCOUNT)
            {
                $success = StudentAccountService::abolishSA(
                    $record['student_id'], $record['amount'], 0, $record['operator_id'], $record['remark'], false
                );
                if(!$success) {
                    $db->rollBack();
                    return $response->withJson(Valid::addErrors([], 'bill', 'abolish_sa_fail'));
                }
            }

            $db->commit();
        } else {
            list($errorCode) = ApprovalService::submit($id, ApprovalModel::TYPE_BILL_DISABLE, $record['operator_id']);
            if(!is_null($errorCode)) {
                return $response->withJson(Valid::addErrors([], 'bill', $errorCode));
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [],
        ]);
    }

    public function list(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_must_be_integer',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'integer',
                'error_code' => 'pay_channel_must_be_integer',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'integer',
                'error_code' => 'pay_channel_must_be_integer',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer',
            ],
            [
                'key'        => 'source',
                'type'       => 'integer',
                'error_code' => 'source_must_be_integer',
            ],
            [
                'key'        => 'start_create_time',
                'type'       => 'integer',
                'error_code' => 'start_create_time_must_be_integer',
            ],
            [
                'key'        => 'end_create_time',
                'type'       => 'integer',
                'error_code' => 'end_create_time_must_be_integer',
            ],
            [
                'key'        => 'org_id',
                'type'       => 'integer',
                'error_code' => 'org_id_must_be_integer',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($records, $total) = BillService::selectByPage(
            $params['org_id'], $params['page'], $params['count'], $params
        );

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }

    public function listForOrg(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_must_be_integer',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'integer',
                'error_code' => 'pay_channel_must_be_integer',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'integer',
                'error_code' => 'page_must_be_integer',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer',
            ],
            [
                'key'        => 'source',
                'type'       => 'integer',
                'error_code' => 'source_must_be_integer',
            ],
            [
                'key'        => 'start_create_time',
                'type'       => 'integer',
                'error_code' => 'start_create_time_must_be_integer',
            ],
            [
                'key'        => 'end_create_time',
                'type'       => 'integer',
                'error_code' => 'end_create_time_must_be_integer',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;

        list($records, $total) = BillService::selectByPage(
            $orgId, $params['page'], $params['count'], $params
        );

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ],
        ]);
    }

    public function add(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'pay_status',
                'type'       => 'required',
                'error_code' => 'pay_status_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'required',
                'error_code' => 'amount_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'min',
                'value'      => 0,
                'error_code' => 'amount_is_egt_0',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
            [
                'key'        => 'source',
                'type'       => 'required',
                'error_code' => 'source_is_required',
            ],
            [
                'key'        => 'source',
                'type'       => 'in',
                'value'      => [1, 2],
                'error_code' => 'source_must_in_1_2',
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 1024,
                'error_code' => 'remark_must_elt_1024',
            ],
            [
                'key'        => 'trade_no',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'trade_no_must_elt_50',
            ],
            [
                'key'        => 'sprice',
                'type'       => 'required',
                'error_code' => 'sprice_is_required',
            ],
            [
                'key'        => 'sprice',
                'type'       => 'min',
                'value'      => 0,
                'error_code' => 'sprice_is_egt_0',
            ],
            [
                'key'        => 'is_enter_account',
                'type'       => 'required',
                'error_code' => 'is_enter_account_is_required',
            ],
            [
                'key'        => 'is_enter_account',
                'type'       => 'in',
                'value'      => [0, 1],
                'error_code' => 'is_enter_account_must_be_in_0_1',
            ],
            [
                'key'        => 'credentials_url',
                'type'       => 'lengthMax',
                'value'      => 256,
                'error_code' => 'credentials_url_length_elt_256',
            ],
            [
                'key'        => 'object_id',
                'type'       => 'required',
                'error_code' => 'object_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;
        if(!empty($params['r_bill_id'])) {
            $rBill = BillService::getDetail($params['r_bill_id'],$orgId);
            if(empty($rBill)) {
                return $response->withJson(Valid::addErrors([], 'bill', 'relate_bill_not_exist'));
            }
            if(!empty($rBill['r_bill_id']) ) {
                return $response->withJson(Valid::addErrors([], 'bill', 'relate_bill_not_create_relate_bill'));
            }
            if($rBill['student_id'] != $params['student_id'] ) {
                return $response->withJson(Valid::addErrors([], 'bill', 'relate_bill_student_id_is_invalid'));
            }
        }
        else {
            $params['r_bill_id'] = 0;
        }
        $studentId = $params['student_id'];

        $student = StudentService::getOrgStudent($orgId, $studentId);
        if(empty($student)) {
            return $response->withJson(Valid::addErrors([], 'bill', 'student_not_exist'));
        }

        if(ApprovalService::needApprove(ApprovalModel::TYPE_BILL_ADD)) {
            $status = BillModel::ADD_STATUS_APPROVING;
        } else {
            $status = BillModel::ADD_STATUS_APPROVED;
        }

        $now = time();

        $columns = [
            'student_id', 'pay_status', 'trade_no',
            'pay_channel', 'source','remark', 'is_enter_account', 'object_id'
        ];

        $data = [
            'create_time' => $now,
            'end_time'    => $now,
            'operator_id' => $this->getEmployeeId(),
            'org_id'      => $orgId,
            'amount'      => $params['amount'] * 100,
            'sprice'      => $params['sprice'] * 100,
            'r_bill_id'   => $params['r_bill_id'],
            'add_status'  => $status,
        ];

        foreach($columns as $key) {
            $data[$key] = $params[$key];
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $lastId = BillModel::insertRecord($data, false);
        if(is_null($lastId)) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'bill', 'save_bill_fail'));
        }

        if($params['pay_status'] == BillModel::PAY_STATUS_PAID &&
            $params['is_enter_account'] == BillModel::IS_ENTER_ACCOUNT &&
            $status == BillModel::ADD_STATUS_APPROVED)
        {
            $success = StudentAccountService::addSA(
                $data['student_id'],
                [StudentAccountModel::TYPE_CASH => $data['amount']],
                $this->getEmployeeId(),
                $data['remark']
            );
            if(!$success) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'bill', 'update_student_account_fail'));
            }
        }

        //付款凭证图片链接
        if(!empty($params['credentials_url'])) {
            $extend = [
                'bill_id'         => $lastId,
                'credentials_url' => $params['credentials_url'],
                'status'          => BillExtendModel::STATUS_NORMAL,
                'create_time'     => $now,
            ];
            $affectRows = BillExtendModel::insertRecord($extend, false);
            if(empty($affectRows)) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'bill', 'save_bill_extend_fail'));
            }
        }

        if($params['pay_status'] == BillModel::PAY_STATUS_PAID &&
            $status == BillModel::ADD_STATUS_APPROVED) {
            //更新用户首付付费
            $rows = StudentService::updateUserPaidStatus($studentId);
            if(!is_null($rows) && empty($rows)) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'bill', 'update_first_pay_fail'));
            }
        }

        //提交审核
        if($status == BillModel::ADD_STATUS_APPROVING) {
            list($errorCode) = ApprovalService::submit($lastId, ApprovalModel::TYPE_BILL_ADD, $this->getEmployeeId());
            if(!is_null($errorCode)) {
                $db->rollBack();
                return $response->withJson(Valid::addErrors([], 'bill', $errorCode));
            }
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['last_id' => $lastId],
        ]);
    }

    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'bill_id',
                'type'       => 'required',
                'error_code' => 'bill_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $billId = $params['bill_id'];

        $record = BillService::getDetail($billId, $this->getEmployeeOrgId());

        $approvals = ApprovalService::getInfo($billId, $this->getEmployeeId());

        $rBills = ['total_count'=> 0,'r_bills'=> []];
        list($rBills['r_bills'],$rBills['total_count']) = BillService::selectByPage($record['org_id'], $params['page'], $params['count'], ['r_bill_id'=>$record['id']]);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'detail' => $record,
                'approvals' => $approvals,
                'r_bills' => $rBills
            ]
        ]);
    }
}