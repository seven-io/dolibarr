<?php

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/seven.lib.php';

// Translations
$langs->loadLangs(["admin", "seven@seven"]);

// Access control
if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$arrayofparameters = [
    'SEVEN_API_KEY' => ['css' => 'minwidth200', 'enabled' => 1],
];

$error = 0;
$setupnotempty = 0;

if ((float)DOL_VERSION >= 6)
    include DOL_DOCUMENT_ROOT . '/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
    $maskconstorder = GETPOST('maskconstorder', 'alpha');
    $maskorder = GETPOST('maskorder', 'alpha');

    if ($maskconstorder) {
        $res = dolibarr_set_const($db, $maskconstorder, $maskorder, 'chaine', 0, '', $conf->entity);
        if (!($res > 0)) $error++;
    }

    if (!$error) setEventMessages($langs->trans("SetupSaved"), null);
    else setEventMessages($langs->trans("Error"), null, 'errors');
} elseif ($action == 'specimen') {
    $modele = GETPOST('module', 'alpha');
    $tmpobjectkey = GETPOST('object');

    $tmpobject = new $tmpobjectkey($db);
    $tmpobject->initAsSpecimen();

    // Search template files
    $file = '';
    $classname = '';
    $filefound = 0;
    $dirmodels = array_merge(['/'], (array)$conf->modules_parts['models']);
    foreach ($dirmodels as $reldir) {
        $file = dol_buildpath($reldir . "core/modules/seven/doc/pdf_" . $modele . "_" . strtolower($tmpobjectkey) . ".modules.php", 0);
        if (file_exists($file)) {
            $filefound = 1;
            $classname = "pdf_" . $modele;
            break;
        }
    }

    if ($filefound) {
        require_once $file;
        $module = new $classname($db);
        if ($module->write_file($tmpobject, $langs) > 0) {
            header("Location: " . DOL_URL_ROOT . "/document.php?modulepart=" . strtolower($tmpobjectkey) . "&file=SPECIMEN.pdf");
            return;
        }
        setEventMessages($module->error, null, 'errors');
        dol_syslog($module->error, LOG_ERR);
    } else {
        setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
        dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
    }
} elseif ($action == 'setmod') {
    // TODO Check if numbering module chosen can be activated by calling method canBeActivated
    $tmpobjectkey = GETPOST('object');
    if (!empty($tmpobjectkey)) {
        $constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . "_ADDON";
        dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
    }
} // Activate a model
elseif ($action == 'set') $ret = addDocumentModel($value, $type, $label, $scandir);
elseif ($action == 'del') {
    $ret = delDocumentModel($value, $type);

    if ($ret > 0) {
        $tmpobjectkey = GETPOST('object');
        if (!empty($tmpobjectkey)) {
            $constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
            if ($conf->global->$constforval == "$value") dolibarr_del_const($db, $constforval, $conf->entity);
        }
    }
} // Set or unset default model
elseif ($action == 'setdoc') {
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
        if ($ret > 0) $ret = addDocumentModel($value, $type, $label, $scandir);
    }
} elseif ($action == 'unsetdoc') {
    $tmpobjectkey = GETPOST('object');

    if (!empty($tmpobjectkey)) {
        $constforval = 'SEVEN_' . strtoupper($tmpobjectkey) . '_ADDON_PDF';
        dolibarr_del_const($db, $constforval, $conf->entity);
    }
}

/*
 * View
 */

$form = new Form($db);
$dirmodels = array_merge(['/'], (array)$conf->modules_parts['models']);
$page_name = "SevenSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
echo load_fiche_titre($langs->trans($page_name), '<a href="' .
    ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1')
    . '">' . $langs->trans("BackToModuleList") . '</a>', 'object_seven@seven');

// Configuration header
echo dol_get_fiche_head(sevenAdminPrepareHead(), 'settings', '', -1, "seven@seven");

// Setup page goes here
echo '<span class="opacitymedium">' . $langs->trans("SevenSetupPage") . '</span><br><br>';

if (isset($_GET['missingOptions']))
    echo $langs->trans('MissingOptions') . ' ' . $_GET['missingOptions'];

if ($action == 'edit') {
    echo '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
    echo '<input type="hidden" name="token" value="' . newToken() . '">';
    echo '<input type="hidden" name="action" value="update">';
    echo '<table class="noborder centpercent">';
    echo '<tr class="liste_titre"><td class="titlefield">' . $langs->trans("Parameter") . '</td><td>' . $langs->trans("Value") . '</td></tr>';

    foreach ($arrayofparameters as $key => $val) {
        echo '<tr class="oddeven"><td>';
        $tooltiphelp = (($langs->trans($key . 'Tooltip') != $key . 'Tooltip') ? $langs->trans($key . 'Tooltip') : '');
        echo $form->textwithpicto($langs->trans($key), $tooltiphelp);
        echo '</td><td><input name="' . $key . '"  class="flat ' . (empty($val['css']) ? 'minwidth200' : $val['css']) . '" value="' . $conf->global->$key . '"></td></tr>';
    }
    echo '</table>';

    echo '<br><div class="center">';
    echo '<input class="button button-save" type="submit" value="' . $langs->trans("Save") . '">';
    echo '</div></form><br>';
} else if (!empty($arrayofparameters)) {
    echo '<table class="noborder centpercent">';
    echo '<tr class="liste_titre"><td class="titlefield">' . $langs->trans("Parameter") . '</td><td>' . $langs->trans("Value") . '</td></tr>';

    foreach ($arrayofparameters as $key => $val) {
        $setupnotempty++;

        echo '<tr class="oddeven"><td>';
        $tooltiphelp = (($langs->trans($key . 'Tooltip') != $key . 'Tooltip') ? $langs->trans($key . 'Tooltip') : '');
        echo $form->textwithpicto($langs->trans($key), $tooltiphelp);
        echo '</td><td>' . $conf->global->$key . '</td></tr>';
    }

    echo '</table>';
    echo '<div class="tabsAction">';
    echo '<a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=edit">' . $langs->trans("Modify") . '</a>';
    echo '</div>';
} else echo '<br>' . $langs->trans("NothingToSetup");

$moduledir = 'seven';
$myTmpObjects = [];
$myTmpObjects['MyObject'] = ['includerefgeneration' => 0, 'includedocgeneration' => 0];

foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
    if ($myTmpObjectKey == 'MyObject') continue;
    if ($myTmpObjectArray['includerefgeneration']) {
        /* Orders Numbering model */
        $setupnotempty++;

        echo load_fiche_titre($langs->trans("NumberingModules", $myTmpObjectKey), '', '');
        echo '<table class="noborder centpercent">';
        echo '<tr class="liste_titre">';
        echo '<td>' . $langs->trans("Name") . '</td>';
        echo '<td>' . $langs->trans("Description") . '</td>';
        echo '<td class="nowrap">' . $langs->trans("Example") . '</td>';
        echo '<td class="center" width="60">' . $langs->trans("Status") . '</td>';
        echo '<td class="center" width="16">' . $langs->trans("ShortInfo") . '</td>';
        echo '</tr>' . "\n";

        clearstatcache();

        foreach ($dirmodels as $reldir) {
            $dir = dol_buildpath($reldir . "core/modules/" . $moduledir);

            if (is_dir($dir)) {
                $handle = opendir($dir);

                if (is_resource($handle)) {
                    while (($file = readdir($handle)) !== false) {
                        if (strpos($file, 'mod_' . strtolower($myTmpObjectKey) . '_') === 0 && substr($file, dol_strlen($file) - 3, 3) == 'php') {
                            $file = substr($file, 0, dol_strlen($file) - 4);

                            require_once $dir . '/' . $file . '.php';

                            $module = new $file($db);

                            // Show modules according to features level
                            if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) continue;
                            if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) continue;

                            if ($module->isEnabled()) {
                                dol_include_once('/' . $moduledir . '/class/' . strtolower($myTmpObjectKey) . '.class.php');

                                echo '<tr class="oddeven"><td>' . $module->name . "</td><td>\n";
                                echo $module->info() . '</td>';

                                // Show example of numbering model
                                echo '<td class="nowrap">';
                                $tmp = $module->getExample();
                                if (preg_match('/^Error/', $tmp)) {
                                    $langs->load("errors");
                                    echo '<div class="error">' . $langs->trans($tmp) . '</div>';
                                } elseif ($tmp == 'NotConfigured') echo $langs->trans($tmp);
                                else echo $tmp;
                                echo '</td>' . "\n";

                                echo '<td class="center">';
                                $constforvar = 'SEVEN_' . strtoupper($myTmpObjectKey) . '_ADDON';
                                if ($conf->global->$constforvar == $file)
                                    echo img_picto($langs->trans("Activated"), 'switch_on');
                                else {
                                    echo '<a href="' . $_SERVER["PHP_SELF"] . '?action=setmod&token=' . newToken() . '&object=' . strtolower($myTmpObjectKey) . '&value=' . urlencode($file) . '">';
                                    echo img_picto($langs->trans("Disabled"), 'switch_off');
                                    echo '</a>';
                                }
                                echo '</td>';

                                $mytmpinstance = new $myTmpObjectKey($db);
                                $mytmpinstance->initAsSpecimen();

                                // Info
                                $htmltooltip = '';
                                $htmltooltip .= '' . $langs->trans("Version") . ': <b>' . $module->getVersion() . '</b><br>';

                                $nextval = $module->getNextValue($mytmpinstance);
                                if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
                                    $htmltooltip .= '' . $langs->trans("NextValue") . ': ';

                                    if ($nextval) {
                                        if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured')
                                            $nextval = $langs->trans($nextval);
                                        $htmltooltip .= $nextval . '<br>';
                                    } else $htmltooltip .= $langs->trans($module->error) . '<br>';
                                }

                                echo '<td class="center">';
                                echo $form->textwithpicto('', $htmltooltip, 1, 0);
                                echo '</td></tr>\n';
                            }
                        }
                    }
                    closedir($handle);
                }
            }
        }
        echo "</table><br>\n";
    }

    if ($myTmpObjectArray['includedocgeneration']) {
        /* Document templates generators */
        $setupnotempty++;
        $type = strtolower($myTmpObjectKey);

        echo load_fiche_titre($langs->trans("DocumentModules", $myTmpObjectKey), '', '');

        // Load array def with activated templates
        $def = [];
        $sql = "SELECT nom";
        $sql .= " FROM " . MAIN_DB_PREFIX . "document_model";
        $sql .= " WHERE type = '" . $db->escape($type) . "'";
        $sql .= " AND entity = " . $conf->entity;
        $resql = $db->query($sql);

        if ($resql) {
            $i = 0;
            $num_rows = $db->num_rows($resql);

            while ($i < $num_rows) {
                $array = $db->fetch_array($resql);
                $def[] = $array[0];
                $i++;
            }
        } else dol_print_error($db);

        echo "<table class=\"noborder\" width=\"100%\">\n";
        echo "<tr class=\"liste_titre\">\n";
        echo '<td>' . $langs->trans("Name") . '</td>';
        echo '<td>' . $langs->trans("Description") . '</td>';
        echo '<td class="center" width="60">' . $langs->trans("Status") . "</td>\n";
        echo '<td class="center" width="60">' . $langs->trans("Default") . "</td>\n";
        echo '<td class="center" width="38">' . $langs->trans("ShortInfo") . '</td>';
        echo '<td class="center" width="38">' . $langs->trans("Preview") . '</td>';
        echo "</tr>\n";

        clearstatcache();

        foreach ($dirmodels as $reldir) {
            foreach (['', '/doc'] as $valdir) {
                $realpath = $reldir . "core/modules/" . $moduledir . $valdir;
                $dir = dol_buildpath($realpath);

                if (is_dir($dir)) {
                    $handle = opendir($dir);

                    if (is_resource($handle)) {
                        while (($file = readdir($handle)) !== false) $filelist[] = $file;
                        closedir($handle);
                        arsort($filelist);

                        foreach ($filelist as $file) {
                            if (preg_match('/\.modules\.php$/i', $file)
                                && preg_match('/^(pdf_|doc_)/', $file)
                                && file_exists($dir . '/' . $file)) {
                                $name = substr($file, 4, dol_strlen($file) - 16);
                                $classname = substr($file, 0, dol_strlen($file) - 12);

                                require_once $dir . '/' . $file;
                                $module = new $classname($db);

                                $modulequalified = 1;
                                if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) $modulequalified = 0;
                                if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) $modulequalified = 0;

                                if ($modulequalified) {
                                    echo '<tr class="oddeven"><td width="100">';
                                    echo(empty($module->name) ? $name : $module->name);
                                    echo "</td><td>\n";
                                    echo method_exists($module, 'info')
                                        ? $module->info($langs) : $module->description;
                                    echo '</td>';

                                    // Active
                                    if (in_array($name, $def)) {
                                        echo '<td class="center">' . "\n";
                                        echo '<a href="' . $_SERVER["PHP_SELF"] . '?action=del&amp;token=' . newToken() . '&amp;value=' . $name . '">';
                                        echo img_picto($langs->trans("Enabled"), 'switch_on');
                                        echo '</a></td>';
                                    } else {
                                        echo '<td class="center">' . "\n";
                                        echo '<a href="' . $_SERVER["PHP_SELF"] . '?action=set&amp;token=' . newToken() . '&amp;value=' . $name . '&amp;scan_dir=' . urlencode($module->scandir) . '&amp;label=' . urlencode($module->name) . '">' . img_picto($langs->trans("Disabled"), 'switch_off') . '</a>';
                                        echo "</td>";
                                    }

                                    echo '<td class="center">'; // Default
                                    $constforvar = 'SEVEN_' . strtoupper($myTmpObjectKey) . '_ADDON';
                                    if ($conf->global->$constforvar == $name) {
                                        // Even if choice is the default value, we allow to disable it. Replace this with previous line if you need to disable unset
                                        echo '<a href="' . $_SERVER["PHP_SELF"] . '?action=unsetdoc&amp;token=' . newToken() . '&amp;object=' . urlencode(strtolower($myTmpObjectKey)) . '&amp;value=' . $name . '&amp;scan_dir=' . $module->scandir . '&amp;label=' . urlencode($module->name) . '&amp;type=' . urlencode($type) . '" alt="' . $langs->trans("Disable") . '">' . img_picto($langs->trans("Enabled"), 'on') . '</a>';
                                    } else
                                        echo '<a href="' . $_SERVER["PHP_SELF"] . '?action=setdoc&amp;token=' . newToken() . '&amp;object=' . urlencode(strtolower($myTmpObjectKey)) . '&amp;value=' . $name . '&amp;scan_dir=' . urlencode($module->scandir) . '&amp;label=' . urlencode($module->name) . '" alt="' . $langs->trans("Default") . '">' . img_picto($langs->trans("Disabled"), 'off') . '</a>';
                                    echo '</td>';

                                    // Info
                                    $htmltooltip = '' . $langs->trans("Name") . ': ' . $module->name;
                                    $htmltooltip .= '<br>' . $langs->trans("Type") . ': ' . ($module->type ? $module->type : $langs->trans("Unknown"));
                                    if ($module->type == 'pdf')
                                        $htmltooltip .= '<br>' . $langs->trans("Width") . '/' . $langs->trans("Height") . ': ' . $module->page_largeur . '/' . $module->page_hauteur;
                                    $htmltooltip .= '<br>' . $langs->trans("Path") . ': ' . preg_replace('/^\//', '', $realpath) . '/' . $file;
                                    $htmltooltip .= '<br><br><u>' . $langs->trans("FeaturesSupported") . ':</u>';
                                    $htmltooltip .= '<br>' . $langs->trans("Logo") . ': ' . yn($module->option_logo, 1, 1);
                                    $htmltooltip .= '<br>' . $langs->trans("MultiLanguage") . ': ' . yn($module->option_multilang, 1, 1);

                                    echo '<td class="center">';
                                    echo $form->textwithpicto('', $htmltooltip, 1, 0);
                                    echo '</td>';
                                    echo '<td class="center">'; // Preview
                                    if ($module->type == 'pdf')
                                        echo '<a href="' . $_SERVER["PHP_SELF"] . '?action=specimen&module=' . $name . '&object=' . $myTmpObjectKey . '">' . img_object($langs->trans("Preview"), 'pdf') . '</a>'; else
                                        echo img_object($langs->trans("PreviewNotAvailable"), 'generic');
                                    echo '</td></tr>\n';
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

if (empty($setupnotempty)) echo '<br>' . $langs->trans("NothingToSetup");

echo dol_get_fiche_end(); // Page end

llxFooter();
$db->close();
