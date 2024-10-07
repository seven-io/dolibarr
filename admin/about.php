<?php
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
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
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once '../lib/seven.lib.php';

global $langs, $user;

$langs->loadLangs(['errors', 'admin', 'seven@seven']);

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$form = new Form($db);
$page_name = 'SevenAbout';
llxHeader('', $langs->trans($page_name));

$linkback = ($backtopage ?: DOL_URL_ROOT) . '/admin/modules.php?restore_lastsearch_values=1';
$linkback = sprintf('<a href=\'%s\'>' . $langs->trans('BackToModuleList') . '</a>', $linkback);

echo load_fiche_titre($langs->trans($page_name), $linkback, 'object_seven@seven');

echo dol_get_fiche_head(sevenAdminPrepareHead(), 'about', '', 0, 'seven@seven');

dol_include_once('/seven/core/modules/modSeven.class.php');

echo (new modSeven($db))->getDescLong();

echo dol_get_fiche_end();
llxFooter();
$db->close();
