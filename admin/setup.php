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

global $langs, $user, $hookmanager, $db, $conf;

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once '../lib/seven.lib.php';

$langs->loadLangs(['admin', 'seven@seven']);

$hookmanager->initHooks(['sevensetup', 'globalsetup']);

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$arrayofparameters = [
	'SEVEN_MYPARAM1' => ['type' => 'string', 'css' => 'minwidth500', 'enabled' => 1],
	'SEVEN_MYPARAM2' => ['type' => 'textarea', 'enabled' => 1],
];

$error = 0;
$setupnotempty = 0;

$useFormSetup = 0; // Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
// Convert arrayofparameter into a formSetup object
if ($useFormSetup && (float)DOL_VERSION >= 15) {
	require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
	$formSetup = new FormSetup($db);

	$formSetup->addItemsFromParamsArray($arrayofparameters); // use the param convertor

	// or use the new system - see example:

	$setupnotempty = count($formSetup->items);
}

$dirmodels = array_merge(['/'], $conf->modules_parts['models']);

include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'alpha');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) $error++;
	}

	if (!$error) setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	else setEventMessages($langs->trans('Error'), null, 'errors');
} elseif ($action == 'specimen') {
	$modele = GETPOST('module', 'alpha');
	$tmpobjectkey = GETPOST('object');

	$tmpobject = new $tmpobjectkey($db);
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = '';
	$classname = '';
	$filefound = 0;
	$dirmodels = array_merge(['/'], $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir . 'core/modules/seven/doc/pdf_' . $modele . '_' . strtolower($tmpobjectkey) . '.modules.php', 0);
		if (file_exists($file)) {
			$filefound = 1;
			$classname = 'pdf_' . $modele;
			break;
		}
	}

	if ($filefound) {
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($tmpobject, $langs) > 0) {
			header('Location: ' . DOL_URL_ROOT . '/document.php?modulepart=' . strtolower($tmpobjectkey) . '&file=SPECIMEN.pdf');
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans('ErrorModuleNotFound'), null, 'errors');
		dol_syslog($langs->trans('ErrorModuleNotFound'), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . '_ADDON';
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	// Activate a model
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		$tmpobjectkey = GETPOST('object');
		if (!empty($tmpobjectkey)) {
			$constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
			if ($conf->global->$constforval == '$value') {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	// Set or unset default model
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->$constforval = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
}

$form = new Form($db);

$pageName = 'SevenapiSetup';

llxHeader('', $langs->trans($pageName), '');

// Subheader
$linkback = $backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1';
$linkback = '<a href=\'' . $linkback . '\'>' . $langs->trans('BackToModuleList') . '</a>';

echo load_fiche_titre($langs->trans($pageName), $linkback, 'title_setup');

// Configuration header
$head = sevenAdminPrepareHead();
echo dol_get_fiche_head($head, 'settings', $langs->trans($pageName), -1, 'seven@seven');

// Setup page goes here
echo '<span class=\'opacitymedium\'>' . $langs->trans('SevenSetupPage') . '</span><br><br>';


if ($action == 'edit') {
	if ($useFormSetup && (float)DOL_VERSION >= 15) echo $formSetup->generateOutput(true);
	else {
		echo '<form method=\'POST\' action=\'' . $_SERVER['PHP_SELF'] . '\'>';
		echo '<input type=\'hidden\' name=\'token\' value=\'' . newToken() . '\'>';
		echo '<input type=\'hidden\' name=\'action\' value=\'update\' />';

		echo '<table class=\'noborder centpercent\'>';
		echo '<tr class=\'liste_titre\'><td class=\'titlefield\'>' . $langs->trans('Parameter') . '</td><td>' . $langs->trans('Value') . '</td></tr>';

		foreach ($arrayofparameters as $constname => $val) {
			if ($val['enabled'] == 1) {
				$setupnotempty++;
				echo '<tr class=\'oddeven\'><td>';
				$tooltiphelp = (($langs->trans($constname . 'Tooltip') != $constname . 'Tooltip') ? $langs->trans($constname . 'Tooltip') : '');
				echo '<span id=\'helplink\'' . $constname . '\' class=\'spanforparamtooltip\'>' . $form->textwithpicto($langs->trans($constname), $tooltiphelp, 1, 'info', '', 0, 3, 'tootips' . $constname) . '</span>';
				echo '</td><td>';

				if ($val['type'] == 'textarea') {
					echo '<textarea class=\'flat\' name=\'' . $constname . '\' id=\'' . $constname . '\' cols=\'50\' rows=\'5\' wrap=\'soft\'>' . '\n';
					echo $conf->global->{$constname};
					echo '</textarea>\n';
				} elseif ($val['type'] == 'html') {
					require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
					$doleditor = new DolEditor($constname, $conf->global->{$constname}, '', 160, 'dolibarr_notes', '', false, false, $conf->fckeditor->enabled, ROWS_5, '90%');
					$doleditor->Create();
				} elseif ($val['type'] == 'yesno') {
					echo $form->selectyesno($constname, $conf->global->{$constname}, 1);
				} elseif (str_contains($val['type'], 'emailtemplate:')) {
					include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
					$formmail = new FormMail($db);

					$tmp = explode(':', $val['type']);
					$nboftemplates = $formmail->fetchAllEMailTemplate($tmp[1], $user, null, 1); // We set lang=null to get in priority record with no lang
					$arrayofmessagename = [];
					if (is_array($formmail->lines_model)) {
						foreach ($formmail->lines_model as $modelmail) {
							$moreonlabel = '';
							if (!empty($arrayofmessagename[$modelmail->label]))
								$moreonlabel = ' <span class=\'opacitymedium\'>(' . $langs->trans('SeveralLangugeVariatFound') . ')</span>';
							// The 'label' is the key that is unique if we exclude the language
							$arrayofmessagename[$modelmail->id] = $langs->trans(preg_replace('/\(|\)/', '', $modelmail->label)) . $moreonlabel;
						}
					}
					echo $form->selectarray($constname, $arrayofmessagename, $conf->global->{$constname}, 'None', 0, 0, '', 0, 0, 0, '', '', 1);
				} elseif (str_contains($val['type'], 'category:')) {
					require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
					require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
					$formother = new FormOther($db);

					$tmp = explode(':', $val['type']);
					echo img_picto('', 'category', 'class=\'pictofixedwidth\'');
					echo $formother->select_categories($tmp[1], $conf->global->{$constname}, $constname, 0, $langs->trans('CustomersProspectsCategoriesShort'));
				} elseif (str_contains($val['type'], 'thirdparty_type')) {
					require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
					$formcompany = new FormCompany($db);
					echo $formcompany->selectProspectCustomerType($conf->global->{$constname}, $constname);
				} elseif ($val['type'] == 'securekey') {
					echo '<input required=\'required\' class=\'flat\' id=\'' . $constname . '\' name=\'' . $constname . '\' value=\'' . (GETPOST($constname, 'alpha') ? GETPOST($constname, 'alpha') : $conf->global->{$constname}) . '\' size=\'40\'>';
					if (!empty($conf->use_javascript_ajax))
						echo '&nbsp;' . img_picto($langs->trans('Generate'), 'refresh', 'id=\'generate_token\'' . $constname . '\' class=\'linkobject\'');
					if (!empty($conf->use_javascript_ajax)) {
						?>
						<script>
							jQuery(document).ready(function () {
									$('#generate_token<?=$constname?>').click(function() {
									$.get('<?=DOL_URL_ROOT . '/core/ajax/security.php'?>', {
									action: 'getrandompassword',
									generic: true
								},
								function(token) {
									$('#<?=$constname?>').val(token);
								});
							});
							});
						</script>
<?php
					}
				} elseif ($val['type'] == 'product') {
					if (!empty($conf->product->enabled) || !empty($conf->service->enabled)) {
						$selected = (empty($conf->global->$constname) ? '' : $conf->global->$constname);
						$form->select_produits($selected, $constname, '', 0);
					}
				} else {
					$className = empty($val['css']) ? 'minwidth200' : $val['css'];
					echo '<input name=\'' . $constname . '\'  class=\'flat \'' . $className . '\' value=\'' . $conf->global->{$constname} . '\'>';
				}
				echo '</td></tr>';
			}
		}
		echo '</table><br><div class=\'center\'>';
		echo '<input class=\'button button-save\' type=\'submit\' value=\'' . $langs->trans('Save') . '\'></div></form>';
	}
	echo '<br>';
} else {
	if ($useFormSetup && (float)DOL_VERSION >= 15) {
		if (!empty($formSetup->items)) echo $formSetup->generateOutput();
	} else {
		if (!empty($arrayofparameters)) {
			echo '<table class=\'noborder centpercent\'>';
			echo '<tr class=\'liste_titre\'><td class=\'titlefield\'>' . $langs->trans('Parameter') . '</td><td>' . $langs->trans('Value') . '</td></tr>';

			foreach ($arrayofparameters as $constname => $val) {
				if ($val['enabled'] == 1) {
					$setupnotempty++;
					echo '<tr class=\'oddeven\'><td>';
					$tooltiphelp = ($langs->trans($constname . 'Tooltip') != $constname . 'Tooltip')
						? $langs->trans($constname . 'Tooltip')
						: '';
					echo $form->textwithpicto($langs->trans($constname), $tooltiphelp);
					echo '</td><td>';

					if ($val['type'] == 'textarea') echo dol_nl2br($conf->global->{$constname});
					elseif ($val['type'] == 'html') echo  $conf->global->{$constname};
					elseif ($val['type'] == 'yesno') echo ajax_constantonoff($constname);
					elseif (str_contains($val['type'], 'emailtemplate:')) {
						include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
						$formmail = new FormMail($db);
						$tmp = explode(':', $val['type']);
						$template = $formmail->getEMailTemplate($db, $tmp[1], $user, $langs, $conf->global->{$constname});
						if ($template < 0) setEventMessages(null, $formmail->errors, 'errors');
						echo $langs->trans($template->label);
					} elseif (str_contains($val['type'], 'category:')) {
						$c = new Categorie($db);
						$result = $c->fetch($conf->global->{$constname});
						if ($result < 0) {
							setEventMessages(null, $c->errors, 'errors');
						} elseif ($result > 0) {
							$ways = $c->print_all_ways(' &gt;&gt; ', 'none', 0, 1); // $ways[0] = 'ccc2 >> ccc2a >> ccc2a1' with html formated text
							$toprint = [];
							foreach ($ways as $way)
								$toprint[] = '<li class=\'select2-search-choice-dolibarr noborderoncategories\'' . ($c->color ? ' style=\'background: #' . $c->color . ';\'' : ' style=\'background: #bbb\'') . '>' . $way . '</li>';
							echo '<div class=\'select2-container-multi-dolibarr\' style=\'width: 90%;\'><ul class=\'select2-choices-dolibarr\'>' . implode(' ', $toprint) . '</ul></div>';
						}
					} elseif (str_contains($val['type'], 'thirdparty_type')) {
						if ($conf->global->{$constname} == 2) echo $langs->trans('Prospect');
						elseif ($conf->global->{$constname} == 3) echo $langs->trans('ProspectCustomer');
						elseif ($conf->global->{$constname} == 1) echo $langs->trans('Customer');
						elseif ($conf->global->{$constname} == 0) echo $langs->trans('NorProspectNorCustomer');
					} elseif ($val['type'] == 'product') {
						$product = new Product($db);
						$resprod = $product->fetch($conf->global->{$constname});
						if ($resprod > 0) echo $product->ref;
						elseif ($resprod < 0) setEventMessages(null, $object->errors, 'errors');
					} else echo $conf->global->{$constname};
					echo '</td></tr>';
				}
			}

			echo '</table>';
		}
	}

	if ($setupnotempty)
		echo '<div class=\'tabsAction\'><a class=\'butAction\' href=\'' . $_SERVER['PHP_SELF'] . '?action=edit&token=' . newToken() . '\'>' . $langs->trans('Modify') . '</a></div>';
	else echo '<br>' . $langs->trans('NothingToSetup');
}

$moduledir = 'seven';
$myTmpObjects = [];
$myTmpObjects['MyObject'] = ['includerefgeneration' => 0, 'includedocgeneration' => 0];

foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
	if ($myTmpObjectKey == 'MyObject') continue;
	if ($myTmpObjectArray['includerefgeneration']) {
		$setupnotempty++;

		echo load_fiche_titre($langs->trans('NumberingModules', $myTmpObjectKey), '', '');
		echo '<table class=\'noborder centpercent\'>';
		echo '<tr class=\'liste_titre\'>';
		echo '<td>' . $langs->trans('Name') . '</td>';
		echo '<td>' . $langs->trans('Description') . '</td>';
		echo '<td class=\'nowrap\'>' . $langs->trans('Example') . '</td>';
		echo '<td class=\'center\' width=\'60\'>' . $langs->trans('Status') . '</td>';
		echo '<td class=\'center\' width=\'16\'>' . $langs->trans('ShortInfo') . '</td>';
		echo '</tr>' . '\n';

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			$dir = dol_buildpath($reldir . 'core/modules/' . $moduledir);

			if (is_dir($dir)) {
				$handle = opendir($dir);
				if (is_resource($handle)) {
					while (($file = readdir($handle)) !== false) {
						if (str_starts_with($file, 'mod_' . strtolower($myTmpObjectKey) . '_') && substr($file, dol_strlen($file) - 3, 3) == 'php') {
							$file = substr($file, 0, dol_strlen($file) - 4);

							require_once $dir . '/' . $file . '.php';

							$module = new $file($db);

							// Show modules according to features level
							if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) continue;
							if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) continue;

							if ($module->isEnabled()) {
								dol_include_once('/' . $moduledir . '/class/' . strtolower($myTmpObjectKey) . '.class.php');

								echo '<tr class=\'oddeven\'><td>' . $module->name . '</td><td>\n';
								echo $module->info();
								echo '</td>';

								// Show example of numbering model
								echo '<td class=\'nowrap\'>';
								$tmp = $module->getExample();
								if (str_starts_with($tmp, 'Error')) {
									$langs->load('errors');
									echo '<div class=\'error\'>' . $langs->trans($tmp) . '</div>';
								} elseif ($tmp == 'NotConfigured') echo $langs->trans($tmp);
								else echo $tmp;
								echo '</td>' . '\n';

								echo '<td class=\'center\'>';
								$constforvar = 'SEVEN_' . strtoupper($myTmpObjectKey) . '_ADDON';
								if ($conf->global->$constforvar == $file) {
									echo img_picto($langs->trans('Activated'), 'switch_on');
								} else {
									echo '<a href=\'' . $_SERVER['PHP_SELF'] . '?action=setmod&token=' . newToken() . '&object=' . strtolower($myTmpObjectKey) . '&value=' . urlencode($file) . '\'>';
									echo img_picto($langs->trans('Disabled'), 'switch_off') . '</a>';
								}
								echo '</td>';

								$mytmpinstance = new $myTmpObjectKey($db);
								$mytmpinstance->initAsSpecimen();

								$htmltooltip = '' . $langs->trans('Version') . ': <b>' . $module->getVersion() . '</b><br>';

								$nextval = $module->getNextValue($mytmpinstance);
								if ('$nextval' != $langs->trans('NotAvailable')) {  // Keep ' on nextval
									$htmltooltip .= '' . $langs->trans('NextValue') . ': ';
									if ($nextval) {
										if (str_starts_with($nextval, 'Error') || $nextval == 'NotConfigured')
											$nextval = $langs->trans($nextval);
										$htmltooltip .= $nextval . '<br>';
									} else $htmltooltip .= $langs->trans($module->error) . '<br>';
								}

								echo '<td class=\'center\'>';
								echo $form->textwithpicto('', $htmltooltip, 1, 0) . '</td></tr>\n';
							}
						}
					}
					closedir($handle);
				}
			}
		}
		echo '</table><br>\n';
	}

	if ($myTmpObjectArray['includedocgeneration']) {
		/*
		 * Document templates generators
		 */
		$setupnotempty++;
		$type = strtolower($myTmpObjectKey);

		echo load_fiche_titre($langs->trans('DocumentModules', $myTmpObjectKey), '', '');

		// Load array def with activated templates
		$def = [];
		$sql = 'SELECT nom';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'document_model';
		$sql .= ' WHERE type = \'' . $db->escape($type) . '\'';
		$sql .= ' AND entity = ' . $conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$i = 0;
			$num_rows = $db->num_rows($resql);
			while ($i < $num_rows) {
				$array = $db->fetch_array($resql);
				$def[] = $array[0];
				$i++;
			}
		} else {
			dol_print_error($db);
		}

		echo '<table class=\'noborder\' width=\'100%\'>\n';
		echo '<tr class=\'liste_titre\'>\n';
		echo '<td>' . $langs->trans('Name') . '</td>';
		echo '<td>' . $langs->trans('Description') . '</td>';
		echo '<td class=\'center\' width=\'60\'>' . $langs->trans('Status') . '</td>\n';
		echo '<td class=\'center\' width=\'60\'>' . $langs->trans('Default') . '</td>\n';
		echo '<td class=\'center\' width=\'38\'>' . $langs->trans('ShortInfo') . '</td>';
		echo '<td class=\'center\' width=\'38\'>' . $langs->trans('Preview') . '</td>';
		echo '</tr>\n';

		clearstatcache();

		foreach ($dirmodels as $reldir) {
			foreach (['', '/doc'] as $valdir) {
				$realpath = $reldir . 'core/modules/' . $moduledir . $valdir;
				$dir = dol_buildpath($realpath);

				if (is_dir($dir)) {
					$handle = opendir($dir);
					if (is_resource($handle)) {
						while (($file = readdir($handle)) !== false) {
							$filelist[] = $file;
						}
						closedir($handle);
						arsort($filelist);

						foreach ($filelist as $file) {
							if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
								if (file_exists($dir . '/' . $file)) {
									$name = substr($file, 4, dol_strlen($file) - 16);
									$classname = substr($file, 0, dol_strlen($file) - 12);

									require_once $dir . '/' . $file;
									$module = new $classname($db);

									$modulequalified = 1;
									if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2)
										$modulequalified = 0;
									if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1)
										$modulequalified = 0;

									if ($modulequalified) {
										echo '<tr class=\'oddeven\'><td width=\'100\'>';
										echo (empty($module->name) ? $name : $module->name);
										echo '</td><td>\n';
										if (method_exists($module, 'info')) echo $module->info($langs);
										else echo $module->description;
										echo '</td>';

										// Active
										echo '<td class=\'center\'>' . '\n';
										if (in_array($name, $def)) {
											echo '<a href=\'' . $_SERVER['PHP_SELF'] . '?action=del&token=' . newToken() . '&value=' . urlencode($name) . '\'>';
											echo img_picto($langs->trans('Enabled'), 'switch_on') . '</a></td>';
										} else
											echo '<a href=\'' . $_SERVER['PHP_SELF'] . '?action=set&token=' . newToken() . '&value=' . urlencode($name) . '&scan_dir=' . urlencode($module->scandir) . '&label=' . urlencode($module->name) . '\'>' . img_picto($langs->trans('Disabled'), 'switch_off') . '</a></td>';

										// Default
										echo '<td class=\'center\'>';
										$constforvar = 'SEVEN_' . strtoupper($myTmpObjectKey) . '_ADDON';
										if ($conf->global->$constforvar == $name)
											echo '<a href=\'' . $_SERVER['PHP_SELF'] . '?action=unsetdoc&token=' . newToken() . '&object=' . urlencode(strtolower($myTmpObjectKey)) . '&value=' . urlencode($name) . '&scan_dir=' . urlencode($module->scandir) . '&label=' . urlencode($module->name) . '&amp;type=' . urlencode($type) . '\' alt=\'' . $langs->trans('Disable') . '\'>' . img_picto($langs->trans('Enabled'), 'on') . '</a>';
										else
											echo '<a href=\'' . $_SERVER['PHP_SELF'] . '?action=setdoc&token=' . newToken() . '&object=' . urlencode(strtolower($myTmpObjectKey)) . '&value=' . urlencode($name) . '&scan_dir=' . urlencode($module->scandir) . '&label=' . urlencode($module->name) . '\' alt=\'' . $langs->trans('Default') . '\'>' . img_picto($langs->trans('Disabled'), 'off') . '</a>';
										echo '</td>';

										$htmltooltip = '' . $langs->trans('Name') . ': ' . $module->name;
										$htmltooltip .= '<br>' . $langs->trans('Type') . ': ' . ($module->type ?: $langs->trans('Unknown'));
										if ($module->type == 'pdf')
											$htmltooltip .= '<br>' . $langs->trans('Width') . '/' . $langs->trans('Height') . ': ' . $module->page_largeur . '/' . $module->page_hauteur;
										$htmltooltip .= '<br>' . $langs->trans('Path') . ': ' . preg_replace('/^\//', '', $realpath) . '/' . $file;
										$htmltooltip .= '<br><br><u>' . $langs->trans('FeaturesSupported') . ':</u>';
										$htmltooltip .= '<br>' . $langs->trans('Logo') . ': ' . yn($module->option_logo, 1, 1);
										$htmltooltip .= '<br>' . $langs->trans('MultiLanguage') . ': ' . yn($module->option_multilang, 1, 1);

										echo '<td class=\'center\'>'
											. $form->textwithpicto('', $htmltooltip, 1, 0) . '</td>'
											. '<td class=\'center\'>'

										;
										if ($module->type == 'pdf')
											echo '<a href=\'' . $_SERVER['PHP_SELF'] . '?action=specimen&module=' . $name . '&object=' . $myTmpObjectKey . '\'>' . img_object($langs->trans('Preview'), 'pdf') . '</a>';
										else echo img_object($langs->trans('PreviewNotAvailable'), 'generic');
										echo '</td></tr>\n';
									}
								}
							}
						}
					}
				}
			}
		}
		echo '</table>';
	}
}

if (empty($setupnotempty)) echo '<br>' . $langs->trans('NothingToSetup');

echo dol_get_fiche_end();

llxFooter();
$db->close();
