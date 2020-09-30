<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/3/21
 * Time: 下午3:47
 */

namespace App\Libs;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Slim\Http\StatusCode;

class OpernCenter
{
    const version = "1.4";
    // 曲谱资源的appId
    const PRO_ID_AI_STUDENT = 1; // 爱学琴
    const PRO_ID_MUSIC_CENTER = 2; // 直营
    const PRO_ID_AI_TEACHER = 4; // 爱练琴
    const PRO_ID_INTERACTION_CLASSROOM = 32; // 互动课堂

    const OPERN_API_CATEGORIES = '/api/opern/categories';
    const OPERN_API_COLLECTIONS = '/api/opern/collections';
    const OPERN_API_LESSONS = '/api/opern/lessons';
    const OPERN_API_COLLECTIONS_BY_ID = '/api/opern/collectionsbyid';
    const OPERN_API_LESSONS_BY_ID = '/api/opern/lessonsbyid';
    const OPERN_API_SEARCH_COLLECTIONS = '/api/opern/search_collection';
    const OPERN_API_SEARCH_LESSONS = '/api/opern/search_opern';
    const OPERN_API_SEARCH_ES_COLLECTIONS = '/api/es_opern/search_collection';
    const OPERN_API_SEARCH_ES_LESSONS = '/api/es_opern/search_opern';
    const OPERN_API_STATIC_RESOURCE = '/api/opern/opernres';

    const OPERN_API_GET_KNOWLEDGE = '/api/knowledge/bylesson';
    const OPERN_API_KNOWLEDGE_CATEGORY = '/api/knowledge/categories';
    const OPERN_API_KNOWLEDGE_BY_CATEGORY = '/api/knowledge/bycategory';
    const OPERN_API_KNOWLEDGE_SEARCH = '/api/knowledge/search';

    const OPERN_API_ENGINE = '/api/opern/engine';
    const OPERN_API_TIMETABLE = '/api/opern/timetable';
    const OPERN_API_OBJECT_TAGS = '/api/opern/object_tags';

    const DEFAULT_PAGE_SIZE = 20;
    const DEFAULT_AUDITING = 0;
    const DEFAULT_PUBLISH = 1;

    const RESOURCE_TYPE = 'png';

    const OPERN_CENTER_ERROR = ['err_no'=>'opern_center_error', 'err_msg'=>'曲谱中心异常'];

    // 新的独立opn服务
    const NEW_SERVICE_API = [
        self::OPERN_API_CATEGORIES,
        self::OPERN_API_COLLECTIONS,
        self::OPERN_API_COLLECTIONS_BY_ID,
        self::OPERN_API_LESSONS,
        self::OPERN_API_LESSONS_BY_ID,
        self::OPERN_API_SEARCH_ES_COLLECTIONS,
        self::OPERN_API_SEARCH_ES_LESSONS,
        self::OPERN_API_STATIC_RESOURCE,
    ];

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

        $serviceHost = DictConstants::get(DictConstants::SERVICE, 'opern_host');

        if (in_array($api, self::NEW_SERVICE_API)) {
            $newServiceHost = DictConstants::get(DictConstants::SERVICE, 'new_opern_host');
            if (!empty($newServiceHost)) {
                $serviceHost = $newServiceHost;
            }
        }

        $fullUrl = $serviceHost . $api;

        try {
            $client = new Client([
                'debug' => false
            ]);

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

        } catch (GuzzleException $e) {
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
            'use_default' => 0,
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
     * @param int|array $collectionIds id或id数组
     * @return array|bool|mixed
     */
    public function collectionsByIds($collectionIds)
    {
        if (is_array($collectionIds)) {
            $collectionIds = implode(",", $collectionIds);
        }

        $result = self::commonAPI(self::OPERN_API_COLLECTIONS_BY_ID, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'collection_ids' => $collectionIds,
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 曲谱课程列表
     * @param $collectionId
     * @param $page
     * @param $pageSize
     * @param $withResources
     * @param $resourceTypes
     * @return array|bool|mixed
     */
    public function lessons($collectionId, $page, $pageSize = self::DEFAULT_PAGE_SIZE, $withResources=1, $resourceTypes='mp4,mp8')
    {
        $result = self::commonAPI(self::OPERN_API_LESSONS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'collection_id' => $collectionId,
            'page' => $page,
            'page_size' => $pageSize,
            'withresources' => $withResources,
            'resource_types' => $resourceTypes
        ]);
        return empty($result) ? [] : $result;
    }

    /**
     * 根据ids获取曲谱列表
     * @param int|array $lessonIds id或id数组
     * @param int $withResources
     * @param string $resourceTypes
     * @param bool $noCdn
     * @return array|bool|mixed
     */
    public function lessonsByIds($lessonIds, $withResources=1, $resourceTypes='dynamic', $noCdn = false)
    {
        if (is_array($lessonIds)) {
            $lessonIds = implode(",", $lessonIds);
        }
        $result = self::commonAPI(self::OPERN_API_LESSONS_BY_ID, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'lesson_ids' => $lessonIds,
            'withresources' => $withResources,
            'resource_types' => $resourceTypes,
            'no_cdn' => $noCdn
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
     * 搜索合集列表
     * @param $keyword
     * @param $searchAuthor
     * @param $pageId
     * @param $pageSize
     * @return array|bool|mixed
     */
    public function searchCollectionsByEs($keyword, $searchAuthor, $pageId, $pageSize = self::DEFAULT_PAGE_SIZE)
    {
        $result = self::commonAPI(self::OPERN_API_SEARCH_ES_COLLECTIONS, [
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
     * @param $withResources
     * @param $resourceTypes
     * @return array|bool|mixed
     */
    public function searchLessons($keyword, $searchAuthor, $withCollection, $page, $pageSize = self::DEFAULT_PAGE_SIZE, $withResources=1, $resourceTypes='mp4,mp8')
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
            'withresources' => $withResources,
            'resource_types' => $resourceTypes
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
     * @param $withResources
     * @param $resourceTypes
     * @return array|bool|mixed
     */
    public function searchLessonsByEs($keyword, $searchAuthor, $withCollection, $page, $pageSize = self::DEFAULT_PAGE_SIZE, $withResources=1, $resourceTypes='mp4,mp8')
    {
        $result = self::commonAPI(self::OPERN_API_SEARCH_ES_LESSONS, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'auditing' => $this->auditing,
            'publish' => $this->publish,
            'keyword' => $keyword,
            'searchauthor' => $searchAuthor,
            'withcollection' => $withCollection,
            'page' => $page,
            'page_size' => $pageSize,
            'withresources' => $withResources,
            'resource_types' => $resourceTypes
        ]);

        return empty($result) ? [] : $result;
    }

    /**
     * 根据$opernIds获取静态资源
     * @param $opernIds
     * @param $types
     * @return array
     */
    public function staticResource($opernIds, $types='png')
    {
        $result = self::commonAPI(self::OPERN_API_STATIC_RESOURCE, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'opern_id' => $opernIds,
            'types' => $types
        ]);
        return empty($result) ? [] : $result;
    }


    public function getKnowledge($lessonId)
    {
        $result = self::commonAPI(self::OPERN_API_GET_KNOWLEDGE, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'lesson_id' => $lessonId
        ]);
        return empty($result) ? [] : $result;
    }

    public function getKnowledgeCategory()
    {
        $result = self::commonAPI(self::OPERN_API_KNOWLEDGE_CATEGORY, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer
        ]);
        return empty($result) ? [] : $result;
    }

    public function getKnowledgeByCategory($categoryId, $page, $pageSize)
    {
        $result = self::commonAPI(self::OPERN_API_KNOWLEDGE_BY_CATEGORY, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'category_id' => $categoryId,
            'page' => $page,
            'page_size' => $pageSize
        ]);
        return empty($result) ? [] : $result;
    }

    public function searchKnowledge($keyword, $type, $page, $pageSize)
    {
        $result = self::commonAPI(self::OPERN_API_KNOWLEDGE_SEARCH, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer,
            'keyword' => $keyword,
            'type' => $type,
            'page' => $page,
            'page_size' => $pageSize
        ]);
        return empty($result) ? [] : $result;
    }

    public function engine()
    {
        $result = self::commonAPI(self::OPERN_API_ENGINE, [
            'pro_id' => $this->proId,
            'pro_ver' => $this->proVer
        ]);
        return empty($result) ? [] : $result;
    }

    /**
     * @param array $collectionIds
     * @return array|bool|mixed
     * 获取指定合集的上课时间表
     */
    public function timetable($collectionIds = [])
    {
        $result = self::commonAPI(self::OPERN_API_TIMETABLE, [
            'collection_id' => $collectionIds,
        ]);
        return empty($result) ? [] : $result;
    }

    /**
     * @param int $type
     * @param array $collectionIds
     * @return array|bool|mixed
     * 获取指定对象的标签
     */
    public function objectTags($type = 1, $collectionIds = [])
    {
        $result = self::commonAPI(self::OPERN_API_OBJECT_TAGS, [
            'object_type' => $type,
            'object_id'   => $collectionIds,
        ]);
        return empty($result) ? [] : $result;
    }
}