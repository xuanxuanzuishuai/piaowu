<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/11/8
 * Time: 12:24 PM
 */
namespace App\Services;

use App\Models\TeacherTagsModel;

class TeacherTagsService
{
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