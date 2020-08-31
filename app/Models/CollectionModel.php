<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/11
 * Time: 5:14 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\ListTree;
use App\Libs\MysqlDB;

class CollectionModel extends Model
{
    //表名称
    public static $table = "collection";
    //开放状态:1未开放 2已开放
    const COLLECTION_STATUS_NOT_PUBLISH = 1;
    const COLLECTION_STATUS_IS_PUBLISH = 2;
    //班级状态
    //1、待组班：当前日期还未进入班级组班期，即在组班期开始日之前。
    //2、组班中：当前日期进入班级组班期，即当前日期在组班期中。
    //3、待开班：当前日期出了班级组班期，但还未进入开班期。
    //4、开班中：当前日期进入班级开班期，即当前日期在开班期中。
    //5、已结班：当前日期出了班级开班期，即当前日期在开班期截止日期之后。
    const COLLECTION_PREPARE_STATUS = 1;
    const COLLECTION_READY_TO_GO_STATUS = 2;
    const COLLECTION_WAIT_OPEN_STATUS = 3;
    const COLLECTION_OPENING_STATUS = 4;
    const COLLECTION_END_STATUS = 5;
    //集合类型1普通集合2公共集合
    const COLLECTION_TYPE_NORMAL = 1;
    const COLLECTION_TYPE_PUBLIC = 2;
    //集合中学员数量上限
    const COLLECTION_MAX_CAPACITY = 500;

    //班级授课类型 1体验课2正式课3全部课程
    const COLLECTION_TEACHING_TYPE_EXPERIENCE_CLASS = ReviewCourseModel::REVIEW_COURSE_49;
    const COLLECTION_TEACHING_TYPE_FORMAL_CLASS = ReviewCourseModel::REVIEW_COURSE_1980;
    const COLLECTION_TEACHING_TYPE_ALL_CLASS = 3;

    // 班级类型 对应 package_type
    const TEACHING_TYPE_TRIAL = PackageExtModel::PACKAGE_TYPE_TRIAL;
    const TEACHING_TYPE_NORMAL = PackageExtModel::PACKAGE_TYPE_NORMAL;

    public static $teachingTypeDictMap = [
        'package_id' => self::COLLECTION_TEACHING_TYPE_EXPERIENCE_CLASS,
        'plus_package_id' => self::COLLECTION_TEACHING_TYPE_FORMAL_CLASS,
    ];


    /**
     * 获取指定日期结班数据
     * @param $date
     * @return array
     */
    public static function getRecordByEndTime($date)
    {
        $sql = "SELECT `id`,
                  from_unixtime(`teaching_start_time`, '%Y-%m-%d') AS `start_date`,
                  from_unixtime(`teaching_end_time`, '%Y-%m-%d') AS `end_date` 
                FROM `collection` 
                WHERE `teaching_type` = :type 
                    AND `teaching_end_time` >= :start_time
                    AND `teaching_end_time` <= :end_time ";

        $map = [
            ':type' => self::COLLECTION_TEACHING_TYPE_EXPERIENCE_CLASS,
            ':start_time' => strtotime($date),
            ':end_time' => strtotime($date . " 23:59:59")
        ];

        return MysqlDB::getDB()->queryAll($sql, $map);
    }

    /**
     * 根据班级过程状态获取查询条件
     * @param $processStatus
     * @return array
     */
    public static function getQueryTimeByProcessStatus($processStatus)
    {
        //班级状态
        $res = [];
        $time = time();
        switch ($processStatus) {
            case CollectionModel::COLLECTION_PREPARE_STATUS:
                $res['prepare_start_time'] = ">=" . $time;
                break;
            case CollectionModel::COLLECTION_READY_TO_GO_STATUS:
                $res['prepare_start_time'] = "<=" . $time;
                $res['prepare_end_time'] = ">=" . $time;
                break;

            case CollectionModel::COLLECTION_WAIT_OPEN_STATUS:
                $res['teaching_start_time'] = ">=" . $time;
                $res['prepare_end_time'] = "<=" . $time;
                break;

            case CollectionModel::COLLECTION_OPENING_STATUS:
                $res['teaching_start_time'] = "<=" . $time;
                $res['teaching_end_time'] = ">=" . $time;
                break;
            case CollectionModel::COLLECTION_END_STATUS:
                $res['teaching_end_time'] = "<=" . $time;
                break;
        }
        return $res;
    }

    /**
     * 获取班级统计数据按照部门架构区分
     * @param $deptId
     * @param $where
     * @return array
     */
    public static function staticsCollectionDeptData($deptId, $where)
    {
        $totalCapacities = $totalStudents = 0;
        $tree[] = ['id' => 0, 'name' => '总计', 'total_capacities' => $totalCapacities, 'total_students' => $totalStudents];
        list($assistantRoleId, $courseManageRoleId) = DictConstants::getValues(DictConstants::ORG_WEB_CONFIG,
            ['assistant_role', 'course_manage_role']);
        //获取部门数据
        $deptIdWhere = [
            'status' => Constants::STATUS_TRUE,
        ];
        if (!empty($deptId)) {
            $deptIds = array_column(DeptModel::getSubDeptById($deptId), 'id');
            array_unshift($deptIds, $deptId);
            $deptIdWhere = [
                'id' => $deptIds,
            ];
        }
        $deptData = DeptModel::getRecords($deptIdWhere, ['id', 'name', 'parent_id'], false);
        if (empty($deptData)) {
            return $tree;
        }
        $lt = new ListTree($deptData);
        $db = MysqlDB::getDB();
        //班级统计数据sql
        $sql = "select tp.*,count( s.id ) AS student_count from (SELECT
                    c.id AS cid,
                    c.capacity,
                    c.assistant_id,
                    em.dept_id,em.name as ename
                FROM
                    " . CollectionModel::$table . " AS c
                    INNER JOIN " . EmployeeModel::$table . " AS em ON c.assistant_id = em.id and em.role_id=" . $assistantRoleId . " :where_str) as tp
                    LEFT JOIN " . StudentModel::$table . " as s on tp.cid=s.collection_id group by tp.cid order by tp.dept_id;;";

        $deptFormatData = array_column($deptData, null, 'id');
        foreach ($deptFormatData as &$item) {
            //获取部门下的班级统计数据
            $item['total_capacities'] = 0;
            $item['total_students'] = 0;
            $memberDeptIds = $lt->getChildren($item['id'], true);
            $memberDeptIds[] = $item['id'];
            $deptWhere = "(" . implode(',', $memberDeptIds) . ")";
            $collectionData = $db->queryAll(str_replace(':where_str', $where . " and em.dept_id in " . $deptWhere, $sql));
            $item['total_capacities'] = array_sum(array_column($collectionData, 'capacity'));
            $item['total_students'] = array_sum(array_column($collectionData, 'student_count'));

            //部门下的助教列表数据
            $membersSon = [];
            array_map(function ($cv) use ($item, &$membersSon) {
                if ($cv['dept_id'] == $item['id']) {
                    $membersSon[$cv['assistant_id']]['total_capacities'] += $cv['capacity'];
                    $membersSon[$cv['assistant_id']]['total_students'] += $cv['student_count'];
                    $membersSon[$cv['assistant_id']]['assistant_id'] = $cv['assistant_id'];
                    $membersSon[$cv['assistant_id']]['name'] = $cv['ename'];
                    $membersSon[$cv['assistant_id']]['id'] = $cv['dept_id'] . $cv['assistant_id'];
                }
            }, $collectionData);

            if (isset($deptFormatData[$item['parent_id']])) {
                $deptFormatData[$item['parent_id']]['children'][$item['id']] = &$deptFormatData[$item['id']];

                if (!empty($membersSon)) {
                    $deptFormatData[$item['parent_id']]['children'][$item['id']]['children'] = array_values($membersSon);
                }
                $deptFormatData[$item['parent_id']]['children'] = array_values($deptFormatData[$item['parent_id']]['children']);
            } else {
                $totalCapacities += $item['total_capacities'];
                $totalStudents += $item['total_students'];
                if (empty($deptFormatData[$item['id']]['children']) && !empty($membersSon)) {
                    $deptFormatData[$item['id']]['children'] = array_values($membersSon);
                }
                $tree[] = &$deptFormatData[$item['id']];
            }
        }
        $tree[0]['total_capacities'] = $totalCapacities;
        $tree[0]['total_students'] = $totalStudents;
        return $tree;
    }
}