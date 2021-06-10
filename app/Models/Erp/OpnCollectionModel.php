<?php

namespace App\Models\Erp;


class OpnCollectionModel extends ErpModel
{
    public static $table = 'opn_collection';
    //  `type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '套课类型， 1：可报名；2：待上线；3：制作中',
    const TYPE_SIGN_UP = 1;
    const TYPE_READY_ONLINE = 2;
    const TYPE_IN_PRODUCTION = 3;
}
