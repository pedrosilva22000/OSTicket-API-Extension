DROP TABLE `%TABLE_PREFIX%api_key_extension`;

DROP TABLE `%TABLE_PREFIX%suspended_ticket`;

DELETE FROM `%TABLE_PREFIX%event` WHERE `name` = 'suspended';

DELETE FROM `%TABLE_PREFIX%ticket_status` WHERE `name` = 'Suspended';