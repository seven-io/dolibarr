<?php
/* Copyright (C) 2021 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    sms77/lib/sms77.lib.php
 * \ingroup sms77
 * \brief   Library files with common functions for Sms77
 */

/**
 * Prepare admin pages header
 * @return array
 */
function sms77AdminPrepareHead() {
    global $langs, $conf;

    $langs->load("sms77@sms77");

    $h = 0;
    $head = [];

    $head[$h][0] = dol_buildpath("/sms77/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/sms77/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'sms77');

    return $head;
}
