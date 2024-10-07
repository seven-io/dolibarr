<?php

require_once DOL_DOCUMENT_ROOT . "/core/lib/date.lib.php";
require_once DOL_DOCUMENT_ROOT . "/societe/class/societe.class.php";
dol_include_once("/seven/core/helpers/helpers.php");
dol_include_once("/seven/core/helpers/EventLogger.php");
dol_include_once("/seven/core/class/seven_logger.class.php");
dol_include_once("/seven/core/controllers/settings/invoice.setting.php");
dol_include_once("/seven/core/controllers/settings/sms_setting.php");

class SendSMSReminderJob {

	private $log;
	private $sms_reminder_db;

	function __construct() {
		global $db;
		$this->log = new Seven_Logger;
		$this->sms_reminder_db = new SevenSMSReminderDatabase($db);
	}

	/*
		Returns 0 if OK, < 0 if KO
	*/
	public function handle() {
		global $db;
		$this->log->add("Seven", "Processing SMS Reminder Queue");

		$unique_key = generateUniqueKey();
		$update_sql = "UPDATE {$this->sms_reminder_db->table_name}";
		$update_sql .= sprintf(' SET `update_key` = "%s"', $unique_key);
		$update_sql .= ' WHERE DATE(`reminder_datetime`) = CURDATE() ';
		$update_sql .= ' AND (`update_key` IS NULL OR `update_key` = "")';
		$update_sql .= ' LIMIT 20';
		$this->sms_reminder_db->update($update_sql);

		$results = $this->sms_reminder_db->get($unique_key);

		$sms_invoice_setting_model = new SMS_Invoice_Setting($db);
		$sms_invoice_setting = $sms_invoice_setting_model->get_settings();

		$sms_setting_model = new SMS_Setting($db);
		$sms_setting = $sms_setting_model->get_settings();

		if ($sms_invoice_setting->enable != "on") {
			$this->log->add("Seven", "SMS notification for invoice module is disabled. No SMS reminders will be sent.");
			$this->sms_reminder_db->resetUpdateKey($unique_key);
			return;
		}

		$today = new DateTime("now", new DateTimeZone(getServerTimeZoneString()));
		$seven_active_hour_start = DateTime::createFromFormat("H:ia", $sms_setting['SEVEN_ACTIVE_HOUR_START']);
		$seven_active_hour_end = DateTime::createFromFormat("H:ia", $sms_setting['SEVEN_ACTIVE_HOUR_END']);

		foreach ($results as $reminder_obj) {
			$id = $reminder_obj['id'];
			$setting_uuid = $reminder_obj['setting_uuid'];
			$object_id = $reminder_obj['object_id'];
			$object_type = $reminder_obj['object_type'];
			$reminder_datetime = $reminder_obj['reminder_datetime'];
			$retry = $reminder_obj['retry'];

			$scheduled_dt = new DateTime($reminder_datetime, new DateTimeZone("UTC"));
			$scheduled_dt->setTimezone(new DateTimeZone(getServerTimeZoneString()));

			// if the reminder date is today
			// check for current hour based on SERVER TZ
			try {
				if ($scheduled_dt->getTimestamp() >= $seven_active_hour_start->getTimestamp() && $scheduled_dt->getTimestamp() <= $seven_active_hour_end->getTimestamp()) {
					$this->log->add("Seven", "Scheduled DT in Active hours");
					// check if current time is in active hour
					if ($today->getTimestamp() >= $seven_active_hour_start->getTimestamp() && $today->getTimestamp() <= $seven_active_hour_end->getTimestamp()) {
						$this->log->add("Seven", "Current Time in Active hours");
						$seven_from = $sms_invoice_setting->send_from;

						$invoice_obj = new $object_type($db);
						$invoice_obj->fetch($object_id);

						$thirdparty = new Societe($db);
						$thirdparty->fetch($invoice_obj->socid);
						$seven_to = validated_mobile_number($thirdparty->phone, $thirdparty->country_code);

						$message = '';
						foreach ($sms_invoice_setting->reminder_settings as $reminder_setting) {
							if ($setting_uuid === $reminder_setting->uuid) {
								$sms_template = $reminder_setting->message;
								$message = $sms_invoice_setting_model->replace_keywords_with_value($invoice_obj, $sms_template);
							}
						}

						if (empty($message)) {
							$this->log->add("Seven", "Setting UUID: {$setting_uuid} was not found in sms reminder setting.");
							$this->log->add("Seven", "Deleting UUID from sms reminder");
							$delete_query = "DELETE FROM {$this->sms_reminder_db->table_name}";
							$delete_query .= sprintf('WHERE `setting_uuid` = "%s"', $setting_uuid);
							$this->sms_reminder_db->db->query($delete_query, 0, "ddl");
							$this->log->add("Seven", "Successfully deleted UUID from sms reminder");
							continue;
						}

						$resp = seven_send_sms($seven_from, $seven_to, $message, "SMS Reminder");
						if ($resp['messages'][0]['status'] == 0) {
							EventLogger::create($invoice_obj->socid, "contact", "SMS sent to contact");
						} else {
							$err_msg = $resp['messages'][0]['err_msg'];
							EventLogger::create($invoice_obj->socid, "contact", "SMS failed to send to contact", "SMS failed due to: {$err_msg}");
						}

						$delete_sql = "DELETE FROM {$this->sms_reminder_db->table_name}";
						$delete_sql .= sprintf(" WHERE `id` = %d", $id);
						$this->sms_reminder_db->delete($delete_sql, 0, "ddl");
					} else {
						$this->log->add("Seven", "Current time not in active hours");
						$this->sms_reminder_db->resetUpdateKeyById($id);
					}
				} else {
					// change: Set schedule dt to active_hour_start.
					$this->log->add("Seven", "Scheduled time is not in ACTIVE HOUR");
					$new_scheduled_dt = clone $scheduled_dt;
					$new_scheduled_dt->setTime($seven_active_hour_start->format("H"), 0);
					$this->log->add("Seven", "new Schedule DT in server TZ");
					$this->log->add("Seven", $new_scheduled_dt->format("Y-m-d H:i:s e"));
					$new_scheduled_dt->setTimezone(new DateTimeZone("UTC"));
					$update_sql = "UPDATE {$this->sms_reminder_db->table_name}";
					$update_sql .= sprintf(" SET `reminder_datetime` = '%s', `update_key` = NULL", $new_scheduled_dt->format("Y-m-d H:i:s"));
					$update_sql .= sprintf(" WHERE `id` = %d", $id);
					$this->sms_reminder_db->update($update_sql, 0, "ddl");
				}
			} catch (Exception $e) {
				$this->log->add("Seven", "Error occured at send-sms-reminder.class.php");
				$this->log->add("Seven", $e->getMessage());
				$retry++;
				$update_sql = "UPDATE {$this->sms_reminder_db->table_name}";
				$update_sql .= sprintf(" SET `retry` = %d, `update_key` = NULL", $retry);
				$update_sql .= sprintf(" WHERE `id` = %d", $id);
				$this->sms_reminder_db->update($update_sql, 0, "ddl");
			}
		}

		$this->log->add("Seven", "Finished processing SMS Reminder Queue");
		return 0;
	}

}
