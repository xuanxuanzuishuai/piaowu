<?php

/**
 * 临时脚本 - 只能执行一次
 * 生成10.18-10.31内的5次活动
 */

namespace App;
use AlibabaCloud\Cloudwf\V20170328\ShopInfo;
use App\Controllers\OrgWeb\Employee;
use App\Models\AreaCityModel;
use App\Models\AreaDistrictModel;
use App\Models\AreaProvinceModel;
use App\Models\EmployeeModel;
use App\Models\RegionProvinceRelationModel;
use App\Models\ShopInfoModel;
use Dotenv\Dotenv;

// 1小时超时
set_time_limit(3600);

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
echo md5('123');


