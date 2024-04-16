DROP TABLE `%TABLE_PREFIX%api_key_nova`;

DELETE FROM `%TABLE_PREFIX%event` WHERE `name` = 'suspended';

DELETE FROM `%TABLE_PREFIX%ticket_status` WHERE `name` = 'Suspended';