<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *    \file       seven/sevenindex.php
 *    \ingroup    seven
 *    \brief      Home page of seven top menu
 */

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

function seven_request($endpoint, array $data) {
    global $seven_apiKey;
    $ch = curl_init('https://gateway.seven.io/api/' . $endpoint);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_merge($data, ['json' => 1])));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-type: application/json',
        'SentWith: dolibarr',
        'X-Api-Key: ' . $seven_apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $json = curl_exec($ch);
    curl_close($ch);
    return $json;
}

/**
 * @return array<User>
 */
function seven_getMobileUsers() {
    global $db;

    $users = [];
    $User = new User($db);
    $User->fetchAll();

    foreach ($User->users as $user) {
        /** @var User $user */
        if ('' !== $user->user_mobile) $users[] = $user;
    }

    return $users;
}

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $users = seven_getMobileUsers();

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
        } else {
            $xml = $_POST['seven_xml'];
            if ('' !== $xml && isset($xml)) $params['xml'] = $xml;
        }

        foreach ($requests as $request) $responses[] = seven_request(
                    $_POST['seven_msg_type'], array_merge($params, $request));
    }
    else $responses[] = $langs->trans('NoUsersWithMobileFound');
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
            <code><?= $response ?></code>
        <?php endforeach; ?>

        <form action='<?= $_SERVER['PHP_SELF'] ?>' id='seven_msg' method='POST'>
            <p>
                <label for='seven_text'>
                    <?= $langs->trans('Text') ?>
                </label>
            </p>
            <textarea id='seven_text' maxlength='10000' name='seven_text'
                      required></textarea>

            <hr>

            <p><?= $langs->trans('SelectMessageType') ?></p>
            <label><?= $langs->trans('MessageTypeSms') ?>
                <input checked name='seven_msg_type' type='radio' value='sms'/>
            </label>

            <label><?= $langs->trans('MessageTypeVoice') ?>
                <input name='seven_msg_type' type='radio' value='voice'/>
            </label>

            <hr>

            <label><?= $langs->trans('From') ?><br>
                <input maxlength='16' name='seven_from'/>
            </label>

            <div id='seven_wrap_label'>
                <hr>

                <label><?= $langs->trans('Label') ?><br>
                    <input maxlength='100' name='seven_label'/>
                </label>
            </div>

            <div id='seven_wrap_foreign_id'>
                <hr>

                <label><?= $langs->trans('ForeignId') ?><br>
                    <input maxlength='64' name='seven_foreign_id'/>
                </label>
            </div>

            <hr>

            <div id='seven_wrap_flash'>
                <label>
                    <?= $langs->trans('Flash') ?><br>
                    <input type='checkbox' name='seven_flash' value='1'/>
                </label>
            </div>

            <div id='seven_wrap_performance_tracking'>
                <label>
                    <?= $langs->trans('PerformanceTracking') ?><br>
                    <input type='checkbox' name='seven_performance_tracking' value='1'/>
                </label>
            </div>

            <div id='seven_wrap_no_reload'>
                <label>
                    <?= $langs->trans('NoReload') ?><br>
                    <input type='checkbox' name='seven_no_reload' value='1'/>
                </label>
            </div>

            <div id='seven_wrap_xml'>
                <label>
                    <?= $langs->trans('XML') ?><br>
                    <input type='checkbox' name='seven_xml' value='1'/>
                </label>
            </div>

            <hr>

            <input type='submit' class='button' value='<?= $langs->trans('Send') ?>'/>
        </form>
    </div>
    <script>
        var $text = document.getElementById('seven_text')
        var $xml = document.getElementById('seven_wrap_xml')
        var smsEles = [
            document.getElementById('seven_wrap_flash'),
            document.getElementById('seven_wrap_performance_tracking'),
            document.getElementById('seven_wrap_no_reload'),
            document.getElementById('seven_wrap_label'),
            document.getElementById('seven_wrap_foreign_id')
        ]

        Array.from(document.getElementsByName('seven_msg_type'))
            .forEach(function(el) {
                el.addEventListener('click', function(e) {
                    var isSMS = 'sms' === e.currentTarget.value
                    $text.maxLength = isSMS ? 1520 : 10000

                    smsEles.forEach(function(ele) {
                        ele.style.display = isSMS ? 'block' : 'none'
                    })

                    $xml.style.display = isSMS ? 'none' : 'block'
                })
            })
    </script>
<?php

$NBMAX = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;
$max = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

llxFooter(); // End of page
$db->close();
