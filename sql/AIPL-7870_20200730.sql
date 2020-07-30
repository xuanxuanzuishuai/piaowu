UPDATE dict
SET key_value = '音符兑换商品'
WHERE
	type = 'student_account_log_op_type'
	AND key_code = 2001;