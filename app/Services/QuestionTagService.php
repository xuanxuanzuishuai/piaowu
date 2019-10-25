<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/23
 * Time: 下午12:19
 */

namespace App\Services;

use App\Models\QuestionTagModel;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;

class QuestionTagService
{
    public static function tags($key)
    {
        return QuestionTagModel::tags($key);
    }

    public static function delTagCacheKey()
    {
        QuestionTagModel::delTagCacheKey();
    }

    public static function addEdit($id, $tag, $employeeId)
    {
        if(empty($id)) {
            $lastId = QuestionTagModel::insertRecord([
                'tag'         => $tag,
                'employee_id' => $employeeId,
                'status'      => Constants::STATUS_TRUE,
                'create_time' => time(),
            ], false);

            if(empty($lastId)) {
                throw new RunTimeException(['save_fail']);
            }
            QuestionTagService::delTagCacheKey();
            return ['id' => $lastId, 'tag' => $tag];
        } else {
            $affectedRows = QuestionTagModel::updateRecord($id, [
                'tag'         => $tag,
                'employee_id' => $employeeId,
                'update_time' => time(),
            ], false);

            if(empty($affectedRows)) {
                throw new RunTimeException(['update_fail']);
            }
            QuestionTagService::delTagCacheKey();
            return ['id' => $id, 'tag' => $tag];
        }
    }
}