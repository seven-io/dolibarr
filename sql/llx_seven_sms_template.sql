CREATE TABLE IF NOT EXISTS llx_seven_sms_template
(
	id
	int
(
	10
) AUTO_INCREMENT PRIMARY KEY,
	title VARCHAR
(
	255
) NOT NULL,
	message TEXT NOT NULL,
	created_at DATETIME DEFAULT NULL
	) ENGINE=innodb;
