<?php

namespace App\Services\StudentServices;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\StudentMessageReminderModel;

/**
 * 消息提醒逻辑处理
 */
class MessageReminderService
{
    /**
     * 获取消息提醒数据列表
     * @param string $studentUuid
     * @param array $messageTypes
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getMessageReminderData(
        string $studentUuid,
        array $messageTypes,
        int $page,
        int $count
    ): array {
        $data = StudentMessageReminderModel::list($studentUuid, $messageTypes, $page, $count);
        if (empty($data['list'])) {
            return $data;
        }
        //格式化数据
        return self::formatList($data);
    }

    /**
     * 格式化列表数据
     * @param array $data
     * @return array
     */
    private static function formatList(array $data): array
    {
        $dictConfig = DictConstants::getTypesMap([DictConstants::MESSAGE_REMINDER_TYPE['type']]);
        foreach ($data['list'] as &$lv) {
            $lv['type_zh'] = $dictConfig[DictConstants::MESSAGE_REMINDER_TYPE['type']][$lv['type']]['value'];
            $lv['create_time'] = date("Y-m-d H:i:s", $lv['create_time']);
        }
        return $data;
    }

    /**
     * 写入数据
     * @param int $messageType
     * @param array $messageDataArr
     * @return bool
     * @throws RunTimeException
     */
    public static function addMessageData(int $messageType, array $messageDataArr): bool
    {
        switch ($messageType) {
            case StudentMessageReminderModel::MESSAGE_TYPE_GOLD_LEAF_60_EXPIRATION_REMINDER:
                $formatParams = self::formatReminderParams($messageType, $messageDataArr);
                break;
            default:
                throw new RuntimeException(["message_reminder_type_is_error"]);
        }
        if (empty($formatParams)) {
            throw new RuntimeException(["param_lay"]);
        }
        $res = StudentMessageReminderModel::add($formatParams);
        if (empty($res)) {
            throw new RuntimeException(["insert_failure"]);
        }
        return true;
    }

    /**
     * 格式化数据
     * @param int $messageType
     * @param array $messageDataArr
     * @return array
     */
    public static function formatReminderParams(int $messageType, array $messageDataArr): array
    {
        $formatParams = [];
        foreach ($messageDataArr as $item) {
            $formatParams[] = [
                'type'         => $messageType,
                'title'        => $item['title'],
                'content'      => $item['content'],
                'data_id'      => (int)$item['data_id'],
                'read_status'  => StudentMessageReminderModel::STATUS_UNREAD,
                'status'       => Constants::STATUS_TRUE,
                'student_uuid' => $item['student_uuid'],
                'href_url'     => '',
                'create_time'  => time(),
            ];
        }
        return $formatParams;
    }

    /**
     * 获取账户是否存在未读状态的提醒消息
     * @param string $studentUuid
     * @param array $messageTypes
     * @return int
     */
    public static function getUnreadMessageReminderCount(string $studentUuid, array $messageTypes): int
    {
        return StudentMessageReminderModel::getCount([
            'student_uuid' => $studentUuid,
            'type'         => $messageTypes,
            'status'       => Constants::STATUS_TRUE,
            'read_status'  => StudentMessageReminderModel::STATUS_UNREAD,
        ]);
    }

    /**
     * 修改消息已读状态
     * @param string $studentUuid
     * @param array $messageTypes
     * @return bool
     * @throws RunTimeException
     */
    public static function updateMessageReminderReadStatus(string $studentUuid, array $messageTypes): bool
    {
        $unreadDataCount = self::getUnreadMessageReminderCount($studentUuid, $messageTypes);
        if (empty($unreadDataCount)) {
            return true;
        }
        $res = StudentMessageReminderModel::batchUpdateRecord(
            [
                'read_status' => StudentMessageReminderModel::STATUS_READ,
                'read_time'   => time()
            ],
            [
                'student_uuid' => $studentUuid,
                'type'         => $messageTypes
            ]);
        if (empty($res)) {
            throw new RuntimeException(["update_failure"]);
        }
        return true;
    }
}