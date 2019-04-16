<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/11/8
 * Time: 11:21 AM
 */
namespace App\Models;

use App\Libs\MysqlDB;

class TeacherTagsModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_DEL = 0;

    const TYPE_SUBJECTIVE = 1; //主观
    const TYPE_OBJECTIVE = 2; //客观

    public static $table = "erp_teacher_tags";
    public static $redisExpire = 86400 * 30;
    public static $redisDB;

    /**
     * 获取正常记录
     * @param $parent_id
     * @return array
     */
    public static function getNormalRecords($parent_id)
    {
        $where = [
            'status' => self::STATUS_NORMAL
        ];
        if ($parent_id){
            $where['parent_id'] = $parent_id;
        }else{
            $where['parent_id'] = null;
        }
        $db = MysqlDB::getDB();
        $result = $db->select(self::$table, [
            "id",
            "name",
            "status",
            "parent_id",
            "update_time"
        ], $where);
        return $result;
    }

    /**
     * 获取相关的关联记录
     * @param $where
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getDetailRecords($where, $page = 0, $count = 0)
    {
        $db = MysqlDB::getDB();
        if ($page > 0 && $count > 0) {
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }

        $teachers = $db->select(self::$table, [
            '[>]' . EmployeeModel::$table => ['operator_id' => 'id']
        ], [
            self::$table . ".id",
            self::$table . ".name",
            self::$table . ".status",
            self::$table . ".type",
            self::$table . ".parent_id",
            self::$table . ".operator_id",
            EmployeeModel::$table . ".name(operator_name)",
            self::$table . ".update_time"
        ], $where);
        return $teachers ?: [];
    }

    /**
     * 获取标签列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getList($page, $count, $params)
    {
        $where['ORDER'] = [TeacherTagsModel::$table . '.update_time' => "DESC"];
        $where['AND'] = [
            TeacherTagsModel::$table . '.parent_id[!]' => null
        ];

        if (!empty($params['parent_id'])){
            $where['AND'][TeacherTagsModel::$table . '.parent_id'] = $params['parent_id'];
        }
        if (!empty($params['tag_id'])){
            $where['AND'][TeacherTagsModel::$table . '.id'] = $params['tag_id'];
        }
        if (!empty($params['type'])){
            $where['AND'][TeacherTagsModel::$table . '.type'] = $params['type'];
        }

        $totalCount = self::getRecordCount($where);
        if ($totalCount == 0) {
            return [[], 0];
        }
        $tags = self::getDetailRecords($where, $page, $count);
        return [$tags, $totalCount];
    }

    /**
     * 插入记录
     * @param $insert
     * @return int|mixed|null|string
     */
    public static function insert($insert)
    {
        $db = MysqlDB::getDB();
        $result = $db->insertGetID(self::$table, $insert);
        return $result;
    }

    /**
     * 更新数据
     * @param $update
     * @param array  $ids
     * @return bool|int|null
     */
    public static function update($update, $ids)
    {
        $where = ['id' => $ids];
        $db = MysqlDB::getDB();
        $result = $db->updateGetCount(self::$table, $update, $where);

        if ($result && $result > 0) {
            foreach($ids as $id){
                self::delCache($id);
            }
        }
        return $result;
    }

    /**
     * 验证子类是否存在
     * @param $name
     * @param $parent_id
     * @return bool
     */
    public static function isExistTag($name, $parent_id){
        $where = [
            'name' => $name,
            'parent_id' => $parent_id,
            'status' => 1
        ];
        $db = MysqlDB::getDB();
        $result = $db->has(self::$table, $where);
        return $result;
    }

    /**
     * 获取记录总数
     * @param $where
     * @return number
     */
    public static function getRecordCount($where)
    {
        return MysqlDB::getDB()->count(self::$table, '*', $where);
    }

    /**
     * 获取父ID
     * @param $ids
     * @return array
     */
    public static function getParentIds($ids)
    {
        $parentIds = MysqlDB::getDB()->select(self::$table, 'parent_id', ['id' => $ids]);
        return array_unique($parentIds);
    }

    /**
     * 获取指定类型的标签
     * 主观 | 客观 | 全部
     * @param $type
     * @return array
     */
    public static function getTags($type)
    {
        $where = [];
        $where['status'] = self::STATUS_NORMAL;
        if(!empty($type)) {
            $where['type'] = $type;
        }
        return self::getRecords($where);
    }

    /**
     * 根据标签名称和标签类型获取父级标签信息
     * @param $name
     * @param $type
     * @return array
     */
    public static function getTagsByNameAndType($name, $type)
    {
        $where = [
            'name' => $name,
            'type' => $type,
            'parent_id' => null
        ];
        return self::getRecord($where);
    }

    /**
     * 根据标签名称和父级ID获取标签信息
     * @param $name
     * @param $parent_id
     * @return array
     */
    public static function getTagsByNameAndParentId($name, $parent_id)
    {
        $where = [
            'name' => $name,
            'parent_id' => $parent_id
        ];
        return self::getRecord($where);
    }

    /**
     * 获取标签id内的客观标签
     * @param $tagIds
     * @return mixed
     */
    public static function getObjectiveTagsByTagIds($tagIds)
    {
        $where = [
            'id' => $tagIds,
            'type' => self::TYPE_OBJECTIVE
        ];
        return self::getRecords($where);
    }
}