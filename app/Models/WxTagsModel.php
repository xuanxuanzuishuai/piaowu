<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/06/28
 * Time: 4:07 PM
 */

namespace App\Models;


use App\Services\DictService;
use App\Libs\Util;
use App\Libs\Constants;

class WxTagsModel extends Model
{
    public static $table = 'wx_tags';
    //状态:1有效 0无效
    const STATUS_ABLE = 1;
    const STATUS_DISABLE = 0;
    //标签名称最大的字节数
    const TAG_NAME_MAX_UNICODE_LENGTH = 30;
    //有效标签最大数量
    const ABLE_TAG_MAX_COUNT = 100;
    //标签下粉丝数超过10w，不允许直接删除
    const TAG_DEL_FANS_COUNT_LIMIT = 100000;

    /**
     * 格式化标签数据
     * @param $data
     * @return mixed
     */
    public static function formatData($data)
    {
        $dictMap = DictService::getTypesMap([Constants::DICT_TYPE_TAG_STATUS, Constants::WECHAT_TYPE]);
        foreach ($data as $k => &$v) {
            $v['status_zh'] = $dictMap[Constants::DICT_TYPE_TAG_STATUS][$v['status']]['value'];
            $v['busi_type_zh'] = $dictMap[Constants::WECHAT_TYPE][$v['busi_type']]['value'];
            $v['create_time_format'] = Util::formatTimestamp($v['create_time']);
            $v['update_time_format'] = Util::formatTimestamp($v['update_time']);
            $v['name'] = Util::textDecode($v['name']);
        }
        return $data;
    }
}