<?php
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) $res = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
// Try main.inc.php using relative path
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../../../main.inc.php')) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');
dol_include_once('/core/lib/admin.lib.php');
dol_include_once('/seven/lib/SevenSMS.class.php');
dol_include_once('/seven/core/helpers/helpers.php');
dol_include_once('/seven/core/class/html.sevenformsms.class.php');

global $langs, $user, $db;

$langs->load('admin');
$langs->load('sms');
$langs->load('seven@seven');

if (property_exists($user, 'societe_id') && $user->societe_id > 0) accessforbidden();

llxHeader('', $langs->trans('seven Send SMS'));

$form_sms = new SevenFormSms($db);
$form_sms->handlePostRequest();

echo load_fiche_titre($langs->trans('Send SMS'), false, 'seven_32.png@seven'); // TODO: add to /img

$form_sms->param['returnUrl'] = $_SERVER['REQUEST_URI'];
$form_sms->param['action'] = 'send_sms';
$form_sms->showForm();

$db->close();

llxFooter();
