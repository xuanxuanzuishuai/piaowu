<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/3/21
 * Time: 下午3:47
 */

namespace App\Libs;


use App\Models\AppConfigModel;
use GuzzleHttp\Client;
use Slim\Http\StatusCode;

class OpernCenterLib
{

    const APP_AIPL = 1;
    const OPERN_API_COLLECTIONS_BY_IDS_URL = '/api/opern/collectionsbyid';
    const OPERN_API_OPERNS_BY_IDS_URL = '/api/opern/opernsbyid';
    const OPERN_API_SEARCH_COLLECTIONS_URL = '/api/opern/search_collection';
    const OPERN_API_SEARCH_LESSONS_URL = '/api/opern/search_opern';

    /**
     * @param $api
     * @param array $data
     * @param string $method
     * @return bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function commonAPI($api,  $data = [], $method = 'GET')
    {
        try {
            $client = new Client([
                'debug' => false
            ]);

            $erpUrl = AppConfigModel::get(AppConfigModel::OPERN_URL_KEY);
            $fullUrl = $erpUrl . $api;

            if ($method == 'GET') {
                $data = ['query' => $data];
            } elseif ($method == 'POST') {
                $data = ['json' => $data];
            }
            $data['headers'] = ['Content-Type' => 'application/json'];
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $fullUrl, 'data' => $data]);
            $response = $client->request($method, $fullUrl, $data);
            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
            SimpleLogger::info(__FILE__ . ':' . __LINE__, ['api' => $api, 'body' => $body, 'status' => $status]);

            if (StatusCode::HTTP_OK == $status) {
                $res = json_decode($body, true);
                if (!empty($res['code']) && $res['code'] !== Valid::CODE_SUCCESS) {
                    SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($res, true)]);
                    return $res;
                }
                return $res;
            } else {
                SimpleLogger::info(__FILE__ . ':' . __LINE__, [print_r($body, true)]);
                $res = json_decode($body, true);
                return $res;
            }

        } catch (\Exception $e) {
            SimpleLogger::error(__FILE__ . ':' . __LINE__, [print_r($e->getMessage(), true)]);
        }
        return false;
    }

    /**
     * 分类列表
     * @param $page
     * @param $pageSize
     * @param $version
     * @param $auditing
     * @param $publish
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function categoriesList($page, $pageSize, $version, $auditing, $publish)
    {
        $api = AppConfigModel::get(AppConfigModel::OPERN_API_CATEGORIES_KEY);
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'use_default' => 0,
            'page' => $page,
            'page_size' => $pageSize,
            'auditing' => $auditing,
            'publish' => $publish,
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 分类下书籍列表
     * @param $page
     * @param $pageSize
     * @param $version
     * @param $categoryId
     * @param $auditing
     * @param $publish
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collectionList($page, $pageSize, $version, $categoryId, $auditing, $publish)
    {
        $api = AppConfigModel::get(AppConfigModel::OPERN_API_COLLECTIONS_KEY);
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'page' => $page,
            'page_size' => $pageSize,
            'category_id' => $categoryId,
            'auditing' => $auditing,
            'publish' => $publish
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 曲谱课程列表
     * @param $page
     * @param $pageSize
     * @param $version
     * @param $collectionId
     * @param $auditing
     * @param $publish
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function lessonsList($page, $pageSize, $version, $collectionId)
    {
        $api = AppConfigModel::get(AppConfigModel::OPERN_API_LESSONS_KEY);
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'page' => $page,
            'page_size' => $pageSize,
            'collection_id' => $collectionId
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 搜索合集列表
     * @param $pageId
     * @param $pageSize
     * @param $version
     * @param $keyword
     * @param $auditing
     * @param $publish
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchCollection($pageId, $pageSize, $version, $keyword, $auditing, $publish)
    {
        $api = AppConfigModel::get(AppConfigModel::OPERN_API_SEARCH_COLLECTIONS_KEY);
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'page' => $pageId,
            'page_size' => $pageSize,
            'keyword' => $keyword,
            'auditing' => $auditing,
            'publish' => $publish
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 搜索曲谱列表
     * @param $page
     * @param $pageSize
     * @param $version
     * @param $keyword
     * @param $auditing
     * @param $publish
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchOperns($page, $pageSize, $version, $keyword, $auditing, $publish)
    {
        $api = AppConfigModel::get(AppConfigModel::OPERN_API_SEARCH_OPERN_KEY);
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'page' => $page,
            'page_size' => $pageSize,
            'keyword' => $keyword,
            'auditing' => $auditing,
            'publish' => $publish
        ]);

        return empty($result) ? [] : $result;
    }

    /** 根据ids获取课程列表
     * @param $version
     * @param $collection_ids
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function collectionsByIds($version, $collection_ids)
    {
        $api = self::OPERN_API_COLLECTIONS_BY_IDS_URL;
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'collection_ids' => implode(",", $collection_ids)
        ]);

        return empty($result) ? [] : $result;
    }

    /** 根据ids获取曲谱列表
     * @param $version
     * @param $opern_ids
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function opernsByIds($version, $opern_ids)
    {
        $api = self::OPERN_API_OPERNS_BY_IDS_URL;
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'opern_ids' => implode(",", $opern_ids)
        ]);

        return empty($result) ? [] : $result;
    }

    /** 根据关键字查询合集
     * @param $version
     * @param $keyword
     * @param $page
     * @param $limit
     * @param $searchauthor
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchCollections($version, $keyword, $page, $limit, $searchauthor) {
        $api = self::OPERN_API_SEARCH_COLLECTIONS_URL;
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'keyword' => $keyword,
            'page' => $page,
            'page_size' => $limit,
            'searchauthor' => $searchauthor
        ]);

        return empty($result) ? [] : $result;

    }

    /** 模糊查询课程
     * @param $version
     * @param $keyword
     * @param $page
     * @param $limit
     * @param $searchauthor
     * @param $withcollection
     * @return array|bool|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function searchLessons($version, $keyword, $page, $limit, $searchauthor, $withcollection) {
        $api = self::OPERN_API_SEARCH_LESSONS_URL;
        $result = self::commonAPI($api, [
            'pro_id' => self::APP_AIPL,
            'pro_ver' => $version,
            'keyword' => $keyword,
            'page' => $page,
            'page_size' => $limit,
            'searchauthor' => $searchauthor,
            'withcollection' => $withcollection
        ]);

        return empty($result) ? [] : $result;

    }
}
