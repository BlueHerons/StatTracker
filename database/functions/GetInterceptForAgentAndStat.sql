DROP FUNCTION IF EXISTS `GetInterceptForAgentAndStat`;

CREATE FUNCTION `GetInterceptForAgentAndStat`(`agent_name` VARCHAR(15), `stat_key` VARCHAR(20)) RETURNS int(10)
    READS SQL DATA
BEGIN

RETURN GetInterceptForAgentAndStatBetweenDates(agent_name,
                                               stat_key,
                                               DATE_SUB(NOW(), INTERVAL 30 DAY),
                                               NOW());

END;
