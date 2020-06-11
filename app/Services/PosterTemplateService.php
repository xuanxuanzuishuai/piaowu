<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/6/10
 * Time: 下午7:27
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Models\TemplatePosterModel;
use App\Models\TemplatePosterWordModel;
use App\Libs\Util;

class PosterTemplateService
{
    /**
     * @param $params
     * @param $operateId
     * @return bool
     * @throws RunTimeException
     * 添加数据
     */
    public static function addData($params, $operateId)
    {
        $time = time();
        $data = [
            'poster_name' => $params['poster_name'],
            'poster_url' => $params['poster_url'],
            'poster_status' => $params['poster_status'],
            'order_num' => $params['order_num'],
            'type' => $params['type'],
            'operate_id' => $operateId,
            'create_time' => $time,
            'update_time' => $time
        ];
        $res = TemplatePosterModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('template poster add data fail', $data);
            throw new RunTimeException(['template_poster_add_data_fail']);
        }
        return true;
    }

    /**
     * @param $params
     * @return array
     * 处理模板图数据
     */
    public static function getList($params)
    {
        list($res, $pageId, $pageLimit, $totalCount) = TemplatePosterModel::getList($params);
        $data = [];
        if (!empty($res)) {
            foreach ($res as $k => $value) {
                $row = self::formatPosterInfo($value);
                $row['display_order_num'] = ($pageId - 1) * $pageLimit + $k + 1;
                $data[] = $row;
            }
        }
        return [$data, $pageId, $pageLimit, $totalCount];
    }

    /**
     * @param $row
     * @return array
     * 格式化某一条的信息
     */
    private static function formatPosterInfo($row)
    {
        return [
            'poster_id' => $row['id'],
            'poster_name' => $row['poster_name'],
            'poster_url' => AliOSS::signUrls($row['poster_url']),
            'poster_status' => $row['poster_status'],
            'poster_status_zh' => DictConstants::get(DictConstants::TEMPLATE_POSTER_CONFIG, $row['poster_status']),
            'update_time' => date('Y-m-d H:i', $row['update_time']),
            'operator_name' => $row['operator_name'] ?? ''
        ];
    }

    /**
     * @param $posterId
     * @return array
     * 某条海报模板图信息
     */
    public static function getOnePosterInfo($posterId)
    {
        $info = TemplatePosterModel::getRecord(['id' => $posterId], []);
        return self::formatPosterInfo($info);
    }

    /**
     * @param $params
     * @param $operateId
     * @return mixed
     * 更新某条海报的信息
     */
    public static function editData($params, $operateId)
    {
        $needUpdate['update_time'] = time();
        $needUpdate['operate_id'] = $operateId;
        isset($params['poster_name']) && $needUpdate['poster_name'] = $params['poster_name'];
        isset($params['poster_url']) && $needUpdate['poster_url'] = $params['poster_url'];
        isset($params['poster_status']) && $needUpdate['poster_status'] = $params['poster_status'];
        isset($params['order_num']) && $needUpdate['order_num'] = $params['order_num'];
        TemplatePosterModel::updateRecord($params['poster_id'], $needUpdate);
        return $needUpdate;
    }

    /**
     * @param $params
     * @param $operateId
     * @return bool
     * @throws RunTimeException
     * 海报模板图文案添加
     */
    public static function addWordData($params, $operateId)
    {
        $time = time();
        $data = [
            'content' => Util::textEncode($params['content']),
            'status'  => $params['status'],
            'create_time' => $time,
            'update_time' => $time,
            'operate_id' => $operateId
        ];
        $res = TemplatePosterWordModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('template poster word add data fail', $data);
            throw new RunTimeException(['template_poster_word_add_data_fail']);
        }
        return true;
    }

    /**
     * @param $params
     * @return array
     * 海报模板图文案列表
     */
    public static function getWordList($params)
    {
        list($res, $pageId, $pageLimit, $totalCount) = TemplatePosterWordModel::getList($params);
        $data = [];
        if (!empty($res)) {
            foreach ($res as $k => $value) {
                $data[] = self::formatWordInfo($value);
            }
        }
        return [$data, $pageId, $pageLimit, $totalCount];
    }

    /**
     * @param $row
     * @return array
     * 格式化模板图文案信息
     */
    private static function formatWordInfo($row)
    {
        return [
            'poster_word_id' => $row['id'],
            'content'        => Util::textDecode($row['content']),
            'status' => $row['status'],
            'status_zh' => DictConstants::get(DictConstants::TEMPLATE_POSTER_CONFIG, $row['status']),
            'update_time' => date('Y-m-d H:i', $row['update_time']),
            'operator_name' => $row['operator_name'] ?? ''
        ];
    }

    /**
     * @param $posterWordId
     * @return array
     * 获取某条文案信息
     */
    public static function getOnePosterWordInfo($posterWordId)
    {
        $info = TemplatePosterWordModel::getRecord(['id' => $posterWordId], []);
        return self::formatWordInfo($info);
    }

    /**
     * @param $params
     * @param $operateId
     * @return mixed
     * 更新海报文案信息
     */
    public static function editWordData($params, $operateId)
    {
        $needUpdate['update_time'] = time();
        $needUpdate['operate_id'] = $operateId;
        isset($params['content']) && $needUpdate['content'] = Util::textEncode($params['content']);
        isset($params['status']) && $needUpdate['status'] = $params['status'];
        TemplatePosterWordModel::updateRecord($params['poster_word_id'], $needUpdate);
        return $needUpdate;
    }
}