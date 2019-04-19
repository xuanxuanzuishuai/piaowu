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

class OpernCenter
{
    // 曲谱资源的appId
    const PRO_ID_AI_STUDENT = 1; // 爱学琴
    const PRO_ID_MUSIC_CENTER = 2; // 直营
    const PRO_ID_AI_TEACHER = 4; // 爱练琴

    const OPERN_API_CATEGORIES = '/api/opern/categories';
    const OPERN_API_COLLECTIONS = '/api/opern/collections';
    const OPERN_API_LESSONS = '/api/opern/lessons';
    const OPERN_API_COLLECTIONS_BY_IDS = '/api/opern/collectionsbyid';
    const OPERN_API_LESSONS_BY_IDS = '/api/opern/lessonsbyid';
    const OPERN_API_SEARCH_COLLECTIONS = '/api/opern/search_collection';
    const OPERN_API_SEARCH_LESSONS = '/api/opern/search_opern';

    const DEFAULT_PAGE_SIZE = 20;
    const DEFAULT_AUDITING = 0;
    const DEFAULT_PUBLISH = 1;

    public $proId; // 曲谱库ProId
    public $proVer; // 曲谱库ProVer
    public $auditing; // 是否只获取审核可见资源 默认false
    public $publish; // 是否只获取已发布资源 默认true

    public function __construct($proId, $proVer, $auditing = self::DEFAULT_AUDITING, $publish = self::DEFAULT_PUBLISH)
    {
        $this->proId = $proId;
        $this->proVer = $proVer;
        $this->auditing = $auditing;
        $this->publish = $publish;
    }

    /**
     * @param $api
     * @param array $data
     * @param string $method
     * @return bool|mixed
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
     * @return array
     */
    public function categories($page, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $result = self::commonAPI(self::OPERN_API_CATEGORIES, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'page' => $page,
            'page_size' => $pageSize,
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 分类下书籍列表
     * @param $categoryId
     * @param $page
     * @param $pageSize
     * @return array|bool|mixed
     */
    public function collections($categoryId, $page, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $result = self::commonAPI(self::OPERN_API_COLLECTIONS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'page' => $page,
            'page_size' => $pageSize,
            'category_id' => $categoryId
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 根据ids获取课程列表
     * @param $collection_ids
     * @return array|bool|mixed
     */
    public function collectionsByIds($collection_ids)
    {
        $result = self::commonAPI(self::OPERN_API_COLLECTIONS_BY_IDS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'collection_ids' => implode(",", $collection_ids),
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 曲谱课程列表
     * @param $collectionId
     * @param $page
     * @param $pageSize
     * @return array|bool|mixed
     */
    public function lessons($collectionId, $page, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $result = self::commonAPI(self::OPERN_API_LESSONS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'collection_id' => $collectionId,
            'page' => $page,
            'page_size' => $pageSize,
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 根据ids获取曲谱列表
     * @param $lesson_ids
     * @return array|bool|mixed
     */
    public function lessonsByIds($lesson_ids)
    {
        $result = self::commonAPI(self::OPERN_API_LESSONS_BY_IDS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'lesson_ids' => implode(",", $lesson_ids),
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 搜索合集列表
     * @param $keyword
     * @param $searchAuthor
     * @param $pageId
     * @param $pageSize
     * @return array|bool|mixed
     */
    public function searchCollections($keyword, $searchAuthor, $pageId, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $result = self::commonAPI(self::OPERN_API_SEARCH_COLLECTIONS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'keyword' => $keyword,
            'searchauthor' => $searchAuthor,
            'page' => $pageId,
            'page_size' => $pageSize
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 搜索曲谱列表
     * @param $keyword
     * @param $searchAuthor
     * @param $withCollection
     * @param $page
     * @param $pageSize
     * @return array|bool|mixed
     */
    public function searchLessons($keyword, $searchAuthor, $withCollection, $page, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $result = self::commonAPI(self::OPERN_API_SEARCH_LESSONS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'keyword' => $keyword,
            'searchauthor' => $searchAuthor,
            'withcollection' => $withCollection,
            'page' => $page,
            'page_size' => $pageSize,
        ]);

        return empty($result) ? [] : $result;
    }
}