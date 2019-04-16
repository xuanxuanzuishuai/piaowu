<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/11/8
 * Time: 12:24 PM
 */
namespace App\Services;

use App\Libs\Valid;
use App\Models\TeacherTagsModel;

class TeacherTagsService
{
    /**
     * 添加标签
     * @param $operatorId
     * @param $name
     * @param $parentId
     * @param $type
     * @return int|mixed|null|string
     */
    public static function addTag($operatorId, $name, $parentId, $type)
    {
        $t = time();
        $data = [
            'name' => $name,
            'parent_id' => $parentId,
            'create_time' => $t,
            'update_time' => $t,
            'operator_id' => $operatorId,
            'type' => $type
        ];
        return TeacherTagsModel::insert($data);
    }

    /**
     * 判断标签是否存在
     *
     * @param [string] $name
     * @param [int|null] $parent_id
     * @return mixed
     */
    public static function isExistTag($name, $parent_id = null)
    {
        $is_exist = TeacherTagsModel::isExistTag($name, $parent_id);
        if ($is_exist){
            return Valid::addErrors([], 'teacher_tag', 'teacher_tag_is_exist');
        }
        return [
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ];
    }

    /**
     * 更新标签状态为禁用
     * @param $operator_id
     * @param $ids
     * @param $parentIds
     * @return array|bool|int|null|string
     */
    public static function modifyStatus($operator_id, $ids, $parentIds)
    {
        $data = [
            "status" => 0,
            "operator_id" => $operator_id,
            'update_time' => time()
        ];
        $updateCount = TeacherTagsModel::update($data, $ids);

        //遍历父ID，如果父标签下所有子标签都停用，则父标签也停用
        if($updateCount > 0){
            foreach($parentIds as $parentId){
                $tags = TeacherTagsModel::getNormalRecords($parentId);
                if (empty($tags)){
                    TeacherTagsModel::updateRecord($parentId, $data);
                }
            }
        }

        return $updateCount;
    }

    /**
     * 获取下拉列表信息
     * @param $params
     * @return array
     */
    public static function dropDown($params)
    {
        $result = TeacherTagsModel::getNormalRecords($params['parent_id']);
        return $result;
    }

    /**
     * 获取标签列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getLists($page, $count, $params){

        list($tags, $totalCount) = TeacherTagsModel::getList($page, $count, $params);

        $i = 0;
        foreach ($tags as $key => $value){
            $result = TeacherTagsModel::getById($value['parent_id']);
            $tags[$i]['parent_name'] = $result['name'];
            $tags[$i]['id_number'] = "L" . sprintf("%03d", $value['id']);
            $i++;
        }

        return [$tags, $totalCount];
    }

    /**
     * 获取标签父ID
     * @param $ids
     * @return array
     */
    public static function getTagsParentIds($ids)
    {
        return TeacherTagsModel::getParentIds($ids);
    }

    /**
     * 获取教师标签数据
     * @param $type
     * @return array
     */
    public static function getTagData($type)
    {
        $tags = TeacherTagsModel::getTags($type);
        return self::formatTagsData($tags);
    }

    /**
     * 格式化标签数据
     * @param $tags
     * @return array
     */
    public static function formatTagsData($tags)
    {
        $data = [];
        foreach($tags as $tag){
            if(empty($tag['parent_id'])){
                //一级分类
                $tag['children'] = [];
                $data[$tag['id']] = $tag;
            }else{
                //二级标签
                $data[$tag['parent_id']]['children'][] = $tag;
            }
        }
        return array_values($data);
    }
}