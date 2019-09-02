ALTER TABLE `student`
  ADD COLUMN `first_pay_time` INT NULL DEFAULT 0 COMMENT '首次付费时间' AFTER `act_sub_info`;

-- 更新用户最首次付费时间
UPDATE student AS s
  INNER JOIN
  (SELECT
     buyer, MIN(buy_time) AS btime
   FROM
     gift_code
   WHERE
     bill_id IS NOT NULL
   GROUP BY buyer) gc ON gc.buyer = s.id
SET
  s.first_pay_time = gc.btime;