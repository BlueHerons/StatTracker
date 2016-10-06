DELIMITER $$

DROP FUNCTION IF EXISTS `GetActiveAgentCount` $$

CREATE FUNCTION `GetActiveAgentCount`() RETURNS int(11)
    READS SQL DATA
BEGIN

RETURN (SELECT count(*) 
         FROM (SELECT agent, date 
                 FROM Data 
                GROUP BY agent 
               HAVING max(date)) q 
        WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY));

END $$

DELIMITER ;
