CREATE TABLE IF NOT EXISTS llx_seven_sms_reminder
(
	id
	int
(
	10
) AUTO_INCREMENT PRIMARY KEY,
	setting_uuid VARCHAR
(
	255
) NOT NULL,
	object_id int
(
	20
) NOT NULL,
	object_type VARCHAR
(
	255
) NOT NULL,
	reminder_datetime DATETIME NOT NULL,
	update_key VARCHAR
(
	255
) DEFAULT NULL,
	retry int
(
	5
) DEFAULT 0,
	created_at DATETIME DEFAULT NULL,
	updated_at DATETIME DEFAULT NULL
	) ENGINE=innodb;
