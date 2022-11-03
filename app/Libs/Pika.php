<?php
/**
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Libs;

class Pika
{
    /** @var string action type insert */
    protected const ACTION_TYPE_INSERT = 'insert';

    /** @var string action type update */
    protected const ACTION_TYPE_UPDATE = 'update';

    /** @var string action type delete */
    protected const ACTION_TYPE_DELETE = 'delete';

    protected $rows   = [];
    protected $appId  = 0;
    protected $action = '';

    private function __construct()
    {
        // 不允许new
    }

    private function __clone()
    {
        // 不允许克隆
    }

    /**
     * 解析pika投递过来的参数
     * @param $params
     * @return Pika
     */
    public static function initObj($params): Pika
    {
        $m = new self();
        $m->rows = $params['msg_body'] ?? [];
        $m->appId = intval($params['app_id'] ?? 0);
        $m->action = $params['action'];
        return $m;
    }

    /**
     * 获取数据
     * @return array
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getAppId(): int
    {
        return $this->appId;
    }

    public function isInsert(): bool
    {
        return $this->action == self::ACTION_TYPE_INSERT;
    }

    public function isUpdate(): bool
    {
        return $this->action == self::ACTION_TYPE_UPDATE;
    }

    public function isDelete(): bool
    {
        return $this->action == self::ACTION_TYPE_DELETE;
    }
}