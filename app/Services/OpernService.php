<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/19
 * Time: 1:21 PM
 */

namespace App\Services;


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
            $series['cover'] = $category['cover'];
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
            $book['cover'] = $collection['cover'];
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
            $opern['name'] = $lesson['opern_name'];
            $opern['score_id'] = $lesson['opern_id'];
            $opern['res'] = !empty($lesson['resources']) ? $lesson['resources'][0]['url'] : '';
            $opern['is_free'] = $lesson['freeflag'] ? '1' : '0';
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
            $result[] = $opern;
        }
        return $result;
    }
}