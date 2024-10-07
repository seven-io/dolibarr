CREATE TABLE IF NOT EXISTS llx_seven_voice_call_logs
(
	ID
	int
(
	10
) AUTO_INCREMENT PRIMARY KEY,
	sender VARCHAR
(
	20
) NOT NULL,
	model_id int
(
	10
),
	dolibarr_class VARCHAR
(
	255
) NOT NULL,
	recipient TEXT NOT NULL,
	status SMALLINT NOT NULL DEFAULT 1,
	date DATETIME DEFAULT NULL
	) ENGINE=innodb;
