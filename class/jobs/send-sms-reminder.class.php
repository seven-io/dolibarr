<?php

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/helpers/EventLogger.php');
dol_include_once('/seven/core/class/seven_logger.class.php');
dol_include_once('/seven/core/controllers/settings/invoice.setting.php');
dol_include_once('/seven/core/controllers/settings/sms_setting.php');

class SendSMSReminderJob {
	private Seven_Logger $log;
	private SevenSMSReminderDatabase $smsReminderDb;

	function __construct() {
		global $db;

		$this->log = new Seven_Logger;
		$this->smsReminderDb = new SevenSMSReminderDatabase($db);
	}

	public function handle() { // Returns 0 if OK, < 0 if KO
		global $db;

		$this->log->add('Seven', 'Processing SMS Reminder Queue');

		$uniqueKey = generateUniqueKey();
		$updateSql = 'UPDATE ' . $this->smsReminderDb->tableName;
		$updateSql .= sprintf(' SET `update_key` = \'%s\'', $uniqueKey);
		$updateSql .= ' WHERE DATE(`reminder_datetime`) = CURDATE() ';
		$updateSql .= ' AND (`update_key` IS NULL OR `update_key` = \'\')';
		$updateSql .= ' LIMIT 20';
		$this->smsReminderDb->update($updateSql);

		$results = $this->smsReminderDb->get($uniqueKey);

		$smsInvoiceSettingModel = new SMS_Invoice_Setting($db);
		$smsInvoiceSetting = $smsInvoiceSettingModel->getSettings();

		$smsSettingModel = new SMS_Setting($db);
		$smsSettings = $smsSettingModel->getSettings();

		if ($smsInvoiceSetting->enable != 'on') {
			$this->log->add('Seven', 'SMS notification for invoice module is disabled. No SMS reminders will be sent.');
			$this->smsReminderDb->resetUpdateKey($uniqueKey);
			return -1;
		}

		$today = new DateTime('now', new DateTimeZone(getServerTimeZoneString()));
		$activeHourStart = DateTime::createFromFormat('H:ia', $smsSettings['SEVEN_ACTIVE_HOUR_START']);
		$activeHourEnd = DateTime::createFromFormat('H:ia', $smsSettings['SEVEN_ACTIVE_HOUR_END']);

		foreach ($results as $reminderObj) {
			$id = $reminderObj['id'];
			$settingUuid = $reminderObj['setting_uuid'];
			$retry = $reminderObj['retry'];
			$scheduledDt = new DateTime($reminderObj['reminder_datetime'], new DateTimeZone('UTC'));
			$scheduledDt->setTimezone(new DateTimeZone(getServerTimeZoneString()));

			// if the reminder date is today
			// check for current hour based on SERVER TZ
			try {
				if ($scheduledDt->getTimestamp() >= $activeHourStart->getTimestamp() && $scheduledDt->getTimestamp() <= $activeHourEnd->getTimestamp()) {
					$this->log->add('Seven', 'Scheduled DT in Active hours');
					// check if current time is in active hour
					if ($today->getTimestamp() >= $activeHourStart->getTimestamp() && $today->getTimestamp() <= $activeHourEnd->getTimestamp()) {
						$this->log->add('Seven', 'Current Time in Active hours');

						$invoiceObj = new $reminderObj['object_type']($db);
						$invoiceObj->fetch($reminderObj['object_id']);

						$thirdparty = new Societe($db);
						$thirdparty->fetch($invoiceObj->socid);

						$message = '';
						foreach ($smsInvoiceSetting->reminder_settings as $reminder_setting)
							if ($settingUuid === $reminder_setting->uuid)
								$message = $smsInvoiceSettingModel->fillKeywordsValues($invoiceObj, $reminder_setting->message);

						if (empty($message)) {
							$this->log->add('Seven', 'Setting UUID: {$settingUuid} was not found in sms reminder setting.');
							$this->log->add('Seven', 'Deleting UUID from sms reminder');
							$query = sprintf('DELETE FROM \'%s\' WHERE `setting_uuid` = \'%s\'', $this->smsReminderDb->tableName, $settingUuid);
							$this->smsReminderDb->db->query($query, 0, 'ddl');
							$this->log->add('Seven', 'Successfully deleted UUID from sms reminder');
							continue;
						}

						$resp = seven_send_sms($smsInvoiceSetting->send_from, validatedMobileNumber($thirdparty->phone, $thirdparty->country_code), $message, 'SMS Reminder');
						$msg = $resp['messages'][0];
						if ($msg['status'] == 0)
							EventLogger::create($invoiceObj->socid, 'contact', 'SMS sent to contact');
						else
							EventLogger::create($invoiceObj->socid, 'contact', 'SMS failed to send to contact', 'SMS failed due to: ' . $msg['err_msg']);

						$query = sprintf('DELETE FROM \'%s\' WHERE `id` = %d', $this->smsReminderDb->tableName, $id);
						$this->smsReminderDb->delete($query, 0, 'ddl');
					} else {
						$this->log->add('Seven', 'Current time not in active hours');
						$this->smsReminderDb->resetUpdateKeyById($id);
					}
				} else {
					// change: Set schedule dt to active_hour_start.
					$this->log->add('Seven', 'Scheduled time is not in ACTIVE HOUR');

					$newScheduledDt = clone $scheduledDt;
					$newScheduledDt->setTime($activeHourStart->format('H'), 0);

					$this->log->add('Seven', 'new Schedule DT in server TZ');
					$this->log->add('Seven', $newScheduledDt->format('Y-m-d H:i:s e'));

					$newScheduledDt->setTimezone(new DateTimeZone('UTC'));
					$updateSql = 'UPDATE {$this->smsReminderDb->tableName}';
					$updateSql .= sprintf(' SET `reminder_datetime` = \'%s\', `update_key` = NULL', $newScheduledDt->format('Y-m-d H:i:s'));
					$updateSql .= sprintf(' WHERE `id` = %d', $id);
					$this->smsReminderDb->update($updateSql, 0, 'ddl');
				}
			} catch (Exception $e) {
				$this->log->add('Seven', 'Error occured at send-sms-reminder.class.php');
				$this->log->add('Seven', $e->getMessage());

				$retry++;

				$updateSql = 'UPDATE {$this->smsReminderDb->tableName}';
				$updateSql .= sprintf(' SET `retry` = %d, `update_key` = NULL', $retry);
				$updateSql .= sprintf(' WHERE `id` = %d', $id);
				$this->smsReminderDb->update($updateSql, 0, 'ddl');
			}
		}

		$this->log->add('Seven', 'Finished processing SMS Reminder Queue');
		return 0;
	}
}
