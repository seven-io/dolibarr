<?php

/**
 * Prepare admin pages header
 * @return array
 */
function sevenAdminPrepareHead() {
    global $langs, $conf;

    $langs->load("seven@seven");

    $h = 0;
    $head = [];

    //$head[$h][0] = dol_buildpath("/seven/admin/setup.php", 1);
	$head[$h][0] = dol_buildpath('/custom/seven/admin/setup.php', 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    //$head[$h][0] = dol_buildpath("/seven/admin/about.php", 1);
	$head[$h][0] = dol_buildpath('/custom/seven/admin/about.php', 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'seven');

    return $head;
}
