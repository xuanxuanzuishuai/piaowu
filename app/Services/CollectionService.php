<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/2/29
 * Time: 4:32 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Models\CollectionModel;
use App\Models\CollectionAssistantLogModel;
use App\Models\StudentAssistantLogModel;
use App\Models\StudentModel;
use App\Models\DictModel;
use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Util;
use App\Libs\Valid;

class CollectionService
{
    /**
     * 添加学生集合
     * @param $insertData
     * @return int|mixed|null|string
     */
    public static function addStudentCollection($insertData)
    {
        //公海班级只能添加一条
        if (CollectionModel::COLLECTION_TYPE_PUBLIC == $insertData['type']) {
            $isExists = CollectionModel::getRecord(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "status" => CollectionModel::COLLECTION_STATUS_IS_PUBLISH]);
            if (!empty($isExists)) {
                return false;
            }
        }
        //集合数据
        $collectionId = CollectionModel::insertRecord($insertData, false);
        if (empty($collectionId)) {
            return false;
        }
        //返回结果
        return $collectionId;
    }

    /**
     * 集合详情by唯一ID
     * @param $id
     * @return int|mixed|null|string
     */
    public static function getStudentCollectionDetailByID($id)
    {
        //获取数据库对象
        $db = MysqlDB::getDB();
        $time = time();
        $querySql = "SELECT 
                        a.id,a.name,a.assistant_id,a.capacity,a.remark,a.prepare_start_time,
                        a.prepare_end_time,a.teaching_start_time,a.teaching_end_time,a.wechat_qr,
                        a.wechat_number,a.status,a.teaching_type
                    FROM " . CollectionModel::$table . " as a
                    WHERE a.id = " . $id;
        //获取数据
        $list = $db->queryAll($querySql);
        if ($list) {
            foreach ($list as &$cv) {
                $cv['oss_wechat_qr'] = AliOSS::signUrls($cv['wechat_qr']);
                $cv['process_status'] = self::collectionProcessStatusDict($time, $cv['prepare_start_time'], $cv['prepare_end_time'], $cv['teaching_start_time'], $cv['teaching_end_time']);
            }
        }
        //返回结果
        return $list;
    }


    /**
     * @param $collectionId
     * @param $params
     * @return int|mixed|null|string
     */
    public static function updateStudentCollection($collectionId, $params)
    {
        //检验班级集合当前状态允许操作的数据字段
        $params = self::checkActionIsAllow($collectionId, $params);
        if (empty($params)) {
            return false;
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
        if ($params['wechat_qr']) {
            $collectionData['wechat_qr'] = $params['wechat_qr'];
        }
        if ($params['wechat_number']) {
            $collectionData['wechat_number'] = $params['wechat_number'];
        }
        if ($params['uid']) {
            $collectionData['update_uid'] = $params['uid'];
        }
        if ($params['status']) {
            $collectionData['status'] = $params['status'];
        }
        if ($params['capacity']) {
            $collectionData['capacity'] = $params['capacity'];
        }
        if ($params['teaching_type']) {
            $collectionData['teaching_type'] = $params['teaching_type'];
        }
        $collectionData['update_time'] = time();
        $collectionAffectRows = CollectionModel::updateRecord($collectionId, $collectionData);
        if (empty($collectionAffectRows)) {
            return false;
        }
        //返回结果
        return $collectionId;
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
        //搜索条件
        $limit = " limit " . ($page - 1) * $limit . "," . $limit;
        $orderBy = " order by c.id desc ";
        $where = "where 1=1";
        $time = time();
        $list = [];
        $type = $params['type'] ?? CollectionModel::COLLECTION_TYPE_NORMAL;
        $where .= " and a.type=" . $type;
        if ($params['id'] && is_numeric($params['id'])) {
            $where .= " and a.id=" . $params['id'];
        }
        if ($params['name']) {
            $where .= " and a.name='" . $params['name'] . "'";
        }
        if ($params['assistant_id'] && is_numeric($params['assistant_id'])) {
            $where .= " and a.assistant_id=" . $params['assistant_id'];
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
                        c.NAME AS assistant_name 
                    FROM
                        collection AS a
                        LEFT JOIN employee AS c ON a.assistant_id = c.id 
                        " . $where . ") AS c
                    LEFT JOIN student AS s ON c.id = s.collection_id
                    group by c.id " . $having;
        $countSql = "SELECT count(*) as datanum, " . $whereSql;
        $countData = $db->queryAll($countSql);
        $count = count($countData);
        if (!empty($count)) {
            $listSql = "SELECT c.*," . $whereSql . $orderBy . $limit;
            $list = $db->queryAll($listSql);
            $dictTypeList = DictService::getListsByTypes([Constants::COLLECTION_PUBLISH_STATUS, Constants::COLLECTION_PROCESS_STATUS]);
            $collectionProcessStatusDict = array_column($dictTypeList[Constants::COLLECTION_PROCESS_STATUS], null, "code");
            $collectionPublishStatusDict = array_column($dictTypeList[Constants::COLLECTION_PUBLISH_STATUS], null, "code");
            //转换数据格式
            foreach ($list as &$lv) {
                $lv['oss_wechat_qr'] = AliOSS::signUrls($lv['wechat_qr']);
                $lv['publish_status_name'] = $collectionPublishStatusDict[$lv['status']]['value'];
                $lv['process_status_name'] = $collectionProcessStatusDict[self::collectionProcessStatusDict($time, $lv['prepare_start_time'], $lv['prepare_end_time'], $lv['teaching_start_time'], $lv['teaching_end_time'])]['value'];
                $lv['process_status'] = self::collectionProcessStatusDict($time, $lv['prepare_start_time'], $lv['prepare_end_time'], $lv['teaching_start_time'], $lv['teaching_end_time']);
                $lv['prepare_start_time'] = date("Y-m-d", $lv['prepare_start_time']);
                $lv['prepare_end_time'] = date("Y-m-d", $lv['prepare_end_time']);
                $lv['teaching_start_time'] = date("Y-m-d", $lv['teaching_start_time']);
                $lv['teaching_end_time'] = date("Y-m-d", $lv['teaching_end_time']);
                $lv['create_time'] = date("Y-m-d H:i", $lv['create_time']);
            }
        }

        //返回结果
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
     * 获取课程可以分配的集合列表
     * @param $reviewCourseType
     * @return array|null
     */
    public static function getCollectionByCourseType($reviewCourseType)
    {
        //数据库对象
        $db = MysqlDB::getDB();
        $time = time();
        $querySql = "SELECT
                        a.id,
                        a.capacity,
                        a.assistant_id,
                        a.teaching_start_time,
                        a.teaching_end_time,
                        a.type,
                        a.wechat_number,
                        count( s.id ) AS s_count 
                    FROM
                        collection as a
                        LEFT JOIN student as s ON a.id = s.collection_id 
                    WHERE
                        ( 
                            a.status = " . CollectionModel::COLLECTION_STATUS_IS_PUBLISH . " AND
                            a.prepare_start_time <= " . $time . " AND
                            a.prepare_end_time >= " . $time . " AND
                            a.type = " . CollectionModel::COLLECTION_TYPE_NORMAL . " AND
                            a.teaching_type = " . $reviewCourseType . "
                         )
                    GROUP BY
                        a.id 
                    HAVING
                        a.capacity > s_count
                    ORDER BY
                        a.id ASC
                    LIMIT 1";
        $list = $db->queryAll($querySql);
        //如果没有可加入的班级，则加入“公海班”，推送默认二维码，不分配助教
        if (empty($list)) {
            $list = CollectionModel::getRecords(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "LIMIT" => 1], ['id', 'assistant_id', 'type'], false);
        }
        //返回结果
        return $list;
    }

    /**
     * 根据用户uuid获取所属的集合信息
     * @param $UUID
     * @param $field
     * @return array|null
     */
    public static function getCollectionByUserUUId($UUID, $field = [])
    {
        //获取用户信息
        $collection = [];
        $userInfo = StudentModel::getRecord(["uuid" => $UUID], ["collection_id"], false);
        if (empty($userInfo['collection_id'])) {
            return $collection;
        }
        //获取集合信息
        $collection = CollectionModel::getRecord(["id" => $userInfo["collection_id"]], $field, false);
        $collection["wechat_qr"] = AliOSS::signUrls($collection["wechat_qr"]);
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
     * @param $id
     * @param $params
     * @return array
     */
    public static function checkActionIsAllow($id, $params)
    {
        //获取目标班级集合的信息
        $data = self::getStudentCollectionDetailByID($id);
        //获取当前集合的班级状态
        $nowTime = time();
        $updateParams = [];
        $processStatus = self::collectionProcessStatusDict($nowTime, $data[0]['prepare_start_time'], $data[0]['prepare_end_time'], $data[0]['teaching_start_time'], $data[0]['teaching_end_time']);
        if ($processStatus == CollectionModel::COLLECTION_READY_TO_GO_STATUS) {
            // 组班中:支持【开放】或【关闭】
            if (!empty($params['status']) && ($params['status'] != $data[0]['status'])) {
                $updateParams['status'] = $params['status'];
                $updateParams['uid'] = $params['uid'];
            }
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
}
