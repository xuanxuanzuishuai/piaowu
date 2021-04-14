<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/14
 * Time: 18:56
 */

namespace App\Models\Dss;

use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;

class DssSharePosterModel extends DssModel
{
    public static $table = 'share_poster';

	//海报类型：1上传截图领奖 2上传截图领返现
	const TYPE_UPLOAD_IMG = 1;
	const TYPE_RETURN_CASH = 2;
}