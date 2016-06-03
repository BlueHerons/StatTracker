DELIMITER $$

DROP PROCEDURE IF EXISTS `GetDistributionForRatio` $$

CREATE PROCEDURE `GetDistributionForRatio`(IN `stat_1` VARCHAR(20), IN `stat_2` VARCHAR(20), IN `factor` DOUBLE(5,2))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS `DistributionForRatio`;

CREATE TEMPORARY TABLE `DistributionForRatio`
SELECT `step`, 
       count(*) `count`
  FROM (SELECT q1.agent,
               FLOOR(s1 / s2),
               FLOOR((s1 / s2) / factor) * factor `step` 
          FROM (SELECT agent,
                       MAX(date) `date`,
                       value 's1' 
                  FROM `Data`
                 WHERE stat = stat_1 AND
                       value > 0
                 GROUP BY agent 
                HAVING MAX(date) 
                 ORDER BY agent) q1
          JOIN (SELECT agent,
                       MAX(date) `date`,
                       value 's2' 
                  FROM `Data` 
                 WHERE stat = stat_2 AND
                       value > 0
                 GROUP BY agent 
                HAVING MAX(date) 
                ORDER BY agent) q2
            ON q1.agent = q2.agent
         ORDER BY `step` asc) q3
 WHERE `step` IS NOT NULL
 GROUP BY `step`;

END $$

DELIMITER ;
