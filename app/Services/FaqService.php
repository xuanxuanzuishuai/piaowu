<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/26
 * Time: 6:14 PM
 */

namespace App\Services;

use App\Models\FaqModel;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\ElasticsearchDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;


class FaqService
{
    const SEARCH_PAGE_LIMIT = 1000;

    /**
     * 添加话术
     * @param $title
     * @param $desc
     * @param $creatorId
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function addFaq($title, $desc, $creatorId)
    {
        $insertData = [
            'title' => $title,
            'desc' => $desc,
            'status' => FaqModel::FAQ_STATUS_ABLE,
            'create_time' => time(),
            'creator_id' => $creatorId,
        ];
        $id = FaqModel::insertRecord($insertData, false);
        if (empty($id)) {
            throw new RunTimeException(['add_faq_failed']);
        }
        return $id;
    }


    /**
     * 修改话术
     * @param $id
     * @param $title
     * @param $desc
     * @param $status
     * @param $updaterId
     * @return mixed
     * @throws RunTimeException
     */
    public static function modifyFaq($id, $title, $desc, $status, $updaterId)
    {
        $updateData = [
            'title' => $title,
            'desc' => $desc,
            'status' => $status,
            'update_time' => time(),
            'updator_id' => $updaterId,
        ];
        $affectRows = FaqModel::updateRecord($id, $updateData, false);
        if (empty($affectRows)) {
            throw new RunTimeException(['update_faq_failed']);
        }
        return $id;
    }

    /**
     * 话术详情
     * @param $id
     * @return mixed|null
     */
    public static function faqDetail($id)
    {
        //获取信息
        $detail = FaqModel::getById($id);
        return $detail;
    }

    /**
     * 话术列表
     * @param $params
     * @param $page
     * @param $count
     * @return mixed|null
     */
    public static function faqList($params, $page, $count)
    {
        //获取信息
        $data['count'] = 0;
        $data['list'] = [];
        $where = [
            'ORDER' => ['id' => "DESC"],
        ];
        if ($params['title']) {
            $where['title[~]'] = $params['title'];
        }
        if ($params['status']) {
            $where['status'] = $params['status'];
        }
        $dataCount = FaqModel::getCount($where);
        if (empty($dataCount)) {
            return $data;
        }
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $list = FaqModel::getRecords($where, [], false);
        $data['count'] = $dataCount;
        $data['list'] = $list;
        return $data;
    }


    public static function searchFaqByELK($key)
    {
        $es = ElasticsearchDB::getDB();
        $fields = ['title^5', 'desc'];

        $params = [
            'index' => ElasticsearchDB::getIndex(FaqModel::$table),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [0 => [
                            'multi_match' => [
                                'query' => $key,
                                'type' => 'best_fields',
                                'fields' => $fields,
                                "analyzer" => "ik_syno_smart"
                            ]
                        ]
                        ]
                    ],
                ],
                'highlight' => ['fields' => ['title' => (object)[],'desc'=>(object)[]]],
                'size' => self::SEARCH_PAGE_LIMIT
            ]
        ];

        try {
            $ret = $es->search($params);

        } catch (\Exception $e) {
            SimpleLogger::error("ES Search ERROR", [$e->getMessage(), $params,]);
            return [];
        }
        SimpleLogger::debug('ES search result', $ret);
        if (!empty($ret['hits']['hits'])) {
            foreach($ret['hits']['hits'] as $value) {
                $tmp = $value['_source'];
                foreach($value['highlight'] as $k => $v) {
                    foreach($v as $vv) {
                        $rv = str_replace(array("<em>","</em>"), "", $vv);
                        $tmp[$k] = str_replace($rv,$vv,$tmp[$k]);
                    }
                }
                $info[] = $tmp;
            }

        } else {
            $info = [];
        }

        return $info;
    }
}
