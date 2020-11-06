<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;

use App\Libs\Util;
use App\Models\StudentFavoriteModel;
use App\Libs\Exceptions\RunTimeException;

class StudentFavoriteService
{
    //最大收藏数量
    const  MAX_FAVORITE = 500;

    /**
     * @param $opn
     * @param $studentId
     * @return array
     * 获取收藏首页数据
     */
    public static function firstPageList($opn, $studentId)
    {
        if (empty($studentId)) {
            return [];
        }
        $getObjectIds = StudentFavoriteModel::getFavoriteIds($studentId);

        if (!empty($getObjectIds['lessonIds'])) {
            $lessons = self::getOpnInfoByIds(StudentFavoriteModel::FAVORITE_TYPE_LESSON, $opn, $getObjectIds['lessonIds']);
        }

        if (!empty($getObjectIds['collectionIds'])) {
            $collections = self::getOpnInfoByIds(StudentFavoriteModel::FAVORITE_TYPE_COLLECTION, $opn, $getObjectIds['collectionIds']);
        }

        return [
            'lessons'      => $lessons ?? [],
            'collections' => $collections ?? [],
        ];
    }

    /**
     * @param $type
     * @param $opn
     * @param $ids
     * @return array
     * 根据一组曲谱或教材ID获取并整合数据
     */
    public static function getOpnInfoByIds($type, $opn, $ids)
    {
        if ($type == StudentFavoriteModel::FAVORITE_TYPE_LESSON) {
            $lessonListResult = $opn->lessonsByIds($ids);

            if (isset($lessonListResult['data']) && !empty($lessonListResult['data'])) {
                foreach ($lessonListResult['data'] as $key => $value) {
                    $lessons[$key] = [
                        'mp4'             => '0',
                        'mp8'             => '0',
                        'id'              => $value['lesson_id'],
                        'lesson_name'     => $value['lesson_name'],
                        'collection_name' => $value['collection_name'],
                        'score_id'        => $value['opern_id'],
                        'is_free'         => $value['freeflag'] ? '1' : '0',
                        'knowledge'       => $value['knowledge'] ? 1 : 0,
                        'mmusic'          => $value['mmusic'] ? '1' : '0',
                        'mmusicconfig'    => $value['mmusicconfig'] ? '1' : '0',
                        'dynamic'         => $value['dynamic'] ? '1' : '0',
                        'page'            => $value['page'],
                    ];
                    foreach($value['resources'] as $v) {
                        if($v['type'] == 'mp8') {
                            $lessons[$key]['mp8'] = '1';
                        }elseif ($v['type'] == 'mp4'){
                            $lessons[$key]['mp4'] = '1';
                        }
                    }
                }
            }
            return $lessons ?? [];
        } elseif ($type == StudentFavoriteModel::FAVORITE_TYPE_COLLECTION) {
            $collectionListResult = $opn->collectionsByIds($ids);
            if (isset($collectionListResult['data']) && !empty($collectionListResult['data'])) {
                foreach ($collectionListResult['data'] as $value) {
                    $collections[] = [
                        'id'      => $value['id'],
                        'name'    => $value['name'],
                        'cover'   => $value['cover'] ?? '',
                        'is_free' => $value['freeflag'] ? '1' : '0',
                    ];
                }
            }
            return $collections ?? [];
        }
        return [];
    }

    /**
     * @param $params
     * @return array|int|mixed|string|null
     * @throws RunTimeException
     * 收藏曲谱或教材
     */
    public static function addFavoriteObject($params)
    {
        $where = [
            'student_id' => $params['student_id'],
            'type'       => $params['type'],
            'object_id'  => $params['object_id'],
        ];
        $isExist = StudentFavoriteModel::getRecord($where, ['id'], false);
        if (!empty($isExist)) {
            return self::updateFavoriteStatus(StudentFavoriteModel::FAVORITE_SUCCESS, $params);
        }

        //检查收藏数量是否超限
        unset($where['object_id']);
        $favoriteCount = StudentFavoriteModel::getCount($where);
        if ($favoriteCount > self::MAX_FAVORITE) {
            throw new RunTimeException(['over_max_allow_num']);
        }

        $time = time();
        $insertData = [
            'student_id'  => $params['student_id'],
            'type'        => $params['type'],
            'object_id'   => $params['object_id'],
            'status'      => StudentFavoriteModel::FAVORITE_SUCCESS,
            'update_time' => $time,
            'create_time' => $time,
        ];

        return StudentFavoriteModel::insertRecord($insertData, false);
    }

    /**
     * @param $operateType
     * @param $params
     * @return array|int|null
     * @throws RunTimeException
     * 更新指定曲谱或教材或状态
     */
    public static function updateFavoriteStatus($operateType, $params)
    {
        if ($operateType != StudentFavoriteModel::FAVORITE_SUCCESS && $operateType != StudentFavoriteModel::FAVORITE_CANCEL) {
            return [];
        }

        $updateData = [
            'status'      => $operateType,
            'update_time' => time(),
        ];
        $where = [
            'student_id' => $params['student_id'],
            'type'       => $params['type'],
            'object_id'  => $params['object_id'],
        ];
        $num = StudentFavoriteModel::batchUpdateRecord($updateData, $where, false);
        if (empty($num)) {
            throw new RunTimeException(['record_not_found']);
        }
        return $num;
    }

    /**
     * @param $opn
     * @param $params
     * @return array
     * 分页查询曲谱列表
     */
    public static function getFavoritesLesson($opn, $params)
    {
        $where = [
            'student_id' => $params['student_id'],
            'type'       => StudentFavoriteModel::FAVORITE_TYPE_LESSON,
            'status'     => StudentFavoriteModel::FAVORITE_SUCCESS,
        ];
        $recordCount = StudentFavoriteModel::getCount($where);
        if (empty($recordCount)) {
            return [];
        }

        $params['count'] = 10;
        list($page, $limit) = Util::formatPageCount($params);
        $where['ORDER'] = ['update_time' => 'DESC'];
        $where['LIMIT'] = [($page - 1) * $limit, $limit];

        $lessonIdsResult = StudentFavoriteModel::getRecords($where, ['object_id'], false);
        if (empty($lessonIdsResult)) {
            return [];
        }
        foreach ($lessonIdsResult as $value) {
            $lessonIds[] = $value['object_id'];
        }

        $lessons = self::getOpnInfoByIds(StudentFavoriteModel::FAVORITE_TYPE_LESSON, $opn, $lessonIds ?? []);

        return [
            'list'        => $lessons ?? [],
            'total_count' => $recordCount ?? [],
        ];
    }

    /**
     * @param $opn
     * @param $params
     * @return array
     * 分页查询教材列表
     */
    public static function getFavoritesCollection($opn, $params)
    {
        $where = [
            'student_id' => $params['student_id'],
            'type'       => StudentFavoriteModel::FAVORITE_TYPE_COLLECTION,
            'status'     => StudentFavoriteModel::FAVORITE_SUCCESS,
        ];
        $recordCount = StudentFavoriteModel::getCount($where);
        if (empty($recordCount)) {
            return [];
        }
        $params['count'] = 12;
        list($page, $limit) = Util::formatPageCount($params);
        $where['ORDER'] = ['update_time' => 'DESC'];
        $where['LIMIT'] = [($page - 1) * $limit, $limit];

        $lessonIdsResult = StudentFavoriteModel::getRecords($where, ['object_id'], false);
        if (empty($lessonIdsResult)) {
            return [];
        }
        foreach ($lessonIdsResult as $value) {
            $lessonIds[] = $value['object_id'];
        }

        $collections = self::getOpnInfoByIds(StudentFavoriteModel::FAVORITE_TYPE_COLLECTION, $opn, $lessonIds ?? []);

        return [
            'list'        => $collections ?? [],
            'total_count' => $recordCount ?? [],
        ];
    }

    /**
     * @param $params
     * @return string[]
     * 获取曲谱收藏状态
     */
    public static function lessonFavoriteStatus($params)
    {
        $where = [
            'student_id' => $params['student_id'],
            'type'       => $params['type'],
            "object_id"  => $params['object_id'],
        ];

        $result = StudentFavoriteModel::getRecord($where, ['status'], false);

        return [
            'status' => (int)$result['status'] ?? StudentFavoriteModel::FAVORITE_NOT,
        ];

    }
}