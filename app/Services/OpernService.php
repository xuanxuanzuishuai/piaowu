<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/19
 * Time: 1:21 PM
 */

namespace App\Services;

use App\Libs\Constants;

class OpernService
{
    /**
     * 格式化曲谱列表
     * @param $data
     * @param string $copyrightCode
     * @return array
     */
    public static function appFormatLessons($data, $copyrightCode = '')
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
            $opern['lesson_name'] = $lesson['lesson_name'];
            $opern['collection_name'] = $lesson['collection_name'];
            $opern['fingering_correct'] = $lesson['fingering_correct'];
            $opern['resources'] = [];
            foreach ($lesson['resources'] as $value) {
                if ($value['type'] == 'mp8') {
                    $opern['mp8'] = '1';
                }
                if ($value['type'] == 'mp4') {
                    $opern['mp4'] = '1';
                }
                $opern['resources'][$value['type']][] = $value;
            }
            if (empty($opern['resources'])) {
                $opern['resources'] = (object)[];
            }
            $opern['copyright_is_show'] = self::controlCopyright($copyrightCode, $lesson['copyright_list']); // 版权展示处理
            $result[] = $opern;
        }
        return $result;
    }

    /**
     * 格式化曲谱列表
     * @param $data
     * @param int $limit
     * @param string $copyrightCode 当前需要获取的曲谱版权
     * @return array
     */
    public static function appFormatLessonByIds($data, $limit = 0, $copyrightCode = '')
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
            $opern['collection_cover_portrait'] = $lesson['collection_cover_portrait'] ? $lesson['collection_cover_portrait'] : '';
            $opern['mmusic'] = $lesson['mmusic'] ? '1' : '0';
            $opern['mmusicconfig'] = $lesson['mmusicconfig'] ? '1' : '0';
            $opern['dynamic'] = $lesson['dynamic'] ? '1' : '0';
            $opern['page'] = $lesson['page'];
            $opern['fingering_correct'] = $lesson['fingering_correct'];
            $opern['resources'] = empty($limit) ? $lesson['resources'] : array_slice($lesson['resources'], 0, $limit);

            // 版权展示处理
            $opern['copyright_is_show'] = self::controlCopyright($copyrightCode, $lesson['copyright_list']);

            $result[] = $opern;
        }
        return $result;
    }

    /**
     * 版权展示处理
     * @param string $copyrightCode
     * @param $copyrightList
     * @return int
     */
    public static function controlCopyright(string $copyrightCode, $copyrightList)
    {
        $show = 1;
        if (!empty($copyrightCode) && !empty($copyrightList) && is_array($copyrightList)) {
            $copyright = array_column($copyrightList, 'is_show', 'code');
            $isShow = $copyright[$copyrightCode] ?? -1;
            if ($isShow == -1 && $copyrightCode === Constants::DICT_COPYRIGHT_CODE_CN_GAT) {
                $isShow = $copyright[Constants::DICT_COPYRIGHT_CODE_CN] ?? -1;
            }
            $show = $isShow != -1 ? (int) $isShow : 1;
        }
        return $show;
    }
}
