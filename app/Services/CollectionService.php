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
use App\Models\CollectionCourseModel;
use App\Models\CourseModel;
use App\Models\StudentModel;
use App\Libs\AliOSS;

class CollectionService
{
    /**
     * 添加学生集合
     * @param $insertData
     * @param $courseIds
     * @return int|mixed|null|string
     */
    public static function addStudentCollection($insertData, $courseIds)
    {
        //公海班级只能添加一条
        if (CollectionModel::COLLECTION_TYPE_PUBLIC == $insertData['type']) {
            $isExists = CollectionModel::getRecord(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "status" => CollectionModel::COLLECTION_STATUS_IS_PUBLISH]);
            if (!empty($isExists)) {
                return false;
            }
        }
        //开启事务
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //集合数据
        $collectionId = CollectionModel::insertRecord($insertData, false);
        if (empty($collectionId)) {
            $db->rollBack();
            return false;
        }
        //集合关联课程
        $insertCollectionCourseData = [];
        foreach ($courseIds as $val) {
            $insertCollectionCourseData[] = [
                'course_id' => $val,
                'collection_id' => $collectionId,
                'create_uid' => $insertData['create_uid'],
                'create_time' => $insertData['create_time'],
            ];
        }
        //写入数据
        $collectionCourseId = CollectionCourseModel::batchInsert($insertCollectionCourseData, false);
        if (empty($collectionCourseId)) {
            $db->rollBack();
            return false;
        }
        //提交事务 返回结果
        $db->commit();
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
        $querySql = "SELECT 
                        a.id,a.name,a.assistant_id,a.capacity,a.remark,a.prepare_start_time,
                        a.prepare_end_time,a.teaching_start_time,a.teaching_end_time,a.wechat_qr,
                        a.wechat_number,GROUP_CONCAT(b.course_id) 
                    FROM " . CollectionModel::$table . " as a
                    LEFT JOIN " . CollectionCourseModel::$table . " as b ON a.id = b.collection_id 
                    WHERE a.id = " . $id . " AND b.status= " . CollectionCourseModel::COLLECTION_COURSE_STATUS_IS_PUBLISH . " GROUP BY a.id";
        //获取数据
        $list = $db->queryAll($querySql);
        if ($list) {
            foreach ($list as &$cv) {
                $cv['oss_wechat_qr'] = AliOSS::signUrls($cv['wechat_qr']);
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
        //验证课程是否存在
        $courseIds = [];
        if ($params['course_ids']) {
            $courseIds = explode(",", $params['course_ids']);
            $courseListInfo = CourseModel::getRecords(['id' => $courseIds, 'status' => CourseModel::COURSE_STATUS_NORMAL], ['id'], false);
            if (empty($courseListInfo) || count($courseIds) != count($courseListInfo)) {
                return false;
            }
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
            $collectionData['prepare_end_time'] = $params['prepare_end_time'];
        }
        if ($params['teaching_start_time']) {
            $collectionData['teaching_start_time'] = $params['teaching_start_time'];
        }
        if ($params['teaching_end_time']) {
            $collectionData['teaching_end_time'] = $params['teaching_end_time'];
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
        $time = time();
        $collectionData['update_time'] = $time;

        //开启事务
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if ($collectionData) {
            $collectionAffectRows = CollectionModel::updateRecord($collectionId, $collectionData);
            if (empty($collectionAffectRows)) {
                $db->rollBack();
                return false;
            }
        }
        //修改合集课程关联数据
        $oldCollectionCourseList = CollectionCourseModel::getRecords(['collection_id' => $collectionId, 'status' => CollectionCourseModel::COLLECTION_COURSE_STATUS_IS_PUBLISH], ['course_id', 'id'], false);
        if ($courseIds) {
            //已存在的课程ID
            $oldCollectionCourseIds = array_column($oldCollectionCourseList, "course_id");
            $needUpdateCourseIds = array_diff($oldCollectionCourseIds, $courseIds);
            $needInsertCourseIds = array_diff($courseIds, $oldCollectionCourseIds);
            if ($needInsertCourseIds) {
                $insertCollectionCourseData = [];
                foreach ($needInsertCourseIds as $icid) {
                    $insertCollectionCourseData[] = [
                        'course_id' => $icid,
                        'collection_id' => $collectionId,
                        'create_uid' => $params['uid'],
                        'create_time' => $time,
                    ];
                }
                $collectionCourseId = CollectionCourseModel::batchInsert($insertCollectionCourseData, false);
                if (empty($collectionCourseId)) {
                    $db->rollBack();
                    return false;
                }
            }

            if ($needUpdateCourseIds) {
                $updateCollectionCourseData = [
                    'status' => CollectionCourseModel::COLLECTION_COURSE_STATUS_NOT_PUBLISH,
                    'update_uid' => $params['uid'],
                    'update_time' => $time,
                ];
                $updateWhere = [
                    'collection_id' => $collectionId,
                    'course_id' => $needUpdateCourseIds,
                ];
                $collectionCourseAffectRows = CollectionCourseModel::batchUpdateRecord($updateCollectionCourseData, $updateWhere, false);
                if (empty($collectionCourseAffectRows)) {
                    $db->rollBack();
                    return false;
                }
            }
        }
        //提交事务 返回结果
        $db->commit();
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
        if ($params['id']) {
            $where .= " and a.id=" . $params['id'];
        }
        if ($params['name']) {
            $where .= " and a.name='" . $params['name'] . "'";
        }
        if ($params['assistant_id']) {
            $where .= " and a.assistant_id=" . $params['assistant_id'];
        }
        if ($params['prepare_start_begin_time']) {
            $where .= " and a.prepare_start_time >=" . $params['prepare_start_begin_time'];
        }
        if ($params['prepare_start_end_time']) {
            $where .= " and a.prepare_start_time <=" . $params['prepare_start_end_time'];
        }
        if ($params['teaching_start_begin_time']) {
            $where .= " and a.teaching_start_time >=" . $params['teaching_start_begin_time'];
        }
        if ($params['teaching_start_end_time']) {
            $where .= " and a.teaching_start_time <=" . $params['teaching_start_end_time'];
        }
        if ($params['create_start_time']) {
            $where .= " and a.create_time>=" . $params['create_start_time'];
        }
        if ($params['create_end_time']) {
            $where .= " and a.create_time<=" . $params['create_end_time'];
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
                    group by c.id " . $having . $orderBy . $limit;
        $countSql = "SELECT count(*) as datanum, " . $whereSql;
        $countData = $db->queryAll($countSql);
        $count = count($countData);
        if (!empty($count)) {
            $listSql = "SELECT c.*," . $whereSql;
            $list = $db->queryAll($listSql);
            $dictTypeList = DictService::getListsByTypes([Constants::COLLECTION_PUBLISH_STATUS, Constants::COLLECTION_PROCESS_STATUS]);
            $collectionProcessStatusDict = array_column($dictTypeList[Constants::COLLECTION_PROCESS_STATUS], null, "code");
            $collectionPublishStatusDict = array_column($dictTypeList[Constants::COLLECTION_PUBLISH_STATUS], null, "code");
            //转换数据格式
            foreach ($list as &$lv) {
                $lv['oss_wechat_qr'] = AliOSS::signUrls($lv['wechat_qr']);
                $lv['publish_status_name'] = $collectionPublishStatusDict[$lv['status']]['value'];
                $lv['process_status_name'] = $collectionProcessStatusDict[self::collectionProcessStatusDict($time, $lv['prepare_start_time'], $lv['prepare_end_time'], $lv['teaching_start_time'], $lv['teaching_end_time'])]['value'];
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
     * @param $packageId
     * @return array|null
     */
    public static function getCollectionByPackageId($packageId)
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
                        a.wechat_number,
                        b.course_id,
                        count( s.id ) AS s_count 
                    FROM
                        collection as a
                        INNER JOIN collection_course as b ON a.id = b.collection_id
                        LEFT JOIN student as s ON a.id = s.collection_id 
                    WHERE
                        ( 
                            a.status = " . CollectionModel::COLLECTION_STATUS_IS_PUBLISH . " AND
                            a.prepare_start_time <= " . $time . " AND
                            a.prepare_end_time >= " . $time . " AND
                            a.type >= " . CollectionModel::COLLECTION_TYPE_NORMAL . " AND
                            b.course_id = " . $packageId . " AND
                            b.status = " . CollectionCourseModel::COLLECTION_COURSE_STATUS_IS_PUBLISH . "
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
            $list = CollectionModel::getRecords(["type" => CollectionModel::COLLECTION_TYPE_PUBLIC, "LIMIT" => 1], ['id', 'assistant_id'], false);
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
     * @return array
     */
    public static function getCollectionDropDownList($name)
    {
        $where = [];
        $field = ['id', 'name'];
        if(!empty($name)){
            $where['name[~]'] = $name;
        }
        return CollectionModel::getRecords($where, $field);
    }
}
