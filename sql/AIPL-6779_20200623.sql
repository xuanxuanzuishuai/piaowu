ALTER TABLE `question`
ADD INDEX `catlogdata`(`catalog`, `sub_catalog`, `status`) USING BTREE;