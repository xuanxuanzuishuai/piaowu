<?php
namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\Erp\ErpReferralUserRefereeModel;

class ErpReferralService
{
    /** 转介绍事件 */
    const EVENT_TYPE_UPLOAD_POSTER = 4; // 上传分享海报
    const EVENT_TYPE_UPLOAD_POSTER_RETURN_CASH = 5; // 上传分享海报领取返现
    const EVENT_TYPE_SIGN_IN = 14; // 打卡领取返现

    /**
     * 前端传相应的期望节点
     */
    const EXPECT_REGISTER          = 1; //注册
    const EXPECT_TRAIL_PAY         = 2; //付费体验卡
    const EXPECT_YEAR_PAY          = 3; //付费年卡
    const EXPECT_FIRST_NORMAL      = 4; //首购智能正式课
    const EXPECT_UPLOAD_SCREENSHOT = 5; //上传截图审核通过

    /** 任务状态 */
    const EVENT_TASK_STATUS_COMPLETE = 2;
    const EVENT_TASK_STATUS_UNCOMPLETE = 1;
    /** 奖励状态状态 */
    const AWARD_STATUS_REJECTED = 0; //不发放
    const AWARD_STATUS_WAITING = 1; //待发放
    const AWARD_STATUS_APPROVAL = 2; //审核中
    const AWARD_STATUS_GIVEN = 3; //发放成功
    const AWARD_STATUS_GIVE_ING = 4; //发放中/已发放待领取
    const AWARD_STATUS_GIVE_FAIL = 5; //发放失败


    const AWARD_STATUS = [
        self:: AWARD_STATUS_REJECTED => '不发放',
        self:: AWARD_STATUS_WAITING => '待发放',
        self:: AWARD_STATUS_APPROVAL => '审核中',
        self:: AWARD_STATUS_GIVEN => '发放成功',
        self:: AWARD_STATUS_GIVE_ING => '发放中',
        self:: AWARD_STATUS_GIVE_FAIL => '发放失败'
    ];

    /** 转介绍奖励类型 */
    const AWARD_TYPE_CASH = 1; // 现金
    const AWARD_TYPE_SUBS = 2; // 订阅时长
    const AWARD_TYPE_POINT = 3; //积分
    const AWARD_TYPE_MEDAL = 4; //奖章

    /** 事件任务状态 0 未启用 1 启用 2 禁用 */
    const ERP_EVENT_TASK_STATUS_NOT_ENABLED = 0;
    const ERP_EVENT_TASK_STATUS_ENABLED = 1;
    const ERP_EVENT_TASK_STATUS_DISABLED = 2;

    //专属海报参加人数
    const PERSONAL_POSTER_ATTEND_NUM_KEY = 'personal_poster_attend_num_key';

    //task对应都node名称key
    const TASK_RELATE_NODE_KEY = 'task_relate_node_key';

    /**
     * 获取推荐人信息数据
     * @param array $params
     * @return array|mixed
     * @throws RunTimeException
     */
    public static function refereeList($params = [])
    {
        $ids = $params['uuids'] ?? [];
        if (empty($ids)) {
            return [];
        }
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
            $ids = array_unique(array_filter($ids));
        }
        if (count($ids) > 1000) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        return ErpReferralUserRefereeModel::getRefereeList($ids);
    }
}
