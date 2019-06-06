<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/19
 * Time: 1:21 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\OpernCenter;
use App\Libs\Valid;

class OpernService
{
    /**
     * 格式化分类列表
     * @param $data
     * @return array
     */
    public static function formatCategories($data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $category) {
            $series['id'] = $category['id'];
            $series['name'] = $category['name'];
            $series['cover'] = $category['cover'] ? $category['cover'] : '';
            $series['have_res'] = $category['dynamic'] ? '1' : '0';
            $series['book_cnt'] = $category['collection_count'];
            $series['opern_cnt'] = $category['lesson_count'];
            $series['is_free'] = $category['freeflag'] ? '1' : '0';
            $result[] = $series;
        }
        return $result;
    }

    /**
     * 格式化合集列表
     * @param $data
     * @return array
     */
    public static function appFormatCollections($data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $collection) {
            $book['id'] = $collection['id'];
            $book['name'] = $collection['name'];
            $book['cover'] = $collection['cover'] ? $collection['cover'] : '';
            $book['have_res'] = $collection['dynamic'] ? '1' : '0';
            $book['opern_cnt'] = $collection['lesson_count'];
            $book['is_free'] = $collection['freeflag'] ? '1' : '0';
            $result[] = $book;
        }
        return $result;
    }

    /**
     * 格式化曲谱列表
     * @param $data
     * @return array
     */
    public static function appFormatLessons($data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $lesson) {
            $opern['id'] = $lesson['id'];
            $opern['name'] = $lesson['name'];
            $opern['score_id'] = $lesson['opern_id'];
            $opern['res'] = !empty($lesson['resources']) ? $lesson['resources'][0]['resource_url'] : '';
            $opern['is_free'] = $lesson['freeflag'] ? '1' : '0';
            $opern['knowledge'] = $lesson['knowledge'] ? 1 : 0;
            $result[] = $opern;
        }
        return $result;
    }

    /**
     * 格式化曲谱列表
     * @param $data
     * @return array
     */
    public static function appFormatLessonByIds($data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $lesson) {
            $opern['id'] = $lesson['lesson_id'];
            $opern['lesson_name'] = $lesson['lesson_name'];
            $opern['name'] = $lesson['opern_name'];
            $opern['score_id'] = $lesson['opern_id'];
            $opern['res'] = !empty($lesson['resources']) ? $lesson['resources'][0]['url'] : '';
            $opern['is_free'] = $lesson['freeflag'] ? '1' : '0';
            $opern['knowledge'] = $lesson['knowledge'] ? 1 : 0;
            $opern['collection_id'] = $lesson['collection_id'] ? $lesson['collection_id'] : '';
            $opern['collection_name'] = $lesson['collection_name'] ? $lesson['collection_name'] : '';
            $opern['collection_cover'] = $lesson['collection_cover'] ? $lesson['collection_cover'] : '';
            $result[] = $opern;
        }
        return $result;
    }

    /**
     * 格式化搜索结果
     * @param $data
     * @return array
     */
    public static function appSearchLessons($data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $lesson) {
            $opern['id'] = $lesson['lesson_id'];
            $opern['name'] = $lesson['opern_name'];
            $opern['score_id'] = $lesson['opern_id'];
            $opern['res'] = !empty($lesson['resources']) ? $lesson['resources'][0]['url'] : '';
            $opern['is_free'] = $lesson['freeflag'] ? '1' : '0';
            $opern['knowledge'] = $lesson['knowledge'] ? 1 : 0;
            $opern['collection_id'] = $lesson['collection_id'] ? $lesson['collection_id'] : '';
            $opern['collection_name'] = $lesson['collection_name'] ? $lesson['collection_name'] : '';
            $opern['collection_cover'] = $lesson['collection_cover'] ? $lesson['collection_cover'] : '';
            $result[] = $opern;
        }
        return $result;
    }

    /**
     * 默认曲集列表
     * @param $proVer
     * @return array
     */
    public static function getDefaultCollections($proVer)
    {
        $defaultCollectionIds = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'default_collections');
        if (empty($defaultCollectionIds)) {
            return [];
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, $proVer);
        $res = $opn->collectionsByIds($defaultCollectionIds);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $defaultCollections = [];
        } else {
            $defaultCollections = $res["data"];
        }
        $defaultCollections = self::appFormatCollections($defaultCollections);

        // 按照[20, 8, 79]排序
        $order = [20, 8, 79];
        list($_, $ret) = [[], []];
        foreach ($defaultCollections as $c){
            $_[$c['id']] = $c;
        }
        foreach($order as $o){
            array_push($ret, $_[$o]);
        }
        return $ret;
    }

    /**
     * 体验课id
     * @return array
     */
    public static function getTrialLessonId()
    {
        $trialLessonIds = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'trial_lessons');
        if (empty($trialLessonIds)) {
            return [];
        }

        return explode(',', $trialLessonIds);
    }

    /**
     * 体验课
     * @param $proVer
     * @return array
     */
    public static function getTrialLessons($proVer)
    {
        $trialLessonIds = DictConstants::get(DictConstants::APP_CONFIG_TEACHER, 'trial_lessons');
        if (empty($trialLessonIds)) {
            return [];
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, $proVer);
        $res = $opn->lessonsByIds($trialLessonIds);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $trialLessons = [];
        } else {
            $trialLessons = $res["data"];
        }
        $trialLessons = self::appFormatLessonByIds($trialLessons);

        return $trialLessons;
    }

    /**
     * @param $lessonIds
     * @param $prod
     * @param $v
     * @param $publish
     * @param $audit
     * @return array
     */
    public static function getLessonForJoin($lessonIds, $prod, $v, $audit, $publish){
        $opn = new OpernCenter($prod, $v, $audit, $publish);
        $lessonRaw = $opn->lessonsByIds($lessonIds);
        $lessons = [];
        foreach ($lessonRaw['data'] as $lesson){
            $lessons[$lesson['lesson_id']] = $lesson;
        }
        return $lessons;
    }

    /**
     * 为数组中的每个对象添加lesson_name和collection_name字段
     * @param $record_list, 其中必须包含lesson_id
     * @return mixed
     */
    public static function formatLessonAndCollectionName($record_list){
        $lesson_id_list = [];
        // 获取lesson_id
        foreach ($record_list as $value){
            array_push($lesson_id_list, $value["lesson_id"]);
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_STUDENT, OpernCenter::version);
        $res = $opn->lessonsByIds($lesson_id_list);
        if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
            $lesson_list = [];
        } else {
            $lesson_list = $res["data"];
        }
        // 存储lesson_id与lesson_name和collection_name的映射关系
        $lesson_map = [];
        foreach ($lesson_list as $lesson) {
            $lesson_map[$lesson["lesson_id"]] = [
                "lesson_name" => $lesson["opern_name"],
                "collection_name" => $lesson["collection_name"]
            ];
        }
        $length = sizeof($record_list);
        for ($i=0; $i < $length; $i++){
            $lesson_id = $record_list[$i]["lesson_id"];
            $record_list[$i]["lesson_name"] = $lesson_map[$lesson_id]["lesson_name"];
            $record_list[$i]["collection_name"] = $lesson_map[$lesson_id]["collection_name"];
        }
        return $record_list;
    }

}