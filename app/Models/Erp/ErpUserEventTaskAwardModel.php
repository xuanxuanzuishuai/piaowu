<?php
namespace App\Models\Erp;

use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeModel;
use App\Models\WeChatAwardCashDealModel;

class ErpUserEventTaskAwardModel extends ErpModel
{
    public static $table = 'erp_user_event_task_award';

    //用户类型
    const USER_TYPE_STUDENT = 1;

    //奖励发放状态
    const STATUS_DISABLED  = 0; // 不发放
    const STATUS_WAITING   = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE      = 3; // 发放成功
    const STATUS_GIVE_ING  = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    const STATUS_DICT = [
        self::STATUS_DISABLED  => '不发放',
        self::STATUS_WAITING   => '待发放',
        self::STATUS_REVIEWING => '审核中',
        self::STATUS_GIVE      => '发放成功',
        self::STATUS_GIVE_ING  => '发放中',
        self::STATUS_GIVE_FAIL => '发放失败',
    ];

    // 奖励类型
    const AWARD_TYPE_CASH = 1; // 现金
    const AWARD_TYPE_DURATION = 2; // 有效期时长
    const AWARD_TYPE_INTEGRATION = 3; // 积分
    const AWARD_TYPE_MEDAL = 4; //奖章

    /**
     * 当前奖励对应的用户信息
     * @param $awardId
     * @return mixed
     */
    public static function getAwardUserInfoByAwardInfo($awardId)
    {
        return self::dbRO()->get(
            self::$table,
            [
                '[><]' . ErpUserEventTaskModel::$table => ['uet_id' => 'id'],
                '[><]' . ErpStudentModel::$table => ['user_id' => 'id']
            ],
            [
                ErpUserEventTaskModel::$table . '.app_id',
                ErpStudentModel::$table . '.uuid'
            ],
            [
                ErpUserEventTaskAwardModel::$table . '.id' => $awardId
            ]
        );
    }

    /**
     * 需要发放的红包
     * @return array|null
     */
    public static function needSendRedPackAward()
    {
        $time = time() - 1728000; //只处理最近二十天创建的
        $a = ErpUserEventTaskAwardModel::$table;
        $ue = ErpUserEventTaskModel::$table;
        $t = ErpEventTaskModel::$table;
        $e = ErpEventModel::$table;
        $sql = "SELECT a.id,a.`status`,e.type,e.`name`,t.`name` 
FROM {$a} a force index(create_time)
inner join {$ue} ue on a.uet_id = ue.id 
inner join {$t} t on ue.event_task_id = t.id
inner join {$e} e on t.event_id = e.id
WHERE a.create_time >= {$time} AND a.status IN (" . self::STATUS_WAITING . "," . self::STATUS_GIVE_FAIL .") AND a.award_type = " .self::AWARD_TYPE_CASH. " AND (a.create_time + a.delay) <= " . time();
        $baseAward = self::dbRO()->queryAll($sql);
        //如果待发放并且是上传截图领奖，过滤掉
        $queueArr = [];
        if (!empty($baseAward)) {
            foreach ($baseAward as $award) {
                if ($award['type'] == ErpEventModel::DAILY_UPLOAD_POSTER && $award['status'] == self::STATUS_WAITING) {
                    SimpleLogger::info('auto send not can give', ['award' => $award]);
                    continue;
                }
                $queueArr[] = ['id' => $award['id']];
            }
        }
        return $queueArr;
    }

    /**
     * 需要发放的延迟时长奖励
     * @return array
     */
    public static function needSendDurationAward()
    {
        // 16天内的数据
        $time = time() - 1382400;
        $a = ErpUserEventTaskAwardModel::$table;
        $ue = ErpUserEventTaskModel::$table;
        $e = ErpEventModel::$table;
        $t = ErpEventTaskModel::$table;

        $sql = "
        SELECT 
            a.id,
            a.`status`,
            a.`create_time`,
            a.`delay`,
            a.`user_id`,
            e.type
        FROM {$a} a force index(create_time)
        INNER JOIN {$ue} ue on a.uet_id = ue.id 
        inner join {$t} t on ue.event_task_id = t.id
        INNER JOIN {$e} e on t.event_id = e.id
        WHERE 
            a.create_time >= {$time}
            AND a.status IN (" . self::STATUS_WAITING . "," . self::STATUS_GIVE_FAIL .")
            AND a.award_type = " .self::AWARD_TYPE_DURATION. "
            AND a.delay > 0";
        $baseAward = self::dbRO()->queryAll($sql);
        // 如果待发放并且是上传截图领奖，过滤掉
        $queueArr = [];
        if (empty($baseAward)) {
            return $queueArr;
        }
        foreach ($baseAward as $award) {
            if ($award['type'] == ErpEventModel::DAILY_UPLOAD_POSTER
                && $award['status'] == self::STATUS_WAITING) {
                SimpleLogger::info('FILTER POSTER AWARD', ['award' => $award]);
                continue;
            }
            if ($award['create_time'] + $award['delay'] > time()) {
                continue;
            }
            $queueArr[] = ['id' => $award['id'], 'user_id' => $award['user_id']];
        }
        return $queueArr;
    }

    /**
     * 需要更新状态的红包
     * @return array|null
     */
    public static function needUpdateRedPackAward()
    {
        $time = time() - 864000; //只处理最近十天更新的
        return self::dbRO()->queryAll("SELECT id FROM " . self::$table . " WHERE review_time >= " . $time . " AND `status` IN (" . self::STATUS_GIVE_ING .") AND award_type = " . self::AWARD_TYPE_CASH);
    }

    /**
     * 奖励对应的活动信息
     * @param $awardId
     * @return mixed
     */
    public static function awardRelateEvent($awardId)
    {
        return self::dbRO()->get(
            self::$table,
            [
                '[><]' . ErpUserEventTaskModel::$table    => ['uet_id' => 'id'],
                '[><]' . ErpStudentModel::$table          => [ErpUserEventTaskModel::$table . '.user_id' => 'id'],
                '[><]' . ErpStudentModel::$table . ' (r)' => ['user_id' => 'id'],
                '[><]' . ErpEventTaskModel::$table        => [ErpUserEventTaskModel::$table . '.event_task_id' => 'id'],
                '[><]' . ErpEventModel::$table            => [ErpEventTaskModel::$table . '.event_id' => 'id'],
            ],
            [
                ErpStudentModel::$table . '.uuid',
                ErpStudentModel::$table . '.mobile',
                'r.uuid (get_award_uuid)',
                ErpEventModel::$table . '.type',
                ErpUserEventTaskModel::$table . '.app_id',
                ErpUserEventTaskModel::$table . '.event_task_id',
                ErpEventTaskModel::$table . '.condition',
                ErpEventTaskModel::$table . '.type (task_type)',
                self::$table . '.status',
                self::$table . '.award_amount',
                self::$table . '.award_type',
                self::$table . '.create_time',
                self::$table . '.delay',
                self::$table . '.id (award_id)',
            ],
            [
                self::$table . '.id' => $awardId
            ]
        );
    }

    /**
     * 红包列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function getAward($page, $count, $params)
    {
        $where = ' where 1=1 ';
        $map = [];

        if (!empty($params['uuid'])) {
            $where .= " and s.uuid =:uuid ";
            $map[':uuid'] = $params['uuid'];
        }
        if (!empty($params['referrer_name'])) {
            $where .= ' and r.name like :referrer_name ';
            $map[':referrer_name'] = "%{$params['referrer_name']}%";
        }
        if (!empty($params['student_mobile'])) {
            $where .= ' and s.mobile like :student_mobile ';
            $map[':student_mobile'] = "{$params['student_mobile']}%";
        }
        if (!empty($params['student_name'])) {
            $where .= ' and s.name like :student_name ';
            $map[':student_name'] = "%{$params['student_name']}%";
        }
        if (!empty($params['referrer_mobile'])) {
            $where .= ' and r.mobile like :referrer_mobile ';
            $map[':referrer_mobile'] = "{$params['referrer_mobile']}%";
        }
        if (!empty($params['event_task_id'])) {
            $where .= " and u.event_task_id in ('" . implode("','", $params['event_task_id']) . "') ";
        }
        if (!Util::emptyExceptZero($params['award_status'])) {
            $where .= ' and a.status = :award_status ';
            $map[':award_status'] = $params['award_status'];
        }
        if (!empty($params['reviewer_id'])) {
            $where .= ' and a.reviewer_id = :reviewer_id ';
            $map[':reviewer_id'] = $params['reviewer_id'];
        }
        if (!empty($params['s_review_time'])) {
            $where .= ' and a.review_time >= :s_review_time ';
            $map[':s_review_time'] = is_numeric($params['s_review_time']) ? $params['s_review_time'] : strtotime($params['s_review_time']);
        }
        if (!empty($params['e_review_time'])) {
            $where .= ' and a.review_time <= :e_review_time ';
            $map[':e_review_time'] = is_numeric($params['e_review_time']) ? $params['e_review_time'] : strtotime($params['e_review_time']);
        }
        if (!empty($params['s_create_time'])) {
            $where .= ' and a.create_time >= :s_create_time ';
            $map[':s_create_time'] = is_numeric($params['s_create_time']) ? $params['s_create_time'] : strtotime($params['s_create_time']);
        }
        if (!empty($params['e_create_time'])) {
            $where .= ' and a.create_time <= :e_create_time ';
            $map[':e_create_time'] = is_numeric($params['e_create_time']) ? $params['e_create_time'] : strtotime($params['e_create_time']);
        }
        if (!empty($params['app_id'])) {
            $where .= ' and u.app_id = :app_id ';
            $map[':app_id'] = $params['app_id'];
        }
        if (!empty($params['award_type'])) {
            $where .= ' and a.award_type = :award_type ';
            $map[':award_type'] = $params['award_type'];
        }

        $a = ErpUserEventTaskAwardModel::getTableNameWithDb();
        $u = ErpUserEventTaskModel::getTableNameWithDb();
        $t = ErpEventTaskModel::getTableNameWithDb();
        $s = ErpStudentModel::getTableNameWithDb();
        $e = EmployeeModel::getTableNameWithDb();
        $wa = WeChatAwardCashDealModel::getTableNameWithDb();

        $joinCondition = "
           INNER JOIN {$a} a ON u.id = a.uet_id and u.user_id = a.user_id
           INNER JOIN {$t} t ON t.id = u.event_task_id
           INNER JOIN {$s} s ON s.id = a.user_id
           LEFT JOIN {$e} e ON e.id = a.reviewer_id
           LEFT JOIN {$wa} wa ON wa.user_event_task_award_id = a.id
        ";
        $sql = "
        SELECT 
           s.id     student_id,
           s.uuid   student_uuid,
           s.mobile referee_mobile,
           a.status award_status,
           s.name   student_name,
           s.mobile student_mobile,
           u.event_task_id,
           t.name   event_task_name,
           t.type   event_task_type,
           t.award,
           a.id user_event_task_award_id,
           a.award_amount,
           a.award_type,
           a.review_time,
           a.reviewer_id,
           a.reason,
           a.delay,
           a.create_time,
           e.name review_name,
           wa.result_code
        FROM {$u} u
        {$joinCondition}
        {$where}";

        $order = 'order by a.create_time desc';
        $limit = Util::limitation($page, $count);

        $db = self::dbRO();

        $records = $db->queryAll("{$sql} {$order} {$limit}", $map);

        $total = $db->queryAll("select count(*) count from ({$sql}) sa", $map);

        return [$records, $total[0]['count']];
    }

    /**
     * 获取用户完成任务的总奖励数值
     * @param $uuid
     * @param array $taskId
     * @return array|int|null
     */
    public static function getUserTotal($uuid, $taskId = [])
    {
        $studentInfo = ErpStudentModel::getRecord(['uuid' => $uuid]);
        if (empty($studentInfo)) {
            return 0;
        }
        $db = self::dbRO();
        $a = ErpUserEventTaskAwardModel::getTableNameWithDb();
        $u = ErpUserEventTaskModel::getTableNameWithDb();
        $where = " a.user_id = " . $studentInfo['id'];
        $where .= " AND a.award_type = " . self::AWARD_TYPE_CASH;
        $where .= " AND a.status = " . self::STATUS_GIVE;

        if (!empty($taskId)) {
            $where .= " AND u.event_task_id in (".(is_array($taskId) ? implode(',', $taskId) : $taskId).") ";
        }
        $sql = "
        SELECT 
            sum(a.award_amount) as totalAmount 
        FROM
            {$u} u
        INNER join {$a} a on a.uet_id = u.id and a.user_id != u.user_id
        WHERE
         {$where}
        ";
        return $db->queryAll($sql);
    }

    /**
     * 通用红包审核 - 转介绍二期奖励列表
     * 注： 注意和 self::getAward 方法的区别
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function awardRedPackList($params, $page, $count)
    {
        $returnList = ['records' => [], 'total_count' => 0];
        $where = ' where 1=1 ';
        $map = [];
        $dssStudentJoin = '';
        $userAwardTable = self::getTableNameWithDb();
        $userEventTable = ErpUserEventTaskModel::getTableNameWithDb();
        $eventTable = ErpEventTaskModel::getTableNameWithDb();
        $studentTable = ErpStudentModel::getTableNameWithDb();
        $dssStudentTable = DssStudentModel::getTableNameWithDb();

        if (!empty($params['student_uuid'])) {
            $where .= " and s.uuid in ('" . implode("','", $params['student_uuid']) . "') ";
        }
        if (!empty($params['referrer_name'])) {
            $where .= ' and r.name like :referrer_name ';
            $map[':referrer_name'] = "%{$params['referrer_name']}%";
        }
        if (!empty($params['student_mobile'])) {
            $where .= ' and s.mobile like :student_mobile ';
            $map[':student_mobile'] = "{$params['student_mobile']}%";
        }
        if (!empty($params['referrer_mobile'])) {
            $where .= ' and r.mobile like :referrer_mobile ';
            $map[':referrer_mobile'] = "{$params['referrer_mobile']}%";
        }
        if (!empty($params['event_task_id'])) {
            if (is_array($params['event_task_id'])) {
                $where .= " and t.id in ('" . implode("','", $params['event_task_id']) . "') ";
            } else {
                $where .= " and t.id =" . intval($params['event_task_id']) . ") ";
            }
        }
        if (isset($params['award_status'])) {
            $where .= ' and a.status = :award_status ';
            $map[':award_status'] = $params['award_status'];
        }
        if (isset($params['reviewer_id'])) {
            $where .= ' and a.reviewer_id = :reviewer_id ';
            $map[':reviewer_id'] = $params['reviewer_id'];
        }
        if (!empty($params['s_review_time'])) {
            $where .= ' and a.review_time >= :s_review_time ';
            $map[':s_review_time'] = $params['s_review_time'];
        }
        if (!empty($params['e_review_time'])) {
            $where .= ' and a.review_time <= :e_review_time ';
            $map[':e_review_time'] = $params['e_review_time'];
        }
        if (!empty($params['s_create_time'])) {
            $where .= ' and a.create_time >= :s_create_time ';
            $map[':s_create_time'] = $params['s_create_time'];
        }
        if (!empty($params['e_create_time'])) {
            $where .= ' and a.create_time <= :e_create_time ';
            $map[':e_create_time'] = $params['e_create_time'];
        }
        if (!empty($params['app_id'])) {
            $where .= ' and u.app_id = :app_id ';
            $map[':app_id'] = $params['app_id'];
        }
        if (!empty($params['award_type'])) {
            $where .= ' and a.award_type = :award_type ';
            $map[':award_type'] = $params['award_type'];
        }
        if (!empty($params['not_award_status'])) {
            $where  .= " and a.status not in ('" . implode("','", $params['not_award_status']) . "')";
        }
        if (!empty($params['assistant_ids'])) {
            array_walk($params['assistant_ids'], function (&$id) {
                $id = intval($id);
            });
            $where  .= " and dss_s.assistant_id in (" . implode(",", $params['assistant_ids']) . ")";
            $dssStudentJoin = " inner join {$dssStudentTable} dss_s on dss_s.uuid=s.uuid";
        }

        $y = self::USER_TYPE_STUDENT;
        $joinCondition = "
           inner join {$userEventTable} u on u.id = a.uet_id and u.user_id = a.user_id and u.user_type = {$y}
           inner join {$eventTable} t on t.id = u.event_task_id
           inner join {$studentTable} s on s.id = a.user_id 
        " . $dssStudentJoin;
        $sql = "select 
           s.id     student_id,
           s.uuid   student_uuid,
           s.mobile referee_mobile,
           a.status award_status,
           s.name   student_name,
           s.mobile student_mobile,
           u.event_task_id,
           t.name   event_task_name,
           t.type   event_task_type,
           t.award,
           a.id user_event_task_award_id,
           a.award_amount,
           a.award_type,
           a.review_time,
           a.reviewer_id,
           a.reason,
           a.delay,
           a.create_time
        from {$userAwardTable} a
        {$joinCondition}
        {$where}";

        $limit = Util::limitation($page, $count);

        $db = self::dbRO();
        $totalCount = $db->queryAll("select count(*) count from ({$sql}) sa", $map);
        $returnList['total_count'] = $totalCount[0]['count'] ?? 0;
        if ($returnList['total_count'] <= 0) {
            return $returnList;
        }
        $records = $db->queryAll("{$sql} order by a.create_time desc {$limit}", $map);
        $returnList['records'] = is_array($records) ? $records : [];
        return $returnList;
    }

}