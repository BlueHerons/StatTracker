DELIMITER $$

DROP FUNCTION IF EXISTS `GetStatDiffForAgentBetweenDates` $$

CREATE FUNCTION `GetStatDiffForAgentBetweenDates`(`agent_name` VARCHAR(15), `stat_name` VARCHAR(20), `date_1` DATE, `date_2` DATE) RETURNS int(11)
    READS SQL DATA
BEGIN

RETURN (SELECT MAX(value) - MIN(value) `value` FROM
            (SELECT stat_name `stat`, COALESCE(SUM(value), 0) `value` FROM Data WHERE agent = agent_name and date = date_1 and stat = stat_name
             UNION
             SELECT stat_name `stat`, COALESCE(SUM(value), 0) `value` FROM Data WHERE agent = agent_name and date = date_2 and stat = stat_name) r
        GROUP BY stat);

END $$

DELIMITER ;
