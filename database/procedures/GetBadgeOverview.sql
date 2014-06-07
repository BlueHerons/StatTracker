CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetBadgeOverview`()
    READS SQL DATA
BEGIN

DECLARE stat VARCHAR(20);
DECLARE done INT DEFAULT false;
DECLARE stats_cursor CURSOR FOR SELECT DISTINCT b.stat FROM Badges b  WHERE b.stat NOT IN ('ap') AND b.level = 'None';
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = true;

DROP TABLE IF EXISTS BadgeOverview;

CREATE TEMPORARY TABLE BadgeOverview (
    stat VARCHAR(20),
    badge VARCHAR(20),
    next VARCHAR(10),
    progress DOUBLE(3,2),
    days_remaining DOUBLE(5,1)
);

OPEN stats_cursor;

stat_loop: LOOP
	FETCH stats_cursor INTO stat;
	IF done THEN LEAVE stat_loop; END IF;

	CALL GetBadgePrediction(stat);
	INSERT INTO BadgeOverview 
		SELECT stat, badge, next, progress, days FROM BadgePrediction;
END LOOP;

CLOSE stats_cursor;

END
