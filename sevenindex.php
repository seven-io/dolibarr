<?php

global $conf, $db, $langs;

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT']))
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

$seven_apiKey = $conf->global->SEVEN_API_KEY;

if (!$seven_apiKey) {
	header('Location: /custom/seven/admin/setup.php?missingOptions='
		. $langs->trans('SEVEN_API_KEY'));
	exit;
}

$responses = [];

if ('POST' === $_SERVER['REQUEST_METHOD']) {
	dol_include_once('/seven/class/SevenApi.class.php');
	$api = new SevenApi;
	$users = $api->getMobileUsers();

	if (count($users)) {
		$text = $_POST['seven_text'];
		$requests = [];
		$matches = [];
		preg_match_all('{{{+[a-z]*_*[a-z]+}}}', $text, $matches);
		$hasPlaceholders = is_array($matches) && !empty($matches[0]);

		if ($hasPlaceholders) foreach ($users as $user) {
			$pText = $text;
			$reflect = new ReflectionObject($user);

			foreach ($matches[0] as $match) {
				$key = trim($match, '{}');
				$replace = '';
				if ($reflect->hasProperty($key)) {
					$prop = $reflect->getProperty($key);
					$prop->setAccessible(true);
					$replace = $prop->getValue($user);
				}
				$pText = str_replace($match, $replace, $pText);
				$pText = preg_replace('/\s+/', ' ', $pText);
				$pText = str_replace(' ,', ',', $pText);
			}

			$requests[] = ['text' => $pText, 'to' => $user->user_mobile];
		}
		else {
			$to = [];
			foreach ($users as $user) $to[] = $user->user_mobile;
			$requests[] = ['text' => $text, 'to' => implode(',', $to)];
		}

		$params = [];
		$from = $_POST['seven_from'];
		if ('' !== $from && isset($from)) $params['from'] = $from;

		if ('sms' === $_POST['seven_msg_type']) {
			$flash = $_POST['seven_flash'];
			if ('' !== $flash && isset($flash)) $params['flash'] = $flash;

			$pt = $_POST['seven_performance_tracking'];
			if ('' !== $pt && isset($pt)) $params['performance_tracking'] = $pt;

			$nr = $_POST['seven_no_reload'];
			if ('' !== $nr && isset($nr)) $params['no_reload'] = $nr;

			$label = isset($_POST['seven_label']) ? $_POST['seven_label'] : null;
			if ($label) $params['label'] = $label;

			$fid = isset($_POST['seven_foreign_id']) ? $_POST['seven_foreign_id'] : null;
			if ($fid) $params['foreign_id'] = $fid;
		}

		$method = $_POST['seven_msg_type'];

		foreach ($requests as $request)
			$responses[] = $api->$method(array_merge($params, $request));
	} else $responses[] = $langs->trans('NoUsersWithMobileFound');
}

$langs->loadLangs(['seven@seven']); // Load translation files required by the page
$action = GETPOST('action', 'aZ09');

$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$max = 5;
$now = dol_now();
$form = new Form($db);
$formfile = new FormFile($db);

llxHeader('', $langs->trans('SevenArea'));
echo load_fiche_titre($langs->trans('SevenArea'), '', 'seven.png@seven');

?>
	<div class='fichecenter'>
		<?php foreach ($responses as $response): ?>
			<code><?= json_encode($response, JSON_PRETTY_PRINT) ?></code>
		<?php endforeach ?>

		<form action='<?= $_SERVER['PHP_SELF'] ?>' id='seven_msg' method='POST'>
			<p>
				<label for='seven_text'>
					<?= $langs->trans('Text') ?>
				</label>
			</p>
			<textarea id='seven_text' maxlength='10000' name='seven_text' required></textarea>

			<hr>

			<p><?= $langs->trans('SelectMessageType') ?></p>
			<label><?= $langs->trans('MessageTypeSms') ?>
				<input checked name='seven_msg_type' type='radio' value='sms'/>
			</label>

			<label><?= $langs->trans('MessageTypeVoice') ?>
				<input name='seven_msg_type' type='radio' value='voice'/>
			</label>

			<hr>

			<label><?= $langs->trans('From') ?>
				<br>
				<input maxlength='16' name='seven_from'/>
			</label>

			<div id='seven_wrap_label'>
				<hr>

				<label><?= $langs->trans('Label') ?>
					<br>
					<input maxlength='100' name='seven_label'/>
				</label>
			</div>

			<div id='seven_wrap_foreign_id'>
				<hr>

				<label><?= $langs->trans('ForeignId') ?>
					<br>
					<input maxlength='64' name='seven_foreign_id'/>
				</label>
			</div>

			<hr>

			<div id='seven_wrap_flash'>
				<label>
					<?= $langs->trans('Flash') ?>
					<br>
					<input type='checkbox' name='seven_flash' value='1'/>
				</label>
			</div>

			<div id='seven_wrap_performance_tracking'>
				<label>
					<?= $langs->trans('PerformanceTracking') ?>
					<br>
					<input type='checkbox' name='seven_performance_tracking' value='1'/>
				</label>
			</div>

			<div id='seven_wrap_no_reload'>
				<label>
					<?= $langs->trans('NoReload') ?>
					<br>
					<input type='checkbox' name='seven_no_reload' value='1'/>
				</label>
			</div>

			<hr>

			<input type='submit' class='button' value='<?= $langs->trans('Send') ?>'/>
		</form>
	</div>
	<script>
		var $text = document.getElementById('seven_text')
		var smsEles = [
			document.getElementById('seven_wrap_flash'),
			document.getElementById('seven_wrap_performance_tracking'),
			document.getElementById('seven_wrap_no_reload'),
			document.getElementById('seven_wrap_label'),
			document.getElementById('seven_wrap_foreign_id'),
		]

		Array.from(document.getElementsByName('seven_msg_type'))
			.forEach(function(el) {
				el.addEventListener('click', function(e) {
					var isSMS = 'sms' === e.currentTarget.value
					$text.maxLength = isSMS ? 1520 : 10000

					smsEles.forEach(function(ele) {
						ele.style.display = isSMS ? 'block' : 'none'
					})
				})
			})
	</script>
<?php

$NBMAX = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;
$max = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

llxFooter(); // End of page
$db->close();
