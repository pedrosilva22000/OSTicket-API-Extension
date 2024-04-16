
INSERT into `%TABLE_PREFIX%event` (`name`) VALUES ('suspended');

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%api_key_nova` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `isactive` TINYINT(1) NOT NULL,
    `id_staff` VARCHAR(255) NOT NULL,
    `apikey` VARCHAR(255) NOT NULL,
    `can_create_tickets` TINYINT(1) NOT NULL,
    `can_close_tickets` TINYINT(1) NOT NULL,
    `can_reopen_tickets` TINYINT(1) NOT NULL,
    `can_edit_tickets` TINYINT(1) NOT NULL,
    `can_suspend_tickets` TINYINT(1) NOT NULL,
    `notes` TEXT,
    `updated` DATETIME,
    `created` DATETIME
) ENGINE=$engine CHARSET=utf8;



INSERT INTO `%TABLE_PREFIX%ticket_status` (`name`, `state`, `mode`, `flags`, `sort`, `properties`, `created`, `updated`)
VALUES ('Suspended', 'open', 3, 0, 6, '{"description":"Tickets are still open but time isnt counting"}', NOW(), NOW());
