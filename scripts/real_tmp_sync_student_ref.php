<?php
/**
 * 手动统计历史真人转介绍关系
 * author: qingfeng.lian
 * date: 2022/8/30
 */

namespace App;

use App\Libs\MysqlDB;
use App\Models\Erp\ErpReferralUserRefereeLogModel;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealStudentReferralInfoModel;
use Dotenv\Dotenv;

date_default_timezone_set('PRC');
// 不限时
set_time_limit(0);
// 设置内存
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$refTable = ErpReferralUserRefereeModel::getTableNameWithDb();
$refLogTable = ErpReferralUserRefereeLogModel::getTableNameWithDb();
$stuTable = ErpStudentModel::getTableNameWithDb();

// 读取所有数据
$sql = "select r.id,r.referee_id,count(1) as total,s.uuid,group_concat(CONCAT_WS('-',r.id,l.id)) as ur_ids from $refTable r" .
    " left join $stuTable s on s.id=r.referee_id".
    " left join $refLogTable l on l.ur_id=r.id and l.action=4".
    " group by r.referee_id";


$list = MysqlDB::getDB()->queryAll($sql);
$count = count($list);
echo "本次轮询需要处理 " . $count . " 条" . PHP_EOL;

$chunkList = array_chunk($list, 150);
foreach ($chunkList as $_list) {
    $batchData = [];
    foreach ($_list as $item) {
        if (empty($item['uuid'])) {
            echo "处理失败----------uuid找不到 " . $item['referee_id'] . PHP_EOL;
            continue;
        }
        $batchData[$item['uuid']] = [
            'student_uuid'            => $item['uuid'],
            'referral_num'            => $item['total'],
            'now_referral_trail_num'  => $item['total'],
            'now_referral_normal_num' => 0,
        ];
        // $batchData[$item['uuid']]['ur_ids'] = $item['ur_ids'];
        // 查询
        if (!empty($item['ur_ids'])) {
            $parseUr = explode(',',$item['ur_ids']);
            foreach ($parseUr as $_ur) {
                if (stripos($_ur,'-') !== false) {
                    $batchData[$item['uuid']]['now_referral_normal_num'] += 1;
                    $batchData[$item['uuid']]['now_referral_trail_num'] -= 1;
                }
            }
            unset($_ur);
        }
    }
    unset($item);
    RealStudentReferralInfoModel::batchInsert(array_values($batchData));
}
unset($_list);

// 验证sql
/*
select count(*) from (
select r.id,r.referee_id,count(1) as total,s.uuid,group_concat(CONCAT_WS('-',r.id,l.id)) as ur_ids from referral_user_referee r
left join erp_student s on s.id=r.referee_id
left join referral_user_referee_log l on l.ur_id=r.id and l.action=4
group by r.referee_id
) t where uuid is not null


-- 找不到uuid的验证sql
select * from erp_dev.referral_user_referee where referee_id=63391;
select * from erp_dev.referral_user_referee_log where ur_id = 3256;
select * from erp_dev.erp_student where id=63391;
 */
