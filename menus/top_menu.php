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
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

global $langs, $user, $conf;

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

$langs->loadLangs(['seven@seven']); // Load translation files required by the page

$action = GETPOST('action', 'aZ09');

if (!$user->rights->seven->permission->read) accessforbidden(); // $user->rights->seven->myobject->read

llxHeader('', $langs->trans('SevenAPIArea'));

echo load_fiche_titre($langs->trans('SevenAPIArea'), '', 'seven.png@seven');
echo '<div class=\'fichecenter\'><div class=\'fichethirdleft\'>';

global $db;

$action = GETPOST('action');
$form = new Form($db);
$arrayofparameters = [
	'SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED' => [],
];

include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';
?>
	<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
		<input type='hidden' name='token' value='<?= newToken(); ?>'/>
		<input type='hidden' name='action' value='update' />

		<table class='border'>
			<tr>
				<td><label for='balance'>Balance</label></td>
				<td><input id='balance' name='balance' size='30' value='123' disabled /></td>
			</tr>
			<tr>
				<td>
					<label for='sms_from'>
						<?= $form->textwithpicto($langs->trans('SEVEN_FROM'), 'Sender/Caller ID'); ?>
					</label>
				</td>
				<td>
					<input id='sms_from' name='sms_from' size='30' value='<?= $conf->global->SEVEN_FROM; ?>' />
				</td>
			</tr>
			<tr>
				<td><label for='message'>SMS Message</label></td>
				<td>
					<textarea cols='40' name='SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED' id='message'
							  rows='4'><?= $conf->global->SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED ?></textarea>
				</td>
			</tr>
			<tr>
				<td><label for='message'>SMS Message</label></td>
				<td>
					<textarea cols='40' name='sms_message' id='message'
							  rows='4'><?= $conf->global->SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED ?></textarea>
				</td>
			</tr>
		</table>
		<div style='text-align:center'>
			<input class='button' type='submit' name='submit' value='<?= $langs->trans('SendSMSButtonTitle') ?>'>
		</div>
	</form>
<?php
echo '</div><div class=\'fichetwothirdright\'>';

$NBMAX = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;
$max = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

echo '</div></div>';

llxFooter();
$db->close();
