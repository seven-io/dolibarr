<?php
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT']))
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php'))
	$res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php'))
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

global $langs, $user, $conf, $db, $hookmanager;

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/seven.lib.php';

$langs->loadLangs(['admin', 'seven@seven']);

$hookmanager->initHooks(['sevensetup', 'globalsetup']);

if (!$user->rights->seven->permission->read) accessforbidden();

dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/controllers/settings/ticket.setting.php');

$setting = new SMS_Ticket_Setting($db);
$setting->updateSettings();

$setting->render();

$db->close();
