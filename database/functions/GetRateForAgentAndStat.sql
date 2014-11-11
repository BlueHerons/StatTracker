DELIMITER $$

DROP FUNCTION IF EXISTS `GetRateForAgentAndStat` $$

CREATE DEFINER=`admin`@`localhost` FUNCTION `GetRateForAgentAndStat`(`agent_name` VARCHAR(20), `stat_key` VARCHAR(20)) RETURNS double(10,2)
    READS SQL DATA
BEGIN

RETURN GetRateForAgentAndStatBetweenDates(agent_name,
                                          stat_key,
                                          DATE_SUB(NOW(), INTERVAL 30 DAY),
                                          NOW());

END $$

DELIMITER ;
