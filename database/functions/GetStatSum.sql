DELIMITER $$

DROP FUNCTION IF EXISTS `GetStatSum` $$

CREATE FUNCTION `GetStatSum`(`stat_name` VARCHAR(20)) RETURNS bigint(21) unsigned
    READS SQL DATA
BEGIN

RETURN (SELECT
    sum(value) `value`
  FROM 
    (SELECT 
         agent, max(value) `value` 
       FROM 
         Data
       WHERE
         stat = stat_name AND
         date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY agent) q);

END $$

DELIMITER ;
