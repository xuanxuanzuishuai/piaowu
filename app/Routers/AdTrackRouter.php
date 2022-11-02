<?php


namespace App\Routers;

use App\Controllers\API\AdTrack;
use App\Middleware\OrgWebMiddleware;
use App\Middleware\SignMiddleware;

class AdTrackRouter extends RouterBase
{
	protected $logFilename = 'operation_ad_track.log';
	public $middleWares = [OrgWebMiddleware::class, SignMiddleware::class];

	protected $uriConfig = [
		//获取微信公众号sop数据
		'/ad_track/sop'         => ['method' => ['post'], 'call' => AdTrack::class . ':sop'],
		'/ad_track/sop_statics' => ['method' => ['post'], 'call' => AdTrack::class . ':sopStatics'],
	];

}