DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetLeaderboardForStat`()
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS LeaderboardForStat;

CREATE TEMPORARY TABLE LeaderboardForStat AS
    SELECT @rank := @rank + 1 AS rank,
           `agent`,
           `value`,
           MIN(`age`) `age`
      FROM (SELECT d.`agent`,
                   d.`value`,
                   DATEDIFF(CURDATE(), CAST(d.`timestamp` AS DATE)) `age`
              FROM Data d 
                   INNER JOIN (SELECT `agent`,
                                      `value`
                                 FROM `Data` d
                           INNER JOIN (SELECT MAX(timestamp) `timestamp`
                                         FROM `Data`
                                     GROUP BY agent) q0
                                 ON d.timestamp = q0.timestamp
                                WHERE stat = stat_name AND
                                      value > 0
                             ORDER BY value DESC) q1
                         ON d.`agent` = q1.`agent` AND
                            d.`value` = q1.`value`) q2,
                   (SELECT @rank := 0) r
     WHERE age <= 30
GROUP BY `agent`
ORDER BY `value` DESC;

END $$
DELIMITER ;
