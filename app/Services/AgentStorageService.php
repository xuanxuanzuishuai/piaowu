<?php

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\EventListener\AgentOpEvent;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\RC4;
use App\Libs\RedisDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\AgentApplicationModel;
use App\Models\AgentAwardBillExtModel;
use App\Models\AgentAwardDetailModel;
use App\Models\AgentInfoModel;
use App\Models\AgentPreStorageRefundModel;
use App\Models\AgentPreStorageReviewLogModel;
use App\Models\BillMapModel;
use App\Models\AgentDivideRulesModel;
use App\Models\AgentModel;
use App\Models\AgentOperationLogModel;
use App\Models\AgentSalePackageModel;
use App\Models\AgentUserModel;
use App\Models\AreaCityModel;
use App\Models\AreaProvinceModel;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssDictModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\GoodsResourceModel;
use App\Models\ParamMapModel;
use App\Models\UserWeiXinInfoModel;
use App\Models\PosterModel;
use App\Models\UserWeiXinModel;
use I18N\Lang;
use Medoo\Medoo;

class AgentStorageService
{
    /**
     * 新增退费申请
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function addRefund(array $params, int $employeeId)
    {
        $time = time();
        //refund数据
        $refundInsertData = [
            'agent_id' => $params['agent_id'],
            'amount' => $params['amount'] * 100,
            'remark' => $params['remark'] ?? '',
            'employee_id' => $employeeId,
            'create_time' => $time,
            'type' => AgentPreStorageRefundModel::TYPE_REFUND_AMOUNT,
        ];


        $amount = self::checkAmount($refundInsertData['agent_id'], $refundInsertData['amount']);

        //agent数据
        $agentUpdateData = [
            'where' => [
                'agent_id' => $refundInsertData['agent_id'],
                'amount' => $amount,
            ],
            'data' => [
                'amount' => $amount - $refundInsertData['amount']
            ]
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = AgentPreStorageRefundModel::add($refundInsertData, $agentUpdateData);

        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['insert_failure']);
        } else {
            $db->commit();
        }
        return true;
    }


    /**
     * 校验可退款金额
     *
     * @param int $agentId
     * @param int $refundAmount
     * @return int
     * @throws RunTimeException
     */
    private static function checkAmount(int $agentId, int $refundAmount): int
    {
        $amount = AgentInfoModel::getRecord(['agent_id' => $agentId], ['amount']);

        if ($amount['amount'] < $refundAmount) {
            throw new RunTimeException(['agent_amount_not_enough']);
        }

        return $amount['amount'];
    }

    /**
     * 编辑退款申请
     *
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function updateRefund(array $params, int $employeeId)
    {
        $time = time();

        $refundUpdateData = [
            'where' => [
                'id' => $params['refund_id'],
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_REBUT,
            ],
            'data' => [
                'amount' => $params['amount'] * 100,
                'remark' => $params['remark'] ?? '',
            ],
        ];
        $refund = AgentPreStorageRefundModel::getById($refundUpdateData['where']['id']);

        if (empty($refund)) {
            throw new RunTimeException(['agent_refund_not_exist']);
        }

        $amount = self::checkAmount($refund['agent_id'], $refundUpdateData['data']['amount']);

        //agent数据
        $agentAmountData = [
            'where' => [
                'id' => $refund['agent_id'],
                'amount' => $amount,
            ],
            'data' => [
                'amount' => $amount - $refundUpdateData['data']['amount']
            ]
        ];

        //log数据
        $log = [
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_REFUND,
            'data_id' => $refund['id'],
            'type' => AgentPreStorageReviewLogModel::TYPE_RESET_PUSH,
        ];

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentPreStorageRefundModel::update($refundUpdateData, $agentAmountData, $log);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        return true;
    }


    /**
     * 代理请款审核
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function verifyRefund(array $params, int $employeeId): bool
    {

        $time = time();

        $refundUpdateData = [
            'where' => [
                'id' => $params['refund_id'],
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_WAIT,
            ]
        ];

        $refund = AgentPreStorageRefundModel::getById($refundUpdateData['where']['id']);
        if (empty($refund)) {
            throw new RunTimeException(['agent_refund_not_exist']);
        }

        $log = [
            'reviewer_uid' => $employeeId,
            'create_time' => $time,
            'data_type' => AgentPreStorageReviewLogModel::DATA_TYPE_REFUND,
            'data_id' => $refund['id'],
        ];

        $agentAmountData = [];

        if ($params['operation'] == AgentPreStorageRefundModel::STATUS_VERIFY_PASS) {
            $refundUpdateData['data'] = [
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_PASS
            ];

            $log['type'] = AgentPreStorageRefundModel::STATUS_VERIFY_PASS;

        } elseif ($params['operation'] == AgentPreStorageRefundModel::STATUS_VERIFY_REBUT) {
            $refundUpdateData['data'] = [
                'status' => AgentPreStorageRefundModel::STATUS_VERIFY_REBUT,
                'remark' => $params['remark'],
            ];
            $log['type'] = AgentPreStorageRefundModel::STATUS_VERIFY_REBUT;

            $agentInfo = AgentInfoModel::getRecord(['agent_id' => $refund['agent_id']]);
            //agent数据
            $agentAmountData = [
                'where' => [
                    'id' => $refund['agent_id'],
                    'amount' => $agentInfo['amount'],
                ],
                'data' => [
                    'amount' => $agentInfo['amount'] + $refund['amount']
                ]
            ];

        } else {
            return false;
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = AgentPreStorageRefundModel::update($refundUpdateData, $agentAmountData, $log);
        if (empty($res)) {
            $db->rollBack();
            throw new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        return true;
    }

    /**
     * 退款列表
     *
     * @param array $params
     * @param int $employeeId
     * @return array
     */
    public static function listRefund(array $params, int $employeeId): array
    {
        $where = [AgentModel::$table . '.parent_id' => 0];
        $where[AgentPreStorageRefundModel::$table . '.type'] = AgentPreStorageRefundModel::TYPE_REFUND_AMOUNT;

        if (!empty($params['agent_id'])) {
            $where[AgentPreStorageRefundModel::$table . '.agent_id'] = $params['agent_id'];
        }
        if (!empty($params['id'])) {
            $where[AgentPreStorageRefundModel::$table . '.id'] = $params['id'];
        }
        if (!empty($params['status'])) {
            $where[AgentPreStorageRefundModel::$table . '.status'] = $params['status'];
        }
        if (!empty($params['create_start_time'])) {
            $where[AgentPreStorageRefundModel::$table . '.create_time[>=]'] = $params['create_start_time'];
        }
        if (!empty($params['create_end_time'])) {
            $where[AgentPreStorageRefundModel::$table . '.create_time[<=]'] = $params['create_end_time'];
        }
//        if (!empty($params['employee_name'])) {
//            $employeeId = EmployeeModel::getRecord(['name' => $params['employee_name']], ['id']);
//            if (empty($employeeId)) {
//                return $data;
//            }
//            $where[AgentModel::$table . '.employee_id'] = $employeeId['id'];
//        }
        if (!empty($params['name'])) {
            $where[AgentModel::$table . '.name[~]'] = $params['name'];
        }
        //数据权限
//        if ($params['only_read_self']) {
//            $where["OR"] = [
//                AgentModel::$table . '.service_employee_id' => $currentEmployeeId,
//                AgentModel::$table . '.employee_id' => $currentEmployeeId,
//            ];
//        }
        return AgentPreStorageRefundModel::list($where, $params['page'], $params['count']);
    }


    /**
     * 退款详情
     * @param int $refundId
     * @return array
     */
    public static function detailRefund(int $refundId): array
    {
        $detail = AgentPreStorageRefundModel::detail($refundId);
        if (!empty($detail)) {
            $detail['operation_log'] = AgentPreStorageReviewLogModel::getLogList($refundId);
        }
        return $detail;
    }

}