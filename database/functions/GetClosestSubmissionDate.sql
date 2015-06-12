DELIMITER $$

DROP FUNCTION IF EXISTS `GetClosestSubmissionDate` $$

CREATE FUNCTION `GetClosestSubmissionDate`(`agent_name` VARCHAR(15), `target_date` DATE) RETURNS date
    READS SQL DATA
BEGIN

RETURN (SELECT d.date
          FROM Data d
         WHERE d.date <= target_date AND
               d.agent = agent_name
      ORDER BY ABS(DATEDIFF(d.date, target_date)) ASC 
         LIMIT 1);

END $$

DELIMITER ;
