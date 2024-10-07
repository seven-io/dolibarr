<?php
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user, $conf;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
// include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// Load translation files required by the page
$langs->loadLangs(["seven@seven"]);

$action = GETPOST('action', 'aZ09');

// Security check
if (!$user->rights->seven->myobject->read) {
	accessforbidden();
}

llxHeader("", $langs->trans("SevenAPIArea"));

print load_fiche_titre($langs->trans("SevenAPIArea"), '', 'seven.png@seven');

print '<div class="fichecenter"><div class="fichethirdleft">';

// file to save data into database
// @Method POST params: action = 'update', token = newToken()
// @Params action = 'update', token = newToken()

$action = GETPOST('action');
$form = new Form($db);
$arrayofparameters = [
	"SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED" => [],
];

include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';

?>
	<!-- Begin form SMS -->
	<form method="POST" action="<?= $_SERVER["PHP_SELF"] ?>">
		<input type="hidden" name="token" value="<?= newToken(); ?>">
		<input type="hidden" name="action" value="update">

		<table class="border" width="100%">
			<!-- Balance -->
			<tr>
				<td width="200px">Balance</td>
				<td>
					<input type="text" name="sms_from" size="30" value="123" disabled>
				</td>
			</tr>

			<!-- Enable  -->

			<tr>
				<td width="200px"><?= $form->textwithpicto($langs->trans("SEVEN_FROM"), "Sender/Caller ID"); ?>*</td>
				<td>
					<input type="text" name="sms_from" size="30" value="<?= $conf->global->SEVEN_FROM; ?>">
				</td>
			</tr>

			<!-- To -->
			<tr>
				<td width="200px" valign="top">SMS Message</td>
				<td>
					<textarea cols="40" name="SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED" id="message"
							  rows="4"><?= $conf->global->SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED ?></textarea>
				</td>
			</tr>

			<!-- Message -->
			<tr>
				<td width="200px" valign="top">SMS Message</td>
				<td>
					<textarea cols="40" name="sms_message" id="message"
							  rows="4"><?= $conf->global->SEVEN_AUTO_THIRDPARTY_INVOICE_CREATED ?></textarea>
				</td>
			</tr>

		</table>
		<!-- Submit -->
		<center>
			<input class="button" type="submit" name="submit" value="<?= $langs->trans("SendSMSButtonTitle") ?>">
		</center>

	</form>
<?php
print '</div><div class="fichetwothirdright">';


$NBMAX = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;
$max = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

print '</div></div>';

// End of page
llxFooter();
$db->close();
