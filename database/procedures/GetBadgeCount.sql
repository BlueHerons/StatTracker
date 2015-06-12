DROP PROCEDURE IF EXISTS `GetBadgeCount`;

CREATE PROCEDURE `GetBadgeCount`(IN `agent_name` VARCHAR(255), IN `submission_date` DATE)
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS BadgeCount;

CALL GetBadges(agent_name, submission_date);

CREATE TEMPORARY TABLE BadgeCount (`level` VARCHAR(8), `count` TINYINT(2));

INSERT INTO BadgeCount SELECT 'bronze', COUNT(*) FROM _Badges WHERE level >= 2 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'silver', COUNT(*) FROM _Badges WHERE level >= 3 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'gold', COUNT(*) FROM _Badges WHERE level >= 4 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'platinum', COUNT(*) FROM _Badges WHERE level >= 5 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'onyx', COUNT(*) FROM _Badges WHERE level >= 6 and stat != 'ap';

END;
