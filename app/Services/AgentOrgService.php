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


    /**
     * 获取机构学生信息
     * @param array $params
     * @return array
     */
    public static function studentList(array $params): array
    {
        $data = [
            'total_count' => 0,
            'list' => []
        ];

        $count = AgentOrganizationStudentModel::getCount([
            'org_id' => $params['agent_id'],
            'status'   => AgentOrganizationStudentModel::STATUS_NORMAL,
        ]);

        if (empty($count)) {
            return $data;
        }

        $data['total_count'] = $count;

        $info = AgentOrganizationStudentModel::getRecords(
            [
                'org_id'   => $params['agent_id'],
                'status'   => AgentOrganizationStudentModel::STATUS_NORMAL,
                'ORDER'    => ['id' => 'DESC'],
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
                    'id', 'uuid', 'mobile', 'name', 'real_name', 'create_time', 'sub_end_date', 'has_review_course'
                ]
            );

            $data['list'] = self::formatStudentSearchResult($studentInfo);
        }

        return $data;
    }


    /**
     * 格式化 学生信息数据
     * @param array $studentInfo
     * @return array
     */
    private static function formatStudentSearchResult(array $studentInfo): array
    {

        foreach ($studentInfo as &$value) {
            $validStatus = 1;
            if ($value['sub_end_date'] < date("Ymd")) {
                $validStatus = 2;
            }
            $value['valid_status']     = DssStudentModel::VALID_STATUS[$validStatus];
            $value['current_progress'] = DssStudentModel::CURRENT_PROGRESS[$value['has_review_course']];
            $value['create_time_show'] = date('Y-m-d H:i:s', $value['create_time']);
        }
        return $studentInfo;
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
        $studentInfo = DssStudentModel::getRecord(['mobile' => $params['mobile']],['id(student_id)']);

        if (empty($studentInfo)){
            throw new RunTimeException(['student_mobile_not_exists']);
        }

        $orgStudent = AgentOrganizationStudentModel::getRecords(['student_id' => $studentInfo['student_id']]);

        $isUpdate = 0;

        if (!empty($orgStudent)){
            foreach ($orgStudent as $value){
                if ($value['status'] == AgentOrganizationStudentModel::STATUS_NORMAL && $value['org_id'] == $params['agent_id']){
                    throw new RunTimeException(['student_exist']);
                }

                if ($value['status'] == AgentOrganizationStudentModel::STATUS_NORMAL){
                    throw new RunTimeException(['student_bind_org']);
                }

                if ($value['org_id'] == $params['agent_id']){
                    $isUpdate = 1;
                }
            }
        }

        if ($isUpdate) {
            $success = AgentOrganizationStudentModel::batchUpdateRecord(
                [
                    'status'      => AgentOrganizationStudentModel::STATUS_NORMAL,
                    'update_time' => time(),
                    'operator_id' => $params['operator_id'],
                ],
                ['org_id' => $params['agent_id'], 'student_id' => $studentInfo['student_id']]
            );

        } else {
            $success = AgentOrganizationStudentModel::insertRecord([
                'status'      => AgentOrganizationStudentModel::STATUS_NORMAL,
                'org_id'      => $params['agent_id'],
                'student_id'  => $studentInfo['student_id'],
                'create_time' => time(),
                'operator_id' => $params['operator_id'],
            ]);
        }

        if (empty($success)) {
            throw new RunTimeException(['operator_failure']);
        }

        $opn = AgentOrganizationOpnModel::getRecords([
            'org_id' => $params['agent_id'],
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
        $success = AgentOrganizationStudentModel::batchUpdateRecord(
            [
                'status'      => AgentOrganizationStudentModel::STATUS_DISABLE,
                'update_time' => time(),
                'operator_id' => $params['operator_id'],
            ],
            ['org_id' => $params['agent_id'], 'student_id' => $params['student_id']]
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
     * @param int $operatorId
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function studentImportAdd(string $filename, int $operatorId, array $params)
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
                if ($row > 200) throw new RunTimeException(['file_over_maximum_limits']);

                $data[] = [
                    'mobile'    => $mobile,
                    'real_name' => $realName,
                ];
            }
            // 检查数据是否为空
            if (empty($data)) throw new RunTimeException(['data_can_not_be_empty', 'import']);
        } catch (\Exception $e) {
            throw new RunTimeException([$e->getMessage()]);
        }

        [$studentInfo, $errorInfo] = self::checkStudentData($data);
        if (empty($errorInfo)) {
            $studentIds = array_column($studentInfo, 'id');

            $orgStudent = AgentOrganizationStudentModel::getRecords(
                ['agent_id' => $params['agent_id']],
                ['student_id', 'status']);

            $orgStudentIds    = array_column($orgStudent, 'student_id');
            $insertStudentIds = array_diff($studentIds, $orgStudentIds);

            $time = time();
            $insertStudentInfo = [];
            if (!empty($insertStudentIds)) {
                foreach ($insertStudentIds as $id) {
                    $insertStudentInfo[] = [
                        'org_id'      => $params['agent_id'],
                        'student_id'  => $id,
                        'create_time' => $time,
                        'operator_id' => $operatorId
                    ];
                }
            }

            $updateStudentInfo = [];
            $updateStudentIds  = array_diff($studentIds, $insertStudentInfo);

            if (!empty($updateStudentIds)) {
                $updateStudentInfo = [
                    'where' => [
                        'org_id'     => $params['agent_id'],
                        'student_id' => $updateStudentIds,
                    ],
                    'data'  => [
                        'update_time' => $time,
                        'operator_id' => $operatorId,
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
                'org_id' => $params['agent_id'],
                'status' => AgentOrganizationOpnModel::STATUS_OK,
            ], ['opn_id']);

            QueueService::updateStudentNameAndCollect([
                'student' => $studentInfo,
                'opn'     => array_column($opn, 'opn_id'),
            ]);
        }

        self::sendEmail($errorInfo, count($studentInfo));
        return true;
    }

    /**
     * 学生信息校验
     * @param array $data
     * @return array|array[]
     */
    private static function checkStudentData(array $data): array
    {
        $mobiles     = array_column($data, 'mobile');
        $mobileCount = array_count_values($mobiles);
        $studentInfo = DssStudentModel::getRecords(['mobile' => $mobiles], ['mobile', 'id']);
        $studentInfo = array_column($studentInfo, 'id', 'mobile');

        $errorInfo = [];
        foreach ($data as &$value) {
            if (empty($value['mobile'])) {
                $errorInfo[]            = $value;
                $errorInfo['error_msg'] = '手机号为空';
                continue;
            }
            if (!Util::isChineseMobile($value['mobile'])) {
                $errorInfo[]            = $value;
                $errorInfo['error_msg'] = '手机号错误';
                continue;
            }
            if (empty($value['real_name'])) {
                $errorInfo[]            = $value;
                $errorInfo['error_msg'] = '真实姓名为空';
                continue;
            }
            if ($mobileCount[$value['mobile']] > 1) {
                $errorInfo[]            = $value;
                $errorInfo['error_msg'] = '手机号重复显示';
                continue;
            }

            if (empty($studentInfo[$value['mobile']])) {
                $errorInfo[]            = $value;
                $errorInfo['error_msg'] = '非小叶子智能陪练用户';
                continue;
            }
            $value['student_id'] = $studentInfo[$value['mobile']];
        }
        return [$data, $errorInfo];
    }

    /**
     * 发送邮件
     * @param array $errorInfo
     * @param int $number
     */
    private static function sendEmail(array $errorInfo = [], int $number = 0)
    {
        $title = '批量导入在读学员完成';
        $content = "本次批量导入在读学员已完成，总共导入{$number}人";
        if (!empty($errorInfo)) {
            $title    = '批量导入在读学员错误数据';
            $fontSize = '5';
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

        $emailsConfig = DictConstants::get(DictConstants::AGENT_ORG_EMAILS, 'import_student');

        PhpMail::sendEmail($emailsConfig, $title, $content);
    }
}