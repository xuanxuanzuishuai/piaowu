<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/2/29
 * Time: 4:32 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Models\CollectionModel;
use App\Models\CollectionAssistantLogModel;
use App\Models\EmployeeModel;
use App\Models\PackageExtModel;
use App\Models\PosterModel;
use App\Models\StudentAssistantLogModel;
use App\Models\StudentModel;
use App\Models\DictModel;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\UserQrTicketModel;

class CollectionService
{
    /**
     * 添加学生集合
     * @param $params
     * @param $operator
     * @return int
     * @throws RunTimeException
     */
    public static function addStudentCollection($params, $operator)
    {
        // 不能添加公海班级
        if ($params['type'] == CollectionModel::COLLECTION_TYPE_PUBLIC) {
            throw new RunTimeException(['cant_add_public_collection']);
        }

        if ($params['teaching_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
            $params['trial_type'] = PackageExtModel::TRIAL_TYPE_NONE;
        }

        if (!PackageService::validateTrialType($params['teaching_type'], $params['trial_type'])) {
            throw new RunTimeException(['invalid_trial_type']);
        }
        $time = time();
        $insertData = [];
        //批量添加班级 检测助教信息
        $assistantInfoList = EmployeeModel::getRecords(
            [
                'id' => $params['assistant_id'],
                'status' => EmployeeModel::STATUS_NORMAL
            ],
            ['id', 'name', 'wx_qr', 'wx_thumb'],
            false
        );
        if (empty($assistantInfoList)) {
            throw new RunTimeException(['assistant_info_error']);
        }
        $missWxInfoAssistantList = [];
        array_map(function ($info) use (&$missWxInfoAssistantList) {
            if (empty($info['wx_qr']) || empty($info['wx_thumb'])) {
                $missWxInfoAssistantList[] = $info['name'];
            }
        }, $assistantInfoList);
        if (!empty($missWxInfoAssistantList)) {
            throw new RunTimeException(['assistant_info_miss'], ['error_detail' => implode(',', $missWxInfoAssistantList)]);
        }
        //远程调用erp获取事件任务信息
        if (!empty($insertData['event_id'])) {
            $eventTaskInfo = ErpReferralService::getEventTasksList();
            if (empty(array_column($eventTaskInfo, null, 'id')[$insertData['task_id']])) {
                throw  new RunTimeException(['event_tasks_disable']);
            }
        }
        foreach ($assistantInfoList as $assistantInfo) {
            $insertData[] = [
                'name' => date("Y-m-d", $params['teaching_start_time']) . $assistantInfo['name'],
                'assistant_id' => $assistantInfo['id'],
                'capacity' => $params['capacity'],
                'remark' => $params['remark'],
                'prepare_start_time' => $params['prepare_start_time'],
                'prepare_end_time' => Util::getStartEndTimestamp($params['prepare_end_time'])[1],
                'teaching_start_time' => $params['teaching_start_time'],
                'teaching_end_time' => Util::getStartEndTimestamp($params['teaching_end_time'])[1],
                'create_uid' => $operator,
                'create_time' => $time,
                'type' => $params['type'] ?? CollectionModel::COLLECTION_TYPE_NORMAL,
                'teaching_type' => $params['teaching_type'],
                'trial_type' => $params['trial_type'],
                'event_id' => $params['event_id'] ?? 0,
                'task_id' => $params['task_id'] ?? 0,
            ];
        }
        //集合数据
        $collectionId = CollectionModel::batchInsert($insertData, false);
        if (empty($collectionId)) {
            throw  new RunTimeException(['add_student_collection_fail']);
        }
        return $collectionId;
    }

    /**
     * 集合详情by唯一ID
     * @param $id
     * @return int|mixed|null|string
     */
    public static function getStudentCollectionDetailByID($id)
    {
        $db = MysqlDB::getDB();
        $list = $db->select(
            CollectionModel::$table,
            [
                "[>]" . EmployeeModel::$table => ["assistant_id" => "id"],
            ],
            [
                CollectionModel::$table . '.id',
                CollectionModel::$table . '.assistant_id',
                CollectionModel::$table . '.name',
                CollectionModel::$table . '.prepare_start_time',
                CollectionModel::$table . '.prepare_end_time',
                CollectionModel::$table . '.teaching_start_time',
                CollectionModel::$table . '.teaching_end_time',
                CollectionModel::$table . '.capacity',
                CollectionModel::$table . '.event_id',
                CollectionModel::$table . '.remark',
                CollectionModel::$table . '.status',
                CollectionModel::$table . '.task_id',
                CollectionModel::$table . '.teaching_type',
                CollectionModel::$table . '.trial_type',
                CollectionModel::$table . '.wechat_qr',
                EmployeeModel::$table . '.name(assistant_name)',
                EmployeeModel::$table . '.wx_qr',
            ],
            [
                CollectionModel::$table . '.id' => $id
            ]
        );
        if (empty($list)) {
            return [];
        }

        $time = time();
        foreach ($list as &$cv) {
            $cv['oss_wechat_qr'] = $cv["wx_qr"] ? AliOSS::signUrls($cv["wx_qr"]) : AliOSS::signUrls($cv["wechat_qr"]);
            $cv['process_status'] = self::collectionProcessStatusDict($time, $cv['prepare_start_time'], $cv['prepare_end_time'], $cv['teaching_start_time'], $cv['teaching_end_time']);
        }

        return $list;
    }

    /**
     * 更新班级url
     * @param $collectionId
     * @param $url
     * @return int
     */
    public static function updateStudentCollectionUrl($collectionId, $url)
    {
        $updateData = ['collection_url' => $url];
        $collectionAffectRows = CollectionModel::updateRecord($collectionId, $updateData);
        return $collectionAffectRows;
    }

    /**
     * @param $collectionId
     * @param $params
     * @param $operator
     * @throws RunTimeException
     */
    public static function updateStudentCollection($collectionId, $params, $operator)
    {

        //检验班级集合当前状态允许操作的数据字段
        $params = self::checkActionIsAllow($collectionId, $params);
        if (empty($params)) {
            throw new RunTimeException(['invalid params']);
        }
        if ($params['name']) {
            $collectionData['name'] = $params['name'];
        }
        if ($params['assistant_id']) {
            $collectionData['assistant_id'] = $params['assistant_id'];
        }
        if ($params['remark']) {
            $collectionData['remark'] = $params['remark'];
        }
        if ($params['prepare_start_time']) {
            $collectionData['prepare_start_time'] = $params['prepare_start_time'];
        }
        if ($params['prepare_end_time']) {
            $collectionData['prepare_end_time'] = Util::getStartEndTimestamp($params['prepare_end_time'])[1];
        }
        if ($params['teaching_start_time']) {
            $collectionData['teaching_start_time'] = $params['teaching_start_time'];
        }
        if ($params['teaching_end_time']) {
            $collectionData['teaching_end_time'] = Util::getStartEndTimestamp($params['teaching_end_time'])[1];
        }
        if ($params['status']) {
            $collectionData['status'] = $params['status'];
        }
        if ($params['capacity']) {
            $collectionData['capacity'] = $params['capacity'];
        }
        if (is_numeric($params['event_id'])) {
            $collectionData['event_id'] = $params['event_id'];
        }
        if (is_numeric($params['task_id'])) {
            $collectionData['task_id'] = $params['task_id'];
        }
        if (isset($params['teaching_type']) || isset($params['trial_type'])) {
            if ($params['teaching_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
                $params['trial_type'] = PackageExtModel::TRIAL_TYPE_NONE;
            }

            if (!PackageService::validateTrialType($params['teaching_type'], $params['trial_type'])) {
                throw new RunTimeException(['invalid_trial_type']);
            }

            $collectionData['teaching_type'] = $params['teaching_type'];
            $collectionData['trial_type'] = $params['trial_type'];
        }

        $collectionData['update_time'] = time();
        $collectionData['update_uid'] = $operator;
        $collectionAffectRows = CollectionModel::updateRecord($collectionId, $collectionData);
        if (empty($collectionAffectRows)) {
            throw new RunTimeException(['update_failure']);
        }
    }


    /**
     * 获取列表
     * @param $params
     * @param $page
     * @param $limit
     * @return int|mixed|null|string
     */
    public static function getStudentCollectionList($params, $page = 1, $limit = 20)
    {
        $time = time();

        $limit = " limit " . ($page - 1) * $limit . "," . $limit;
        $orderBy = " order by c.id desc ";
        $where = "where 1=1";

        $type = $params['type'] ?? CollectionModel::COLLECTION_TYPE_NORMAL;
        $where .= " and a.type=" . $type;

        if ($params['id'] && is_numeric($params['id'])) {
            $where .= " and a.id=" . $params['id'];

        } elseif (!empty($params['name'])) {
            $like = Util::sqlLike($params['name']);
            $where .= " and a.name like '{$like}' ";
        }

        if (!empty($params['assistant_id'])) {
            if (is_array($params['assistant_id'])) {
                $assistantIds = implode(', ', $params['assistant_id']);
                $assistantFilter = "a.assistant_id IN ({$assistantIds})";
            } elseif (is_numeric($params['assistant_id'])) {
                $assistantFilter = "a.assistant_id = {$params['assistant_id']}";
            } else {
                $assistantFilter = '';
            }

            if (!empty($assistantFilter)) {
                $where .= " AND ({$assistantFilter}) ";
            }
        }

        if ($params['prepare_start_begin_time']) {
            $where .= " and a.prepare_start_time >=" . $params['prepare_start_begin_time'];
        }
        if ($params['prepare_start_end_time']) {
            $where .= " and a.prepare_start_time <=" . Util::getStartEndTimestamp($params['prepare_start_end_time'])[1];
        }
        if ($params['teaching_start_begin_time']) {
            $where .= " and a.teaching_start_time >=" . $params['teaching_start_begin_time'];
        }
        if ($params['teaching_start_end_time']) {
            $where .= " and a.teaching_start_time <=" . Util::getStartEndTimestamp($params['teaching_start_end_time'])[1];
        }
        if ($params['create_start_time']) {
            $where .= " and a.create_time>=" . $params['create_start_time'];
        }
        if ($params['create_end_time']) {
            $where .= " and a.create_time<=" . Util::getStartEndTimestamp($params['create_end_time'])[1];
        }
        if ($params['publish_status']) {
            $where .= " and a.status=" . $params['publish_status'];
        }

        if (isset($params['teaching_type'])) {
            $where .= " and a.teaching_type=" . (int)$params['teaching_type'];
        }

        if (isset($params['trial_type'])) {
            $where .= " and a.trial_type=" . (int)$params['trial_type'];
        }

        if (is_numeric($params['task_id'])) {
            $where .= " and a.task_id=" . $params['task_id'];
        }

        //学生数量:最低值默认0
        $studentMinCount = empty($params['student_min_count']) ? 0 : (int)$params['student_min_count'];
        $having = " HAVING student_count>=" . $studentMinCount;
        if (!empty($params['student_max_count']) && is_numeric($params['student_max_count'])) {
            $having .= " and student_count<=" . $params['student_max_count'];
        }

        //班级状态
        switch ($params['process_status']) {
            case CollectionModel::COLLECTION_PREPARE_STATUS:
                $where .= " and a.prepare_start_time >=" . $time;
                break;
            case CollectionModel::COLLECTION_READY_TO_GO_STATUS:
                $where .= " and a.prepare_start_time <=" . $time . " and a.prepare_end_time >=" . $time;
                break;

            case CollectionModel::COLLECTION_WAIT_OPEN_STATUS:
                $where .= " and a.prepare_end_time <=" . $time . " and a.teaching_start_time >=" . $time;
                break;

            case CollectionModel::COLLECTION_OPENING_STATUS:
                $where .= " and a.teaching_start_time <=" . $time . " and a.teaching_end_time >=" . $time;
                break;
            case CollectionModel::COLLECTION_END_STATUS:
                $where .= " and a.teaching_end_time <=" . $time;
                break;
        }
        //获取数量
        $db = MysqlDB::getDB();
        $whereSql = " count( s.id ) AS student_count FROM
                    ( SELECT
                        a.*,
                        c.NAME AS assistant_name,
                        c.wx_qr
                    FROM
                        collection AS a
                        LEFT JOIN employee AS c ON a.assistant_id = c.id 
                        " . $where . ") AS c
                    LEFT JOIN student AS s ON c.id = s.collection_id
                    group by c.id " . $having;
        $countSql = "SELECT count(*) as datanum, " . $whereSql;
        $countData = $db->queryAll($countSql);
        $count = count($countData);

        if (empty($count)) {
            return [0, []];
        }

        $listSql = "SELECT c.*," . $whereSql . $orderBy . $limit;
        $list = $db->queryAll($listSql);

        $dictTypeList = DictService::getListsByTypes([Constants::COLLECTION_PUBLISH_STATUS, Constants::COLLECTION_PROCESS_STATUS]);
        $collectionProcessStatusDict = array_column($dictTypeList[Constants::COLLECTION_PROCESS_STATUS], null, "code");
        $collectionPublishStatusDict = array_column($dictTypeList[Constants::COLLECTION_PUBLISH_STATUS], null, "code");
        $packageTypeDict = DictConstants::getSet(DictConstants::PACKAGE_TYPE);
        $trialTypeDict = DictConstants::getSet(DictConstants::TRIAL_TYPE);

        //事件任务信息
        $taskInfo = [];
        $eventIds = array_unique(array_column($list, 'event_id'));
        if (!empty($eventIds)) {
            $eventTask = ErpReferralService::getEventTasksList($eventIds);
            $taskInfo = array_column($eventTask, null, 'id');
        }

        foreach ($list as &$lv) {
            $lv['oss_wechat_qr'] = $lv['wx_qr'] ? AliOSS::signUrls($lv['wx_qr']) : AliOSS::signUrls($lv["wechat_qr"]);
            $lv['publish_status_name'] = $collectionPublishStatusDict[$lv['status']]['value'];
            $lv['process_status_name'] = $collectionProcessStatusDict[self::collectionProcessStatusDict($time, $lv['prepare_start_time'], $lv['prepare_end_time'], $lv['teaching_start_time'], $lv['teaching_end_time'])]['value'];
            $lv['process_status'] = self::collectionProcessStatusDict($time, $lv['prepare_start_time'], $lv['prepare_end_time'], $lv['teaching_start_time'], $lv['teaching_end_time']);
            $lv['prepare_start_time'] = date("Y-m-d", $lv['prepare_start_time']);
            $lv['prepare_end_time'] = date("Y-m-d", $lv['prepare_end_time']);
            $lv['teaching_start_time'] = date("Y-m-d", $lv['teaching_start_time']);
            $lv['teaching_end_time'] = date("Y-m-d", $lv['teaching_end_time']);
            $lv['create_time'] = date("Y-m-d H:i", $lv['create_time']);
            $lv['teaching_type_name'] = $packageTypeDict[$lv['teaching_type']] ?? '-';
            $lv['trial_type_name'] = $trialTypeDict[$lv['trial_type']] ?? '-';
            $lv['task_name'] = $taskInfo[$lv['task_id']]['name'] ?? "无";
        }

        return [$count, $list];
    }

    /**
     * 学生集合状态
     * @param $nowTime
     * @param $prepareStartTime
     * @param $prepareEndTime
     * @param $teachingStartTime
     * @param $teachingEndTime
     * @return int|mixed|null|string
     * 1、待组班：当前日期还未进入班级组班期，即在组班期开始日之前。
     * 2、组班中：当前日期进入班级组班期，即当前日期在组班期中。
     * 3、待开班：当前日期出了班级组班期，但还未进入开班期。
     * 4、开班中：当前日期进入班级开班期，即当前日期在开班期中。
     * 5、已结班：当前日期出了班级开班期，即当前日期在开班期截止日期之后。
     */
    public static function collectionProcessStatusDict($nowTime, $prepareStartTime, $prepareEndTime, $teachingStartTime, $teachingEndTime)
    {
        if ($nowTime < $prepareStartTime) {
            $status = CollectionModel::COLLECTION_PREPARE_STATUS;
        } elseif ($nowTime >= $prepareStartTime && $nowTime <= $prepareEndTime) {
            $status = CollectionModel::COLLECTION_READY_TO_GO_STATUS;

        } elseif ($nowTime > $prepareEndTime && $nowTime < $teachingStartTime) {
            $status = CollectionModel::COLLECTION_WAIT_OPEN_STATUS;

        } elseif ($nowTime >= $teachingStartTime && $nowTime <= $teachingEndTime) {
            $status = CollectionModel::COLLECTION_OPENING_STATUS;

        } elseif ($nowTime > $teachingEndTime) {
            $status = CollectionModel::COLLECTION_END_STATUS;

        } else {
            $status = 0;
        }
        //返回结果
        return $status;
    }

    /**
     * 根据池子分配规则得到的助教ID获取本助教下组班中的班级
     * @param $assistantId
     * @param $collectionStatus
     * @param int $packageType
     * @param $trialType
     * @return array
     */
    public static function getValidCollection($assistantId,
                                              $collectionStatus,
                                              $packageType,
                                              $trialType)
    {
        //数据库对象
        $db = MysqlDB::getDB();
        $time = time();
        //当前可用的班级列表
        $dayStartEndTimestamp = Util::getStartEndTimestamp($time);
        $querySql = "SELECT
                        c.id,
                        c.capacity,
                        c.assistant_id,
                        c.teaching_start_time,
                        c.teaching_end_time,
                        c.type,
                        c.wechat_number,
                        ( SELECT COUNT( id ) FROM student AS s WHERE s.collection_id = c.id ) AS total_allot,
                        COUNT( scl.id ) AS today_allot
                    FROM
                        collection AS c
                        LEFT JOIN student_collection_log AS scl ON c.id = scl.new_collection_id
                        AND scl.old_collection_id = 0 AND scl.create_time BETWEEN " . $dayStartEndTimestamp[0] . " AND " . $dayStartEndTimestamp[1] . "
                    WHERE
                        c.status = " . $collectionStatus . "
                        AND c.type = " . CollectionModel::COLLECTION_TYPE_NORMAL . " 
                        AND c.teaching_type = " . $packageType . "
                        AND c.trial_type = " . $trialType . "
                        AND c.prepare_start_time <= " . $time . " 
                        AND c.prepare_end_time  >= " . $time . "
                        AND c.assistant_id  = " . $assistantId . "
                    GROUP BY
                        c.id
                    HAVING
                        c.capacity > total_allot
                    ORDER BY
                        prepare_start_time ASC,
                        c.id ASC
                    LIMIT 1";
        //查询数据获取可分配班级
        $list = $db->queryAll($querySql);
        //返回结果
        return $list[0] ?? null;
    }

    public static function getRefValidCollection($assistantId,
                                                 $collectionStatus,
                                                 $packageType,
                                                 $trialType)
    {
        //数据库对象
        $db = MysqlDB::getDB();
        $time = time();
        //当前可用的班级列表
        $dayStartEndTimestamp = Util::getStartEndTimestamp($time);
        $querySql = "SELECT
                        c.id,
                        c.capacity,
                        c.assistant_id,
                        c.teaching_start_time,
                        c.teaching_end_time,
                        c.type,
                        c.wechat_number,
                        ( SELECT COUNT( id ) FROM student AS s WHERE s.collection_id = c.id ) AS total_allot,
                        COUNT( scl.id ) AS today_allot
                    FROM
                        collection AS c
                        LEFT JOIN student_collection_log AS scl ON c.id = scl.new_collection_id
                        AND scl.old_collection_id = 0 AND scl.create_time BETWEEN " . $dayStartEndTimestamp[0] . " AND " . $dayStartEndTimestamp[1] . "
                    WHERE
                        c.status = " . $collectionStatus . "
                        AND c.type = " . CollectionModel::COLLECTION_TYPE_NORMAL . " 
                        AND c.teaching_type = " . $packageType . "
                        AND c.trial_type = " . $trialType . "
                        AND c.teaching_start_time > " . $time . "
                        AND c.assistant_id  = " . $assistantId . "
                    GROUP BY
                        c.id
                    HAVING
                        c.capacity > total_allot
                    ORDER BY
                        prepare_start_time ASC,
                        c.id ASC
                    LIMIT 1";
        //查询数据获取可分配班级
        $list = $db->queryAll($querySql);
        //返回结果
        return $list[0] ?? null;
    }

    /**
     * 根据用户uuid获取所属的集合信息
     * @param $UUID
     * @return array|null
     */
    public static function getCollectionByUserUUId($UUID)
    {
        //获取用户信息
        $collection = [];
        $userInfo = StudentModel::getRecord(["uuid" => $UUID], ["collection_id", 'id'], false);
        if (empty($userInfo['collection_id'])) {
            return $collection;
        }
        //获取集合信息
        $db = MysqlDB::getDB();
        $collection = $db->get(
            CollectionModel::$table,
            [
                "[>]" . EmployeeModel::$table => ["assistant_id" => "id"],
            ],
            [
                CollectionModel::$table . '.wechat_qr',
                EmployeeModel::$table . '.wx_qr',
            ],
            [
                CollectionModel::$table . '.id' => $userInfo["collection_id"]
            ]
        );
        $collection["wechat_qr"] = $collection["wx_qr"] ? AliOSS::signUrls($collection["wx_qr"]) : AliOSS::signUrls($collection["wechat_qr"]);
        //获取转介绍配置数据
        $referralConfig = PosterModel::getRecord(["apply_type" => PosterModel::APPLY_TYPE_STUDENT_WECHAT, "status" => PosterModel::STATUS_PUBLISH, "poster_type" => PosterModel::POSTER_TYPE_WECHAT_STANDARD], ['url', 'settings', 'content1', 'content2']);
        $settings = json_decode($referralConfig['settings'], true);
        //生成二维码海报
        $collection['recommend_info'] = UserService::generateQRPosterAliOss($userInfo['id'], $referralConfig['url'], UserQrTicketModel::STUDENT_TYPE, $settings['poster_width'], $settings['poster_height'], $settings['qr_width'], $settings['qr_height'], $settings['qr_x'], $settings['qr_y'], DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'BUY_TRAIL_STUDENT_INVITE_STUDENT'), UserQrTicketModel::LANDING_TYPE_NORMAL);
        //返回结果
        return $collection;
    }

    /**
     * 获取班级下拉菜单列表数据
     * @param $name
     * @param $notOver
     * @return array
     */
    public static function getCollectionDropDownList($name, $notOver = false)
    {
        $where = [];
        $field = ['id', 'name'];
        if (!empty($name)) {
            $where['name[~]'] = $name;
        }
        //是否只取未结课的班级
        if ($notOver) {
            $where['teaching_end_time[>]'] = time();
        }
        return CollectionModel::getRecords($where, $field);
    }

    /**
     * 检测班级集合允许操作的字段
     * @param $collectionId
     * @param $params
     * @return array
     */
    public static function checkActionIsAllow($collectionId, $params)
    {
        //获取目标班级集合的信息
        $data = self::getStudentCollectionDetailByID($collectionId);

        //获取当前集合的班级状态
        $nowTime = time();
        $updateParams = [];
        $processStatus = self::collectionProcessStatusDict($nowTime, $data[0]['prepare_start_time'], $data[0]['prepare_end_time'], $data[0]['teaching_start_time'], $data[0]['teaching_end_time']);
        if ($processStatus == CollectionModel::COLLECTION_READY_TO_GO_STATUS) {
            // 组班中:班级状态、班级名称、组班期、班级容量、参与活动、备注均可以修改
            $updateFields = [
                'status',
                'name',
                'prepare_start_time',
                'prepare_end_time',
                'capacity',
                'event_id',
                'task_id',
                'remark',
            ];
            array_map(function ($fv) use ($params, &$updateParams, $data) {
                if (isset($params[$fv]) && ($params[$fv] != $data[0][$fv])) {
                    $updateParams[$fv] = $params[$fv];
                }
            }, $updateFields);
        } elseif ($processStatus == CollectionModel::COLLECTION_PREPARE_STATUS) {
            //待组班:支持任何操作
            $updateParams = $params;
        }

        return $updateParams;
    }

    /**
     * 获取购买产品包下拉框
     * @param $keyCode
     * @return array
     */
    public static function getCollectionPackageList($keyCode)
    {
        //获取数据
        $data = [];
        $dictList = DictModel::getRecords(['type' => DictConstants::PACKAGE_CONFIG['type'], 'key_code' => [$keyCode]], ['key_code', 'key_value', 'desc'], false);
        if (empty($dictList)) {
            return $data;
        }
        //转换数据
        foreach ($dictList as $dv) {
            $data[] = [
                'teaching_type' => CollectionModel::$teachingTypeDictMap[$dv['key_code']],
                'type_name' => $dv['desc'],
            ];
        }
        return $data;
    }


    /**
     * 班级分配助教
     * @param $collectionIDList
     * @param $assistantId
     * @param $employeeID
     * @return array
     */
    public static function reAllotCollectionAssistant($collectionIDList, $assistantId, $employeeID)
    {
        //获取班级数据
        $collectionList = CollectionModel::getRecords(['id' => $collectionIDList, 'assistant_id[!]' => $assistantId], ['id', 'assistant_id'], false);
        if (empty($collectionList)) {
            return Valid::addErrors([], 'collection', 'collection_assistant_data_no_change');
        }
        $result = [];
        //开启事务
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $time = time();
        foreach ($collectionList as $collectionData) {
            //修改班级助教信息
            $collectionAffectRow = CollectionModel::UpdateRecord($collectionData['id'], ['assistant_id' => $assistantId, 'update_uid' => $employeeID, 'update_time' => $time], false);
            if (empty($collectionAffectRow)) {
                $db->rollBack();
                return Valid::addErrors([], 'collection', 'update_student_collection_fail');
            }
            //记录班级分配助教日志
            $logData = [
                'collection_id' => $collectionData['id'],
                'old_assistant_id' => $collectionData['assistant_id'],
                'new_assistant_id' => $assistantId,
                'create_time' => $time,
                'create_uid' => $employeeID,
            ];
            $collectionAssistantLogId = CollectionAssistantLogModel::insertRecord($logData, false);
            if (empty($collectionAssistantLogId)) {
                $db->rollBack();
                return Valid::addErrors([], 'collection', 'insert_collection_assistant_log_fail');
            }
            //获取当前属于该班级并且助教是当前助教下的学生数据
            $studentList = StudentModel::getRecords(['collection_id' => $collectionData['id'], 'assistant_id' => $collectionData['assistant_id']], ['id', 'assistant_id'], false);
            if (empty($studentList)) {
                continue;
            }
            $studentNewAssistantData = ['assistant_id' => $assistantId, 'allot_assistant_time' => $time];
            $studentUpdateAssistantAffectRows = StudentModel::batchUpdateRecord($studentNewAssistantData, ['collection_id' => $collectionData['id'], 'assistant_id' => $collectionData['assistant_id']], false);
            if (empty($studentUpdateAssistantAffectRows)) {
                $db->rollBack();
                return Valid::addErrors([], 'collection', 'update_student_data_failed');
            }
            //记录学生分配助教日志
            $studentAllotAssistantLogData = StudentService::formatAllotAssistantLogData($studentList, $assistantId, $employeeID, $time, StudentAssistantLogModel::OPERATE_TYPE_ALLOT_COLLECTION_ASSISTANT, $collectionAssistantLogId);
            $logRes = StudentService::addAllotAssistantLog($studentAllotAssistantLogData);
            if (empty($logRes)) {
                $db->rollBack();
                return Valid::addErrors([], 'collection', 'add_allot_assistant_log_failed');
            }
        }
        $db->commit();
        return $result;
    }


    /**
     * 获取班级微信号信息
     * @param $collectionId
     * @return array
     */
    public static function getCollectionWechatInfo($collectionId)
    {

        $needAddWx = 0;
        $wechatQr = '';

        if (!empty($collectionId)) {
            // 获取班级信息
            $db = MysqlDB::getDB();
            $collection = $db->get(
                CollectionModel::$table,
                [
                    "[>]" . EmployeeModel::$table => ["assistant_id" => "id"],
                ],
                [
                    CollectionModel::$table . '.type',
                    CollectionModel::$table . '.teaching_end_time',
                    CollectionModel::$table . '.wechat_qr',
                    EmployeeModel::$table . '.wx_qr',
                ],
                [
                    CollectionModel::$table . '.id' => $collectionId
                ]
            );
            // 班级仍在有效期, 或公海班级
            if ($collection['teaching_end_time'] > time() || $collection['type'] == CollectionModel::COLLECTION_TYPE_PUBLIC) {
                $needAddWx = 1;
                $wxQr = $collection["wx_qr"] ?? $collection["wechat_qr"];
                $wechatQr = AliOSS::signUrls($wxQr);
            }
        }

        return [$needAddWx, $wechatQr];
    }

    /**
     * 获取班级关联的event事件数据
     * @param $collectionId
     * @return array
     */
    public static function getCollectionJoinEventInfo($collectionId)
    {
        //获取班级信息
        $result = [
            'info' => [],
            'task_condition' => []
        ];
        $collectionInfo = CollectionModel::getById($collectionId);
        if (empty($collectionInfo)) {
            return $result;
        }
        $result['info'] = $collectionInfo;
        if (empty($collectionInfo['event_id']) || empty($collectionInfo['task_id'])) {
            return $result;
        }
        //获取事件任务信息
        $eventTaskList = ErpReferralService::getEventTasksList($collectionInfo['event_id']);
        $taskConditionInfo = array_column($eventTaskList, null, 'id')[$collectionInfo['task_id']]['condition'];
        $result['task_condition'] = $taskConditionInfo;
        return $result;
    }

    /**
     * 获取指定日期结班班级数据
     * @param $date
     * @return array
     */
    public static function getCollectionByEndTime($date)
    {
        return CollectionModel::getRecordByEndTime($date);
    }

    /**
     * 获取指定日期开班班级数据
     * @param $date
     * @return array
     */
    public static function getCollectionByStartTime($date)
    {
        return CollectionModel::getRecordByStartTime($date);
    }


    /**
     * 获取班级统计数据按照部门架构区分
     * @param $params
     * @return array
     */
    public static function getCollectionDeptData($params)
    {
        $where = " where c.type=" . CollectionModel::COLLECTION_TYPE_NORMAL;
        if ($params['prepare_start_begin_time']) {
            $where .= " and c.prepare_start_time >=" . $params['prepare_start_begin_time'];
        }
        if ($params['prepare_start_end_time']) {
            $where .= " and c.prepare_start_time <=" . Util::getStartEndTimestamp($params['prepare_start_end_time'])[1];
        }
        if ($params['teaching_start_begin_time']) {
            $where .= " and c.teaching_start_time >=" . $params['teaching_start_begin_time'];
        }
        if ($params['teaching_start_end_time']) {
            $where .= " and c.teaching_start_time <=" . Util::getStartEndTimestamp($params['teaching_start_end_time'])[1];
        }
        if (isset($params['teaching_type'])) {
            $where .= " and c.teaching_type=" . (int)$params['teaching_type'];
        }
        if (isset($params['trial_type'])) {
            $where .= " and c.trial_type=" . (int)$params['trial_type'];
        }
        if (is_numeric($params['task_id'])) {
            $where .= " and c.task_id=" . $params['task_id'];
        }
        //班级状态
        if (!empty($params['process_status'])) {
            $processStatus = CollectionModel::getQueryTimeByProcessStatus($params['process_status']);
            array_walk($processStatus, function ($psv, $psk) use (&$processStatusWhere) {
                $processStatusWhere[] = "c." . $psk . $psv;
            });
            $where .= " AND " . implode(' AND ', $processStatusWhere);
        }
        $data = CollectionModel::staticsCollectionDeptData($params['dept_id'], $where);
        return $data;
    }

    /**
     * 学生转介绍推荐人的班级助教信息（待新功能稳定，删除此方法）
     * @param $studentId
     * @param $packageType
     * @param $trialType
     * @return array|mixed
     */
    public static function getCollectionByRefereeIdV1($studentId, $packageType, $trialType)
    {
        //获取学生转介绍推荐人的班级助教信息
        $refereeData = StudentRefereeService::studentRefereeUserData($studentId);
        if (empty($refereeData['assistant_id'])) {
            return [];
        }
        $time = time();
        $where['assistant_id'] = $refereeData['assistant_id'];
        $where['teaching_type'] = $packageType;
        $where['trial_type'] = $trialType;
        //班级状态
        $where['prepare_start_time[<=]'] = $time;
        $where['prepare_end_time[>=]'] = $time;
        $where['ORDER'] = ["id" => "ASC"];
        $refereeCollection = CollectionModel::getRecord($where, '*');
        return $refereeCollection;
    }

    /**
     * 获取课程可以分配的集合列表（待新功能稳定，删除此方法）
     * @param $packageType
     * @param $trialType
     * @return array|null
     */
    public static function getCollectionByCourseTypeV1($packageType, $trialType)
    {
        //数据库对象
        $db = MysqlDB::getDB();
        $time = time();
        //当前可用的班级列表
        $dayStartEndTimestamp = Util::getStartEndTimestamp($time);
        $querySql = "SELECT
                        c.id,
                        c.capacity,
                        c.assistant_id,
                        c.teaching_start_time,
                        c.teaching_end_time,
                        c.type,
                        c.wechat_number,
                        ( SELECT COUNT( id ) FROM student AS s WHERE s.collection_id = c.id ) AS total_allot,
                        COUNT( scl.id ) AS today_allot
                    FROM
                        collection AS c
                        LEFT JOIN student_collection_log AS scl ON c.id = scl.new_collection_id
                        AND scl.old_collection_id = 0 AND scl.create_time BETWEEN " . $dayStartEndTimestamp[0] . " AND " . $dayStartEndTimestamp[1] . "
                    WHERE
                        c.STATUS = " . CollectionModel::COLLECTION_STATUS_IS_PUBLISH . "
                        AND c.type = " . CollectionModel::COLLECTION_TYPE_NORMAL . "
                        AND c.teaching_type = " . $packageType . "
                        AND c.trial_type = " . $trialType . "
                        AND c.prepare_start_time <= " . $time . "
                        AND c.prepare_end_time  >= " . $time . "
                    GROUP BY
                        c.id
                    HAVING
                        c.capacity > total_allot
                    ORDER BY
                        today_allot ASC,
                        c.id ASC
                    LIMIT 1";
        //查询数据获取可分配班级
        $list = $db->queryAll($querySql);
        //如果没有可加入的班级，则加入“公海班”，推送默认二维码，不分配助教
        if (empty($list)) {
            $list = CollectionModel::getRecords(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "LIMIT" => 1], ['id', 'assistant_id', 'type'], false);
        }
        //返回结果
        return $list[0] ?? null;
    }
}
