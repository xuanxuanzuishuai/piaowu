<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/06/28
 * Time: 4:07 PM
 */

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\UserWeixinModel;
use App\Models\WxTagsModel;

class WxTagsService
{
    /**
     * 检测标签数据是否满足条件
     * @param $tagName
     * @param $appId
     * @param $busiType
     * @return bool
     * @throws RunTimeException
     */
    public static function checkTagDataValid($tagName, $appId, $busiType)
    {
        //检测标签名称长度是否合格
        $tagNameLengthCheck = Util::checkStringLength($tagName, WxTagsModel::TAG_NAME_MAX_UNICODE_LENGTH);
        if (empty($tagNameLengthCheck)) {
            throw new RunTimeException(['tag_name_length_error']);
        }
        //检测标签数据是否存在
        $tagData = WxTagsModel::getRecord([
            'app_id' => $appId,
            'busi_type' => $busiType,
            'status' => WxTagsModel::STATUS_ABLE,
            'name' => Util::textEncode($tagName),
        ]);
        if (!empty($tagData)) {
            throw new RunTimeException(['tag_have_exist']);

        }
        return true;
    }


    /**
     * 新增标签
     * @param $params
     * @param $employeeId
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function addTag($params, $employeeId)
    {
        //检测同一个应用下的同一个公众号类型下有效标签数量不能大于100个
        $tagCount = WxTagsModel::getCount([
            'app_id' => $params['app_id'],
            'busi_type' => $params['busi_type'],
            'status' => WxTagsModel::STATUS_ABLE,
        ]);
        if ($tagCount >= WxTagsModel::ABLE_TAG_MAX_COUNT) {
            throw new RunTimeException(["tag_max_count_limit"]);
        }
        //检测标签数据是否满足条件
        self::checkTagDataValid($params['tag_name'], $params['app_id'], $params['busi_type']);
        //添加微信标签
        $wTagCreateRes = WeChatService::createTags($params['app_id'], UserWeixinModel::USER_TYPE_STUDENT, $params['tag_name']);
        if (empty($wTagCreateRes['tag'])) {
            throw new RunTimeException(["tag_wei_xin_create_fail"]);
        }
        //添加数据
        $insertData = [
            'name' => Util::textEncode($params['tag_name']),
            'weixin_tag_id' => $wTagCreateRes['tag']['id'],
            'app_id' => $params['app_id'],
            'busi_type' => $params['busi_type'],
            'create_uid' => $employeeId,
            'create_time' => time(),
        ];
        $insertId = WxTagsModel::insertRecord($insertData);
        if (empty($insertId)) {
            throw new RunTimeException(["tag_create_fail"]);
        }
        return $insertId;
    }

    /**
     * 修改标签
     * @param $params
     * @param $employeeId
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function updateTag($params, $employeeId)
    {
        //获取当前标签信息
        $tagData = WxTagsModel::getRecord([
            'id' => $params['tag_id'],
            'status' => WxTagsModel::STATUS_ABLE,
        ]);
        if (empty($tagData)) {
            throw new RunTimeException(["tag_no_exist"]);
        }
        //检测标签数据是否满足条件
        self::checkTagDataValid($params['tag_name'], $tagData['app_id'], $tagData['busi_type']);
        //修改微信标签
        $wTagUpdateRes = WeChatService::updateTags($tagData['app_id'], UserWeixinModel::USER_TYPE_STUDENT, $tagData['weixin_tag_id'], $params['tag_name']);
        if (!empty($wTagUpdateRes['errcode'])) {
            throw new RunTimeException(["tag_wei_xin_update_fail"]);
        }
        //修改数据
        $updateData = [
            'name' => Util::textEncode($params['tag_name']),
            'update_uid' => $employeeId,
            'update_time' => time(),
        ];
        $affectRows = WxTagsModel::updateRecord($params['tag_id'], $updateData);
        if (empty($affectRows)) {
            throw new RunTimeException(["tag_update_fail"]);
        }
        return $affectRows;
    }


    /**
     * 删除标签
     * @param $params
     * @param $employeeId
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function delTag($params, $employeeId)
    {
        //获取当前标签信息
        $tagData = WxTagsModel::getRecord([
            'id' => $params['tag_id'],
            'status' => WxTagsModel::STATUS_ABLE,
        ]);
        if (empty($tagData)) {
            throw new RunTimeException(["tag_no_exist"]);
        }
        //检测标签下粉丝的数量超过10万不能直接删除
        $tagFansList = WeChatService::tagsFansList($tagData['app_id'], UserWeixinModel::USER_TYPE_STUDENT, $tagData['weixin_tag_id']);
        if (!isset($tagFansList['count']) || ($tagFansList['count'] >= WxTagsModel::TAG_DEL_FANS_COUNT_LIMIT)) {
            throw new RunTimeException(["tag_stop_direct_del"]);
        }
        //删除微信标签
        $wTagDelRes = WeChatService::delTags($tagData['app_id'], UserWeixinModel::USER_TYPE_STUDENT, $tagData['weixin_tag_id']);
        if (!empty($wTagDelRes['errcode'])) {
            throw new RunTimeException(["tag_wei_xin_delete_fail"]);
        }
        //修改数据
        $updateData = [
            'status' => WxTagsModel::STATUS_DISABLE,
            'update_uid' => $employeeId,
            'update_time' => time(),
        ];
        $affectRows = WxTagsModel::updateRecord($params['tag_id'], $updateData);
        if (empty($affectRows)) {
            throw new RunTimeException(["tag_delete_fail"]);
        }
        return $affectRows;
    }

    /**
     * 微信标签列表
     * @param $params
     * @return array
     */
    public static function tagList($params)
    {
        //搜索条件
        $where = [
            'status' => WxTagsModel::STATUS_ABLE
        ];
        if ($params['name']) {
            $where['name[~]'] = Util::textEncode($params['name']);
        }
        //获取当前标签信息
        list($totalCount, $list) = WxTagsModel::getPage(
            $where,
            $params['page'],
            $params['count'],
            false,
            ['id', 'name', 'app_id', 'status', 'busi_type', 'create_time', 'update_time']
        );
        if (!empty($list)) {
            $list = WxTagsModel::formatData($list);
        }
        return ["count" => $totalCount, "list" => $list];
    }
}
