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

    const MAXIMUM_LIMIT = 5; //曲谱拍照搜索，最多显示5张曲谱
    const PAGE_LIMIT = 1; //拍照搜曲谱算法那边pageId从0开始，pre环境pageId从1开始，所以比对页码时候，默认把算法那边传过来的pageId加1
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
            $series['mmusic'] = $category['mmusic'] ? '1' : '0';
            $series['mmusicconfig'] = $category['mmusicconfig'] ? '1' : '0';
            $series['dynamic'] = $category['dynamic'] ? '1' : '0';
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
            $book['mmusic'] = $collection['mmusic'] ? '1' : '0';
            $book['mmusicconfig'] = $collection['mmusicconfig'] ? '1' : '0';
            $book['dynamic'] = $collection['dynamic'] ? '1' : '0';
            $book['images'] = $collection['img_list'] ?? [];
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
            $opern['mp4'] = '0';
            $opern['mp8'] = '0';
            $opern['id'] = $lesson['id'];
            $opern['name'] = $lesson['name'];
            $opern['score_id'] = $lesson['opern_id'];
            $opern['res'] = !empty($lesson['resources']) ? $lesson['resources'][0]['resource_url'] : '';
            $opern['is_free'] = $lesson['freeflag'] ? '1' : '0';
            $opern['knowledge'] = $lesson['knowledge'] ? 1 : 0;
            $opern['mmusic'] = $lesson['mmusic'] ? '1' : '0';
            $opern['mmusicconfig'] = $lesson['mmusicconfig'] ? '1' : '0';
            $opern['dynamic'] = $lesson['dynamic'] ? '1' : '0';
            $opern['page'] = $lesson['page'];
            foreach($lesson['resources'] as $value) {
                if($value['type'] == 'mp8') {
                    $opern['mp8'] = '1';
                }
                if($value['type'] == 'mp4') {
                    $opern['mp4'] = '1';
                }
            }
            $result[] = $opern;
        }
        return $result;
    }

    /**
     * 格式化曲谱列表
     * @param $data
     * @param int $limit
     * @return array
     */
    public static function appFormatLessonByIds($data, $limit = 0)
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
            $opern['mmusic'] = $lesson['mmusic'] ? '1' : '0';
            $opern['mmusicconfig'] = $lesson['mmusicconfig'] ? '1' : '0';
            $opern['dynamic'] = $lesson['dynamic'] ? '1' : '0';
            $opern['page'] = $lesson['page'];
            $opern['resources'] = empty($limit) ? $lesson['resources'] : array_slice($lesson['resources'], 0, $limit);

            $result[] = $opern;
        }
        return $result;
    }

    public static function appMusicScoreSearch($data, $images, $limit = self::MAXIMUM_LIMIT)
    {
        $lesson = [];
        if (empty($data)) {
            return $lesson;
        }
        if (count($data) > $limit){
            $data = array_slice($data,$limit);
        }
        $imagesData = array_column($images, 'pageId', 'lessonId');

        foreach ($data as $item) {
            $opern['id'] = $item['lesson_id'];
            $opern['name'] = $item['opern_name'];
            $opern['score_id'] = $item['opern_id'];
            $opern['is_free'] = $item['freeflag'] ? '1' : '0';
            $opern['knowledge'] = $item['knowledge'] ? 1 : 0;
            $opern['collection_id'] = $item['collection_id'] ? $item['collection_id'] : '';
            $opern['collection_name'] = $item['collection_name'] ? $item['collection_name'] : '';
            $opern['collection_cover'] = $item['collection_cover'] ? $item['collection_cover'] : '';
            $opern['mmusic'] = $item['mmusic'] ? '1' : '0';
            $opern['mmusicconfig'] = $item['mmusicconfig'] ? '1' : '0';
            $opern['dynamic'] = $item['dynamic'] ? '1' : '0';
            $opern['page'] = $item['page'];
            $opern['res'] = "";
            $opern['mp4'] = '0';
            $opern['mp8'] = '0';
            foreach ($item['resources'] as $resource) {
                if ($resource['sort'] == ($imagesData[$item['lesson_id']] + self::PAGE_LIMIT)) {
                    $opern['res'] = $resource['resource_url'];
                    break;
                }
            }
            if (!empty($opern['res'])) {
                $lesson[] = $opern;
            }
        }
        return $lesson;
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
            $opern['mp4'] = '0';
            $opern['mp8'] = '0';
            $opern['id'] = $lesson['lesson_id'];
            $opern['name'] = $lesson['opern_name'];
            $opern['score_id'] = $lesson['opern_id'];
            $opern['res'] = !empty($lesson['resources']) ? $lesson['resources'][0]['url'] : '';
            $opern['is_free'] = $lesson['freeflag'] ? '1' : '0';
            $opern['knowledge'] = $lesson['knowledge'] ? 1 : 0;
            $opern['collection_id'] = $lesson['collection_id'] ? $lesson['collection_id'] : '';
            $opern['collection_name'] = $lesson['collection_name'] ? $lesson['collection_name'] : '';
            $opern['collection_cover'] = $lesson['collection_cover'] ? $lesson['collection_cover'] : '';
            $opern['mmusic'] = $lesson['mmusic'] ? '1' : '0';
            $opern['mmusicconfig'] = $lesson['mmusicconfig'] ? '1' : '0';
            $opern['dynamic'] = $lesson['dynamic'] ? '1' : '0';
            $opern['page'] = $lesson['page'];
            foreach($lesson['resources'] as $value ) {
                if($value['type'] == 'mp4') {
                    $opern['mp4'] = '1';
                }
                if($value['type'] == 'mp8') {
                    $opern['mp8'] = '1';
                }
            }
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
     * 根据课程获取曲集
     *
     * @param int|array $lessonId
     * @param $proVer
     * @return array
     */
    public static function getCollectionsByLessonId($lessonId, $proVer)
    {
        if (empty($lessonId)) {
            return [];
        }

        $opn = new OpernCenter(OpernCenter::PRO_ID_AI_TEACHER, $proVer);
        $lessons = $opn->lessonsByIds($lessonId);
        $collectionIds = array_column($lessons['data'], 'collection_id');
        $result = $opn->collectionsByIds($collectionIds);

        if (empty($result) || !empty($result['errors'])) {
            return [];
        }

        return OpernService::appFormatCollections($result['data']);
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