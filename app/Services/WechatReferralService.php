<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/2/11
 * Time: 2:32 PM
 */

namespace App\Services;

use App\Libs\MysqlDB;
use App\Models\PosterModel;
use App\Models\UserQrTicketModel;
use App\Libs\Constants;
use App\Libs\AliOSS;
use App\Libs\File;
use App\Models\WeChatOpenIdListModel;

class WechatReferralService
{
    /**
     * 添加转介绍海报
     * @param $insertData
     * @return int|mixed|null|string
     */
    public static function addPoster($insertData)
    {
        //获取类型名称
        $typeName = DictService::getKeyValue(Constants::WECHAT_TYPE, $insertData['apply_type']);
        $insertData['name'] = $typeName;
        $insertData['status'] = PosterModel::STATUS_PUBLISH;
        //写入数据
        $id = PosterModel::insertRecord($insertData);
        //返回结果
        return $id;
    }

    /**
     * 修改转介绍海报
     * @param $id
     * @param $updateData
     * @return int|mixed|null|string
     */
    public static function updatePoster($id, $updateData)
    {
        //写入数据
        $typeName = DictService::getKeyValue(Constants::WECHAT_TYPE, $updateData['apply_type']);
        $updateData['name'] = $typeName;
        $affectRows = PosterModel::updateRecord($id, $updateData);
        //返回结果
        return $affectRows;
    }

    /**
     * 获取海报详情
     * @param $where
     * @param $fields
     * @return int|mixed|null|string
     */
    public static function getPosterDetail($where, $fields = [])
    {
        //写入数据
        $data = PosterModel::getRecord($where, $fields, false);
        if ($data) {
            $data['apply_type_name'] = DictService::getKeyValue(Constants::WECHAT_TYPE, $data['apply_type']);
            $data['oss_url'] = AliOSS::signUrls($data['url']);
        }
        //返回结果
        return $data;
    }

    /**
     * 获取海报列表
     * @param $params
     * @param $page
     * @param $count
     * @return int|mixed|null|string
     */
    public static function getPosterList($params, $page = 1, $count = 20)
    {
        //搜索条件
        $where = $data = [];
        if ($params['poster_type']) {
            $where['poster_type'] = $params['poster_type'];
        }
        if ($params['poster_status']) {
            $where['status'] = $params['poster_status'];
        }
        if ($params['apply_type']) {
            $where['apply_type'] = $params['apply_type'];
        }
        //获取数量
        $db = MysqlDB::getDB();
        $dataCount = $db->count(PosterModel::$table, $where);
        if ($dataCount > 0) {
            //获取数据
            $where['LIMIT'] = [($page - 1) * $count, $count];
            $data = PosterModel::getRecords($where, ['id', 'apply_type', 'url', 'poster_type'], false);
            if ($data) {
                $applyTypeName = DictService::getTypeMap(Constants::WECHAT_TYPE);
                $posterTypeName = DictService::getTypeMap(Constants::POSTER_TYPE);
                foreach ($data as &$val) {
                    $val['apply_type_name'] = $applyTypeName[$val['apply_type']];
                    $val['poster_type_name'] = $posterTypeName[$val['poster_type']];
                    $val['oss_url'] = AliOSS::signUrls($val['url']);
                }
            }
        }

        //返回结果
        return [$dataCount, $data];
    }


    /**
     * 批量修改
     * @param $where
     * @param $updateData
     * @return int|mixed|null|string
     */
    public static function updatePosterWhere($where, $updateData)
    {
        //写入数据
        $affectRows = PosterModel::batchUpdateRecord($updateData, $where, false);
        //返回结果
        return $affectRows;
    }


    /**
     * 删除二维码海报数据
     * @param $posterType
     * @param $applyType
     * @param $delPosterList
     */
    public static function delPosterQrFile($posterType, $applyType, $delPosterList)
    {
        if (empty($delPosterList)) {
            return;
        }
        //海报分类目录
        $posterFirstDir = UserQrTicketModel::$posterDir[$applyType];
        //标准海报
        if ($posterType == PosterModel::POSTER_TYPE_WECHAT_STANDARD) {
            foreach ($delPosterList as $value) {
                $filePath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/" . $posterFirstDir . "/" . md5($value['url']);
                File::delDirFile($filePath);
            }
        }
    }
}
