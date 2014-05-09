CREATE DEFINER=`admin`@`localhost` PROCEDURE `GetAPBreakdown`()
    READS SQL DATA
BEGIN

DROP TABLE IF EXISTS APBreakdown;

CREATE TEMPORARY TABLE APBreakdown
SELECT ap.stat,
       stat.name,
       (FLOOR(q1.value * ap.factor) * ap.ap_gain) ap_gained
  FROM (  SELECT stat,
                 MAX(value) value
            FROM RawStatsForAgent
        GROUP BY stat) q1
        INNER JOIN AP ap ON
              ap.stat = q1.stat
        INNER JOIN Stats stat ON
              ap.stat = stat.stat
  WHERE ap_gain > 0;

SET @totalAP = 0;
SET @remainder = 0;
SELECT MAX(value) FROM RawStatsForAgent WHERE stat = 'ap' INTO @totalAP;
SELECT @totalAP - SUM(ap_gained) FROM APBreakdown INTO @remainder;

INSERT INTO APBreakdown VALUES('', 'Uncalculated', @remainder);

END
