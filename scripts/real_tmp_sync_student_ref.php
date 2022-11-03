<?php
/**
 * 手动统计历史真人转介绍关系
 * author: qingfeng.lian
 * date: 2022/8/30
 * 使用示例：
 * 全量计算：php real_tmp_sync_student_ref.php
 * 分批计算：php real_tmp_sync_student_ref.php limit 0(最小的主键id,不包含)
 * 指定用户：php real_tmp_sync_student_ref.php user 10（需要重新计算的用户id）
 */

namespace App;

use App\Libs\MysqlDB;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Models\Erp\ErpStudentAttributeModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\RealStudentReferralInfoModel;
use App\Services\SyncTableData\TraitService\RealStatisticsStudentReferralService;
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

$db = MysqlDB::getDB();
$refTable = ErpReferralUserRefereeModel::getTableNameWithDb();
$stuTable = ErpStudentModel::getTableNameWithDb();
$stuAttrTable = ErpStudentAttributeModel::getTableNameWithDb();

$countSql = 'select count(*) as total from (select referee_id from ' . $refTable . ' group by referee_id) t';
$count = $db->queryAll($countSql);
$total = $count[0]['total'];
echo "本次轮询需要处理 " . $total . " 条" . PHP_EOL;

$firstCmd = $argv[1] ?? '';
$twoCmd = $argv[2] ?? '';

$sql = 'select r.referee_id,s.uuid as referee_uuid from ' . $refTable . ' as r' .
    ' left join ' . $stuTable . ' as s on s.id=r.referee_id';
if (!empty($firstCmd)) {
    switch ($firstCmd) {
        case "limit":
            $sql .= " where id> $twoCmd";
            break;
        case "user":
            $sql .= " where r.referee_id=$twoCmd";
    }
}
$sql .= ' group by r.referee_id';
if (!empty($firstCmd) && $firstCmd == "limit") {
    $sql .= " limit 0,5000";
}

$refList = $db->queryAll($sql);
foreach ($refList as $k => $item) {
    if (empty($item['referee_uuid'])) {
        continue;
    }
    $_insertData = RealStatisticsStudentReferralService::computeStudentReferralStatisticsInfo(['uuid' => $item['referee_uuid'], 'id' => $item['referee_id']]);
    RealStudentReferralInfoModel::insertRecord($_insertData);
    echo "处理进度 $k/$total 条" . PHP_EOL;
}
unset($item);

