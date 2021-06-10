<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\AgentOrganizationModel;
use App\Models\AgentOrganizationOpnModel;
use App\Models\AgentOrganizationStudentModel;
use App\Models\Erp\OpnCollectionModel;

class AgentOrgService
{
    /**
     * 代理商机构专属曲谱教材关联
     * @param array $params
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function orgOpnRelation($params, $employeeId)
    {
        $opnCheckData = self::opnRelationCheck($params, $employeeId);
        if (empty($opnCheckData['res'])) {
            return true;
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if (!empty($opnCheckData['add'])) {
            $relationId = AgentOrganizationOpnModel::batchInsert($opnCheckData['add']);
            if (empty($relationId)) {
                $db->rollBack();
                throw new RunTimeException(['insert_failure']);
            }
        }
        if (!empty($opnCheckData['del'])) {
            foreach ($opnCheckData['del'] as $du) {
                $affectRow = AgentOrganizationOpnModel::updateRecord($du['id'], $du['update_data']);
                if (empty($affectRow)) {
                    $db->rollBack();
                    throw new RunTimeException(['update_failure']);
                }
            }
        }
        $db->commit();
        return true;
    }

    /**
     * 机构专属曲谱教材关联条件检测
     * @param $params
     * @param $employeeId
     * @return array
     */
    private static function opnRelationCheck($params, $employeeId)
    {
        $time = time();
        $checkRes = false;
        //检测曲谱教材是否重复
        $haveRelationOpnList = array_column(AgentOrganizationOpnModel::getRecords(
            [
                'org_id' => $params['agent_org_id'],
                'status' => AgentOrganizationOpnModel::STATUS_OK
            ],
            [
                'opn_id', 'id'
            ]), null, 'opn_id');
        $haveRelationOpnIds = array_column($haveRelationOpnList, 'opn_id');
        $opnDelDiff = array_diff($haveRelationOpnIds, $params['opn_id']);
        $opnAddDiff = array_diff($params['opn_id'], $haveRelationOpnIds);
        $delData = $addData = [];
        //取消关联关系的曲谱教材ID
        if (!empty($opnDelDiff)) {
            $checkRes = true;
            foreach ($opnDelDiff as $dv) {
                $delData[] = [
                    'id' => $haveRelationOpnList[$dv]['id'],
                    'update_data' => [
                        'status' => AgentOrganizationOpnModel::STATUS_REMOVE,
                        'update_time' => $time,
                        'operator_id' => $employeeId,
                    ],
                ];
            }
        }
        //新增关联关系的曲谱教材ID
        if (!empty($opnAddDiff)) {
            $checkRes = true;
            foreach ($opnAddDiff as $av) {
                $addData[] = [
                    'org_id' => $params['agent_org_id'],
                    'opn_id' => $av,
                    'create_time' => $time,
                    'operator_id' => $employeeId,
                ];
            }
        }
        return ['add' => $addData, 'del' => $delData, 'res' => $checkRes];
    }

    /**
     * 获取代理机构专属曲谱列表
     * @param $params
     * @return array
     */
    public static function orgOpnList($params)
    {
        return AgentOrganizationOpnModel::getOrgOpnList($params['agent_id'], $params['page'], $params['count']);
    }

    /**
     * 代理商机构统计数据
     * @param $agentId
     * @return array
     */
    public static function orgStaticsData($agentId)
    {
        return AgentOrganizationModel::getRecord(['agent_id' => $agentId], ['agent_id', 'id', 'name', 'quantity', 'amount']);
    }

    /**
     * 获取小程序机构展示封面数据
     * @param $agentId
     * @return mixed
     */
    public static function orgMiniCoverData($agentId)
    {
        //名称
        $orgData = AgentOrganizationModel::getRecord(['agent_id' => $agentId, 'status' => AgentOrganizationModel::STATUS_OK], ['name', 'id']);
        $coverData['name'] = $orgData['name'];
        //曲谱教材
        $coverData['opn_count'] = AgentOrganizationOpnModel::getCount(['org_id' => $orgData['id'], 'status' => AgentOrganizationOpnModel::STATUS_OK]);
        //在读学员
        $coverData['student_count'] = AgentOrganizationStudentModel::getCount(['org_id' => $orgData['id'], 'status' => AgentOrganizationOpnModel::STATUS_OK]);
        return $coverData;
    }


    /**
     * 获取代理机构专属曲谱列表
     * @param $agentId
     * @return array
     */
    public static function orgMiniOpnList($agentId)
    {
        $list = [];
        $opnIds = AgentOrganizationOpnModel::getOrgOpnId($agentId);
        if (empty($opnIds)) {
            return $list;
        }
        //教材数据
        $opnData = OpnCollectionModel::getRecords(['id' => $opnIds, 'type' => OpnCollectionModel::TYPE_SIGN_UP], ['id', 'name', 'cover_portrait', 'cover']);
        if (empty($opnData)) {
            return $list;
        }
        $opnData = array_column($opnData, null, 'id');
        $dictConfig = array_column(DictConstants::getErpDictArr(DictConstants::ERP_SYSTEM_ENV['type'], ['QINIU_DOMAIN_1', 'QINIU_FOLDER_1'])[DictConstants::ERP_SYSTEM_ENV['type']],'value','code');
        foreach ($opnIds as $pid) {
            $list[] = [
                'cover_portrait' => empty($opnData[$pid]['cover_portrait']) ? '' : Util::getQiNiuFullImgUrl($opnData[$pid]['cover_portrait'], $dictConfig['QINIU_DOMAIN_1'], $dictConfig['QINIU_FOLDER_1']),
                'name' => $opnData[$pid]['name'],
            ];
        }
        return $list;
    }
}