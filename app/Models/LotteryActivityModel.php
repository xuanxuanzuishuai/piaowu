<?php

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class LotteryActivityModel extends Model
{
    public static $table = 'lottery_activity';
    // 参加用户来源类型 1：规则筛选 2：手动导入
    const USER_SOURCE_FILTER = 1;
    const USER_SOURCE_IMPORT = 2;

    // 中奖限制参与次数限制类型:1自定义2不限
    const TYPE_UNLIMITED = 2;
    const TYPE_CUSTOM = 1;

    /**
     * 增加抽奖活动
     * @param $addParamsData
     * @return bool
     */
    public static function add($addParamsData): bool
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //活动表
        $lotteryId = self::insertRecord($addParamsData['base_data']);
        if (empty($lotteryId)) {
            SimpleLogger::error("lottery add error", []);
            $db->rollBack();
            return false;
        }
        //中奖规则
        $awardRuleRes = true;
        if (!empty($addParamsData['win_prize_rule'])) {
            $awardRuleRes = LotteryAwardRuleModel::batchInsert($addParamsData['win_prize_rule']);
        }
        if (empty($awardRuleRes)) {
            SimpleLogger::error("lottery award rule add error", []);
            $db->rollBack();
            return false;
        }
        //规则筛选可抽奖用户
        $lotteryFilterRes = true;
        if (!empty($addParamsData['lottery_times_rule'])) {
            $lotteryFilterRes = LotteryFilterUserModel::batchInsert($addParamsData['lottery_times_rule']);
        }
        if (empty($lotteryFilterRes)) {
            SimpleLogger::error("lottery filter user rule add error", []);
            $db->rollBack();
            return false;
        }
        //奖品数据
        $awardInfoRes = LotteryAwardInfoModel::batchInsert($addParamsData['awards']);
        if (empty($awardInfoRes)) {
            SimpleLogger::error("lottery award add error", []);
            $db->rollBack();
            return false;
        }
        $lotteryImportRes = true;
        if (!empty($addParamsData['import_user'])) {
            $lotteryImportRes = LotteryImportUserModel::batchInsert($addParamsData['import_user']);
        }
        if (empty($lotteryImportRes)) {
            SimpleLogger::error("lottery import user add error", []);
            $db->rollBack();
            return false;
        }
        $db->commit();
        return true;
    }

    /**
     * 编辑
     * @param $opActivityId
     * @param $updateParamsData
     * @return bool
     */
    public static function update($opActivityId, $updateParamsData): bool
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //基础数据修改
        $updateRows = self::batchUpdateRecord($updateParamsData['base_data'], ['op_activity_id' => $opActivityId]);
        if (empty($updateRows)) {
            SimpleLogger::error("lottery update error", []);
            $db->rollBack();
            return false;
        }
        //删除中奖规则/筛选可抽奖用户奖品数据
        $commonDeleteWhere = ['op_activity_id' => $opActivityId];
        LotteryAwardRuleModel::batchDelete($commonDeleteWhere);
        LotteryFilterUserModel::batchDelete($commonDeleteWhere);
        LotteryAwardInfoModel::batchDelete($commonDeleteWhere);
        LotteryImportUserModel::batchDelete($commonDeleteWhere);
        //中奖规则
        $awardRuleRes = true;
        if (!empty($updateParamsData['win_prize_rule'])) {
            $awardRuleRes = LotteryAwardRuleModel::batchInsert($updateParamsData['win_prize_rule']);
        }
        if (empty($awardRuleRes)) {
            SimpleLogger::error("lottery award rule update error", []);
            $db->rollBack();
            return false;
        }
        //规则筛选可抽奖用户
        $lotteryFilterUserRes = 0;
        if (!empty($updateParamsData['lottery_times_rule'])) {
            $lotteryFilterUserRes = LotteryFilterUserModel::batchInsert($updateParamsData['lottery_times_rule']);
        }
        if (empty($lotteryFilterUserRes)) {
            SimpleLogger::error("lottery filter user rule update error", []);
            $db->rollBack();
            return false;
        }
        //奖品数据
        $awardInfoRes = LotteryAwardInfoModel::batchInsert($updateParamsData['awards']);
        if (empty($awardInfoRes)) {
            SimpleLogger::error("lottery award update error", []);
            $db->rollBack();
            return false;
        }
        //导入名单数据
        $lotteryImportRes = true;
        if (!empty($addParamsData['import_user'])) {
            $lotteryImportRes = LotteryImportUserModel::batchInsert($addParamsData['import_user']);
        }
        if (empty($lotteryImportRes)) {
            SimpleLogger::error("lottery import user update error", []);
            $db->rollBack();
            return false;
        }
        $db->commit();
        return true;
    }
}
