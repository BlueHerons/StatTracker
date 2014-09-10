DELIMITER $$
CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetLeaderboardForStat`(IN `stat_key` VARCHAR(20))
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS LeaderboardForStat;

CREATE TEMPORARY TABLE LeaderboardForStat AS
SELECT @rank := @rank + 1 AS rank,
       agent,
       date,
       value,
       age
  FROM (SELECT q.agent,
               q.date,
               d.value,
               DATEDIFF(NOW(), q.date) `age`
          FROM (  SELECT agent,
                         MAX(date) `date`
                    FROM Data 
                GROUP BY agent) q 
                LEFT JOIN Data d ON  q.agent = d.agent AND 
                                     q.date = d.date 
          WHERE d.stat = stat_key AND
                d.value > 0
       ORDER BY value DESC) q1,
       (SELECT @rank := 0) r
WHERE age <= 30;

END $$
DELIMITER ;
