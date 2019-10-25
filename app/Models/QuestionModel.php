<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/22
 * Time: 下午6:11
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

class QuestionModel extends Model
{
    public static $table = 'question';

    public static function selectByPage($page, $count, $params)
    {
        $where = ' where 1=1 ';
        $map = [];

        if(!empty($params['exam_org'])) {
            $where .= ' and q.exam_org = :exam_org ';
            $map[':exam_org'] = $params['exam_org'];
        }
        if(!empty($params['level'])) {
            $where .= ' and q.level = :level ';
            $map[':level'] = $params['level'];
        }
        if(!empty($params['catalog'])) {
            $where .= ' and q.catalog = :catalog ';
            $map[':catalog'] = $params['catalog'];
        }
        if(!empty($params['sub_catalog'])) {
            $where .= ' and q.sub_catalog = :sub_catalog ';
            $map[':sub_catalog'] = $params['sub_catalog'];
        }
        if(!empty($params['template'])) {
            $where .= ' and q.template = :template ';
            $map[':template'] = $params['template'];
        }
        if(!empty($params['content_text'])) {
            $where .= ' and q.content_text like :content_text ';
            $map[':content_text'] = "%{$params['content_text']}%";
        }
        if(!empty($params['opern'])) {
            $where .= ' and q.opern like :opern ';
            $map[':opern'] = "%{$params['opern']}%";
        }
        if(!empty($params['question_tag_id'])) {
            $where .= ' and tr.question_tag_id = :question_tag_id ';
            $map[':question_tag_id'] = $params['question_tag_id'];
        }
        if(isset($params['status'])) {
            $where .= ' and q.status = :status ';
            $map[':status'] = $params['status'];
        }

        $q = QuestionModel::$table;
        $t = QuestionTagModel::$table;
        $tr = QuestionTagRelationModel::$table;
        $e = EmployeeModel::$table;
        $s = Constants::STATUS_TRUE;

        $countSql = "select count(*) count from (select q.id
from {$q} q
       left join {$tr} tr on q.id = tr.question_id and tr.status = {$s}
left join {$t} t on t.id = tr.question_tag_id {$where} group by q.id) ss";

        $db = MysqlDB::getDB();

        $total = $db->queryAll($countSql, $map);
        if($total[0]['count'] == 0) {
            return [[], 0];
        }

        $limit = Util::limitation($page, $count);

        $sql = "select group_concat(t.tag order by t.create_time asc) question_tag, e.name employee_name, q.*
from {$q} q
       left join {$tr} tr on q.id = tr.question_id and tr.status = {$s}
left join {$t} t on t.id = tr.question_tag_id inner join {$e} e on e.id = q.employee_id 
{$where} group by q.id order by q.create_time desc, q.status asc {$limit}";

        $records = $db->queryAll($sql, $map);

        if(!empty($params['question_tag_id'])) {
            $in = implode(',', array_column($records, 'id'));

            $sql = "select group_concat(t.tag order by t.create_time asc) question_tag, e.name employee_name, q.*
from {$q} q
       left join {$tr} tr on q.id = tr.question_id and tr.status = {$s}
left join {$t} t on t.id = tr.question_tag_id inner join {$e} e on e.id = q.employee_id 
where q.id in ($in)
group by q.id order by q.create_time desc, q.status asc";

            $records = $db->queryAll($sql);
        }

        return [$records, $total[0]['count']];
    }

    public static function getByQuestionId($id)
    {
        $q = QuestionModel::$table;
        $tr = QuestionTagRelationModel::$table;
        $s = Constants::STATUS_TRUE;

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select group_concat(tr.question_tag_id) question_tag, q.*
        from {$q} q
        left join {$tr} tr on q.id = tr.question_id and tr.status = {$s} where q.id = :id", [':id' => $id]);

        return empty($records) ? [] : $records[0];
    }
}