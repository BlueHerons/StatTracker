CREATE DEFINER=`SRStats`@`localhost` PROCEDURE `GetBadgeCount`()
    READS SQL DATA
BEGIN

CALL GetCurrentBadges();

DROP TABLE IF EXISTS BadgeCount;

CREATE TEMPORARY TABLE BadgeCount (`level` VARCHAR(8), `count` TINYINT(2));
INSERT INTO BadgeCount SELECT 'bronze', COUNT(*) FROM CurrentBadges WHERE level >= 2 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'silver', COUNT(*) FROM CurrentBadges WHERE level >= 3 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'gold', COUNT(*) FROM CurrentBadges WHERE level >= 4 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'platinum', COUNT(*) FROM CurrentBadges WHERE level >= 5 and stat != 'ap';
INSERT INTO BadgeCount SELECT 'onyx', COUNT(*) FROM CurrentBadges WHERE level >= 6 and stat != 'ap';

END
