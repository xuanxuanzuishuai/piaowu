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
use App\Libs\PhpMail;
use App\Libs\Util;
use App\Models\AgentOrganizationModel;
use App\Models\AgentOrganizationOpnModel;
use App\Models\AgentOrganizationStudentModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\OpnCollectionModel;
use App\Services\Queue\QueueService;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
        $opnAddData = self::opnRelationCheck($params, $employeeId);
        if (!empty($opnAddData)) {
            $relationId = AgentOrganizationOpnModel::batchInsert($opnAddData);
            if (empty($relationId)) {
                throw new RunTimeException(['insert_failure']);
            }
            self::pushStudentFavoriteCollection($params['agent_org_id'], array_column($opnAddData, 'opn_id'));
        }
        return true;
    }

    /**
     * 机构专属教材自动收藏推送消息队列
     * @param $agentOrgId
     * @param array $opnCollectionIds
     * @return bool
     */
    private static function pushStudentFavoriteCollection($agentOrgId, array $opnCollectionIds)
    {
        if (empty($opnCollectionIds) || empty($agentOrgId)) {
            return false;
        }
        //获取当前机构有效状态的在读学员
        $agentOrgStudentIds = AgentOrganizationStudentModel::getRecords(['org_id' => $agentOrgId, 'status' => AgentOrganizationStudentModel::STATUS_NORMAL], 'student_id');
        if (empty($agentOrgStudentIds)) {
            return false;
        }
        $studentsInfo = DssStudentModel::getRecords(['id' => $agentOrgStudentIds], ['id(student_id)', 'real_name']);
        return QueueService::updateStudentNameAndCollect([
            'student' => $studentsInfo,
            'opn' => $opnCollectionIds,
        ]);
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
        //检测曲谱教材是否重复
        $haveRelationOpnList = array_column(AgentOrganizationOpnModel::getRecords(
            [
                'org_id' => $params['agent_org_id'],
                'status' => AgentOrganizationOpnModel::STATUS_OK
            ],
            [
                'opn_id', 'id'
            ]), null, 'opn_id');
        //去重
        $opnAddDiff = array_diff(array_unique($params['opn_id']), array_column($haveRelationOpnList, 'opn_id'));
        $addData = [];
        //新增关联关系的曲谱教材ID
        if (!empty($opnAddDiff)) {
            foreach ($opnAddDiff as $av) {
                $addData[] = [
                    'org_id' => $params['agent_org_id'],
                    'opn_id' => $av,
                    'create_time' => $time,
                    'operator_id' => $employeeId,
                ];
            }
        }
        return $addData;
    }

    /**
     * 代理商机构专属曲谱教材取消关联
     * @param int $relationId
     * @param int $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function orgOpnDelRelation($relationId, $employeeId)
    {
        $affectRow = AgentOrganizationOpnModel::updateRecord($relationId,
            [
                'status' => AgentOrganizationOpnModel::STATUS_REMOVE,
                'operator_id' => $employeeId,
                'update_time' => time(),
            ]);
        if (empty($affectRow)) {
            throw new RunTimeException(['update_failure']);
        }
        return true;
    }


    /**
     * 获取代理机构专属曲谱列表
     * @param $params
     * @return array
     */
    public static function orgOpnList($params)
    {
        $list = AgentOrganizationOpnModel::getOrgOpnList($params['agent_id'], $params['page'], $params['count']);
        if (empty($list['list'])) {
            return $list;
        }
        $opnCollectionData = array_column(OpnCollectionModel::getCollectionDataById(array_column($list['list'], 'opn_id')), null, 'id');
        foreach ($list['list'] as &$lv) {
            $lv['name'] = (string)$opnCollectionData[$lv['opn_id']]['name'];
            $lv['author'] = (string)$opnCollectionData[$lv['opn_id']]['author'];
            $lv['press'] = (string)$opnCollectionData[$lv['opn_id']]['press'];
            $lv['artist_name'] = (string)$opnCollectionData[$lv['opn_id']]['artist_name'];
        }
        return $list;
    }

    /**
     * 代理商机构统计数据
     * @param $agentId
     * @return array
     */
    public static function orgStaticsData($agentId)
    {
        $detail = AgentService::agentStaticsData((int)$agentId);

        $data = AgentOrganizationModel::getRecord(['agent_id' => $agentId], ['agent_id', 'id(org_id)', 'name', 'quantity', 'amount']);
        if (empty($data)) {
            return [];
        }
        $data['amount'] = Util::yuan($data['amount'], 0);
        return array_merge($detail, $data);
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


    /**
     * 获取机构学生信息
     *
     * @param array $params
     * @return array
     * @throws RunTimeException
     */
    public static function studentList(array $params): array
    {
        $orgData = self::existAgentOrg($params['agent_id']);

        $data = [
            'total_count' => 0,
            'list' => []
        ];

        $count = AgentOrganizationStudentModel::getCount([
            'org_id' => $orgData['org_id'],
            'status'   => AgentOrganizationStudentModel::STATUS_NORMAL,
        ]);

        if (empty($count)) return $data;

        $data['total_count'] = $count;

        $info = AgentOrganizationStudentModel::getRecords(
            [
                'org_id'   => $orgData['org_id'],
                'status'   => AgentOrganizationStudentModel::STATUS_NORMAL,
                'ORDER'    => ['update_time' => 'DESC'],
                'LIMIT'    => [($params['page'] - 1) * $params['count'], $params['count']]
            ],
            [
                'student_id'
            ]
        );

        if (!empty($info)) {
            $studentIds  = array_column($info, 'student_id');
            $studentInfo = DssStudentModel::getRecords(
                [
                    'id' => $studentIds
                ],
                [
                    'id', 'uuid', 'mobile', 'name', 'real_name', 'create_time', 'sub_end_date', 'has_review_course', 'thumb'
                ]
            );

            $data['list'] = self::formatStudentSearchResult($info,$studentInfo);
        }

        return $data;
    }


    /**
     * 格式化 学生信息数据
     * @param array $studentInfo
     * @param array $info
     * @return array
     */
    private static function formatStudentSearchResult(array $info, array $studentInfo): array
    {
        $studentInfo = array_column($studentInfo, null, 'id');
        foreach ($info as &$value) {
            $value = array_merge($value, $studentInfo[$value['student_id']]);
            $validStatus = 1;
            if ($value['sub_end_date'] < date("Ymd")) $validStatus = 2;
            $value['thumb']            = StudentService::getStudentThumb($value['thumb']);
            $value['valid_status']     = DssStudentModel::VALID_STATUS[$validStatus];
            $value['current_progress'] = DssStudentModel::CURRENT_PROGRESS[$value['has_review_course']];
            $value['create_time_show'] = date('Y-m-d H:i:s', $value['create_time']);
        }
        return $info;
    }

    /**
     * 机构是否存在
     *
     * @param int $agentId
     * @return array
     * @throws RunTimeException
     */
    private static function existAgentOrg(int $agentId): array
    {
        if (empty($agentId)) throw new RunTimeException(['agent_org_id_required']);

        $orgData = AgentOrganizationModel::getRecord([
            'agent_id' => $agentId,
            'status'   => AgentOrganizationModel::STATUS_OK
        ], ['id(org_id)']);

        if (empty($orgData)) throw new RunTimeException(['agent_org_id_required']);

        return $orgData;
    }

    /**
     * 添加机构学员
     *
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function addStudent(array $params): bool
    {

        $orgData = self::existAgentOrg($params['agent_id']);

        $studentInfo = DssStudentModel::getRecord(['mobile' => $params['mobile']],['id(student_id)']);

        if (empty($studentInfo)){
            throw new RunTimeException(['student_mobile_not_exists']);
        }

        $orgStudent = AgentOrganizationStudentModel::getRecords(['student_id' => $studentInfo['student_id']]);

        $isUpdate = 0;

        if (!empty($orgStudent)){
            foreach ($orgStudent as $value){
                if ($value['status'] == AgentOrganizationStudentModel::STATUS_NORMAL && $value['org_id'] == $orgData['org_id']){
                    throw new RunTimeException(['student_org_exist']);
                }

                if ($value['status'] == AgentOrganizationStudentModel::STATUS_NORMAL){
                    throw new RunTimeException(['student_bind_org']);
                }

                if ($value['org_id'] == $orgData['org_id']){
                    $isUpdate = 1;
                }
            }
        }
        $time = time();
        if ($isUpdate) {
            $success = AgentOrganizationStudentModel::batchUpdateRecord(
                [
                    'status'      => AgentOrganizationStudentModel::STATUS_NORMAL,
                    'update_time' => $time,
                    'operator_id' => $params['operator_id'],
                ],
                ['org_id' => $orgData['org_id'], 'student_id' => $studentInfo['student_id']]
            );

        } else {
            $success = AgentOrganizationStudentModel::insertRecord([
                'status'      => AgentOrganizationStudentModel::STATUS_NORMAL,
                'org_id'      => $orgData['org_id'],
                'student_id'  => $studentInfo['student_id'],
                'create_time' => $time,
                'update_time' => $time,
                'operator_id' => $params['operator_id'],
            ]);
        }

        if (empty($success)) {
            throw new RunTimeException(['operator_failure']);
        }

        $opn = AgentOrganizationOpnModel::getRecords([
            'org_id' => $orgData['org_id'],
            'status' => AgentOrganizationOpnModel::STATUS_OK,
        ], ['opn_id']);

        QueueService::updateStudentNameAndCollect([
            'student' => [
                [
                    'student_id' => $studentInfo['student_id'],
                    'real_name'  => $params['real_name']
                ]
            ],
            'opn'     => array_column($opn, 'opn_id'),
        ]);

        return true;
    }

    /**
     * 删除机构学生
     *
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function delStudent(array $params): bool
    {
        $orgData = self::existAgentOrg($params['agent_id']);

        $success = AgentOrganizationStudentModel::batchUpdateRecord(
            [
                'status'      => AgentOrganizationStudentModel::STATUS_DISABLE,
                'update_time' => time(),
                'operator_id' => $params['operator_id'],
            ],
            ['org_id' => $orgData['org_id'], 'student_id' => $params['student_id']]
        );

        if (empty($success)) {
            throw new RunTimeException(['update_failure']);
        }

        return true;
    }

    /**
     * 批量导入操作
     *
     * @param string $filename
     * @param array $employee
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function studentImportAdd(string $filename, array $employee, array $params)
    {
        try {
            $fileType     = ucfirst(pathinfo($filename)["extension"]);
            $reader       = IOFactory::createReader($fileType);
            $spreadsheet  = $reader->load($filename);
            $objWorksheet = $spreadsheet->getActiveSheet();
            $highestRow   = $objWorksheet->getHighestRow();

            $data = [];

            $counter = 0; //空白计数器

            for ($row = 2; $row <= $highestRow; $row++) {
                $mobile   = $objWorksheet->getCell('A' . $row)->getValue();
                $realName = trim($objWorksheet->getCell('B' . $row)->getValue());
                if (empty($mobile) && empty($realName)) {
                    if ($counter > 10) break;
                    $counter++;
                    continue;
                }
                if ($row > 201) throw new \Exception('file_over_maximum_limits');

                $data[] = [
                    'mobile'    => $mobile,
                    'real_name' => $realName,
                ];
            }
            // 检查数据是否为空
            if (empty($data)) throw new \Exception('data_can_not_be_empty');
        }catch (\Exception $e){
            throw new RunTimeException([$e->getMessage()]);
        }

        $orgData = self::existAgentOrg($params['agent_id']);

        [$studentInfo, $insertStudentIds, $updateStudentIds, $errorInfo] = self::checkStudentData($data, $orgData['org_id']);

        if (empty($errorInfo)) {
            $time = time();
            $insertStudentInfo = $updateStudentInfo = [];
            if (!empty($insertStudentIds)) {
                foreach ($insertStudentIds as $id) {
                    $insertStudentInfo[] = [
                        'org_id'      => $orgData['org_id'],
                        'student_id'  => $id,
                        'create_time' => $time,
                        'update_time' => $time,
                        'operator_id' => $employee['id']
                    ];
                }
            }
            if (!empty($updateStudentIds)) {
                $updateStudentInfo = [
                    'where' => [
                        'org_id'     => $orgData['org_id'],
                        'student_id' => $updateStudentIds,
                    ],
                    'data'  => [
                        'update_time' => $time,
                        'operator_id' => $employee['id'],
                        'status'      => AgentOrganizationStudentModel::STATUS_NORMAL
                    ]
                ];
            }

            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $res = AgentOrganizationStudentModel::batchOperator($insertStudentInfo, $updateStudentInfo);

            if (empty($res)) {
                $db->rollBack();
                throw new RunTimeException(['operator_failure']);
            } else {
                $db->commit();
            }

            $opn = AgentOrganizationOpnModel::getRecords([
                'org_id' => $orgData['org_id'],
                'status' => AgentOrganizationOpnModel::STATUS_OK,
            ], ['opn_id']);

            QueueService::updateStudentNameAndCollect([
                'student' => $studentInfo,
                'opn'     => array_column($opn, 'opn_id'),
            ]);
            self::sendEmail($employee['email'], [], count($studentInfo));
        } else {
            self::sendEmail($employee['email'], $errorInfo);
            throw new RunTimeException(['data_error_see_email']);
        }
        return true;
    }

    /**
     * 学生信息校验
     *
     * @param array $data
     * @param int $orgId
     * @return array|array[]
     */
    private static function checkStudentData(array $data, int $orgId): array
    {
        $mobiles     = array_filter(array_column($data, 'mobile'));
        $mobileCount = array_count_values($mobiles);
        $studentData = DssStudentModel::getRecords(['mobile' => $mobiles], ['mobile', 'id']);
        $studentInfo = array_column($studentData, 'id', 'mobile');

        $studentIds = array_column($studentData, 'id');
        $noSelfOrgStudentIds = $orgStudentStatus = [];
        if (!empty($studentIds)) {
            $noSelfOrgStudent = AgentOrganizationStudentModel::getRecords([
                'student_id' => $studentIds,
                'org_id[!]' => $orgId,
                'status' => AgentOrganizationStudentModel::STATUS_NORMAL
            ], ['student_id']);

            $noSelfOrgStudentIds = array_column($noSelfOrgStudent, 'student_id');

            $orgStudent = AgentOrganizationStudentModel::getRecords([
                'org_id' => $orgId,
                'student_id' => $studentIds,
            ], ['student_id', 'status']);
            $orgStudentStatus = array_column($orgStudent, 'status', 'student_id');
        }
        $updateInfo = $insertInfo = $errorInfo = [];
        foreach ($data as &$value) {
            if (empty($value['mobile'])) {
                $errorInfo[] = array_merge($value, ['error_msg' => '手机号为空']);
                continue;
            }
            if (!Util::isChineseMobile($value['mobile'])) {
                $errorInfo[] = array_merge($value, ['error_msg' => '手机号错误']);
                continue;
            }
            if (empty($value['real_name'])) {
                $errorInfo[] = array_merge($value, ['error_msg' => '真实姓名为空']);
                continue;
            }
            if (!Util::isChineseText($value['real_name'])){
                $errorInfo[] = array_merge($value, ['error_msg' => '真实姓名超过10个字或包含了数字与特殊字符']);
                continue;
            }
            if ($mobileCount[$value['mobile']] > 1) {
                $errorInfo[] = array_merge($value, ['error_msg' => '手机号重复']);
                continue;
            }

            if (empty($studentInfo[$value['mobile']])) {
                $errorInfo[] = array_merge($value, ['error_msg' => '非小叶子智能陪练用户']);
                continue;
            }

            $value['student_id'] = $studentInfo[$value['mobile']];

            if (in_array($value['student_id'], $noSelfOrgStudentIds)) {
                $errorInfo[] = array_merge($value, ['error_msg' => '已是其他机构在读学员']);
                continue;
            }

            if ($orgStudentStatus[$value['student_id']] == AgentOrganizationStudentModel::STATUS_NORMAL) {
                $errorInfo[] = array_merge($value, ['error_msg' => '已是该机构在读学员']);
                continue;
            } elseif ($orgStudentStatus[$value['student_id']] == AgentOrganizationStudentModel::STATUS_DISABLE) {
                $updateInfo[] = $value['student_id'];
            } else {
                $insertInfo[] = $value['student_id'];
            }
        }
        return [$data, $insertInfo, $updateInfo, $errorInfo];
    }

    /**
     * 发送邮件
     * @param string $email
     * @param array $errorInfo
     * @param int $number
     * @return bool
     */
    private static function sendEmail(string $email = '', array $errorInfo = [], int $number = 0)
    {
        if (empty($email)) return false;

        $title = '批量导入在读学员完成';
        $content = "本次批量导入在读学员已完成，总共导入{$number}人";
        if (!empty($errorInfo)) {
            $title    = '批量导入在读学员错误数据';
            $fontSize = '3';
            $content  = '<table border="1" style="border-collapse: collapse;" width="400">
                <caption style="text-align:left"><font size=' . $fontSize . '>检测在读学员上传表，以下内容为错误数据，请更新完后重新上传表:</font></caption>
                <thead>
                    <tr>
                        <th>用户手机号</th>
                        <th>学员真实姓名</th>
                        <th>失败原因</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($errorInfo as $value) {
                $content .= "<tr>
                    <th>{$value['mobile']}</th><th>{$value['real_name']}</th><th>{$value['error_msg']}</th>
                    </tr>";
            }
            $content .= '</tbody></table><br><br>';
        }

        PhpMail::sendEmail($email, $title, $content);

        return true;
    }
}