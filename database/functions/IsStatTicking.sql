DELIMITER $$

DROP FUNCTION IF EXISTS `IsStatTicking` $$

CREATE FUNCTION `IsStatTicking`(`agent_name` VARCHAR(15), `stat_name` VARCHAR(20)) RETURNS tinyint(1)
    READS SQL DATA
BEGIN

RETURN
(SELECT (most_recent.date - previous.date) = (most_recent.value - previous.value)
  FROM (SELECT date, value FROM Data WHERE agent = agent_name AND stat = stat_name ORDER BY date DESC LIMIT 1) most_recent, 
       (SELECT date, value FROM Data WHERE agent = agent_name AND stat = stat_name ORDER BY date DESC LIMIT 1,1) previous);

END $$

DELIMITER ;
