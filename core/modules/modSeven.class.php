<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2021 SuperAdmin
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
 *    \defgroup   seven     Module Seven
 *  \brief      Seven module descriptor.
 *  \file       htdocs/seven/core/modules/modSeven.class.php
 *  \ingroup    seven
 *  \brief      Description and activation file for module Seven
 */
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Seven
 */
class modSeven extends DolibarrModules {
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     * @param DoliDB $db Database handler
     */
    public function __construct($db) {
        global $langs, $conf;
        $this->db = $db;

        // Id for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
        $this->numero = 500000; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'seven';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
        // It is used to group modules by family in module setup page
        $this->family = "other";

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        //$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
        // Module label (no space allowed), used if translation string 'ModuleSevenName' not found (Seven is name of module).
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description, used if translation string 'ModuleSevenDesc' not found (Seven is name of module).
        $this->description = "SevenDescription";
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = "SevenDescription";

        // Author
        $this->editor_name = 'seven communications GmbH & Co. KG';
        $this->editor_url = 'https://www.seven.io';

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
        $this->version = '1.0';
        // Url to the file with your last numberversion of this module
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where SEVEN is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        // To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
        $this->picto = 'generic';

        // Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
        $this->module_parts = [
            // Set this to 1 if module has its own trigger directory (core/triggers)
            'triggers' => 0,
            // Set this to 1 if module has its own login method file (core/login)
            'login' => 0,
            // Set this to 1 if module has its own substitution function file (core/substitutions)
            'substitutions' => 0,
            // Set this to 1 if module has its own menus handler directory (core/menus)
            'menus' => 0,
            // Set this to 1 if module overwrite template dir (core/tpl)
            'tpl' => 0,
            // Set this to 1 if module has its own barcode directory (core/modules/barcode)
            'barcode' => 0,
            // Set this to 1 if module has its own models directory (core/modules/xxx)
            'models' => 0,
            // Set this to 1 if module has its own printing directory (core/modules/printing)
            'printing' => 0,
            // Set this to 1 if module has its own theme directory (theme)
            'theme' => 0,
            // Set this to relative path of css file if module has its own css file
            'css' => [],
            // Set this to relative path of js file if module must load a js on all pages
            'js' => [],
            // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
            'hooks' => [
                'usercard' => 'test123',
                'globalcard' => 'test0rnbla',
                'all' => 'blubbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                //   'data' => array(
                //       'hookcontext1',
                //       'hookcontext2',
                //   ),
                //   'entity' => '0',
            ],
            // Set this to 1 if features of module are opened to external users
            'moduleforexternal' => 0,
        ];

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/seven/temp","/seven/subdir");
        $this->dirs = ["/seven/temp"];

        // Config pages. Put here list of php page, stored into seven/admin directory, to use to setup module.
        $this->config_page_url = ["setup.php@seven"];

        // Dependencies
        // A condition to hide module
        $this->hidden = false;
        // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
        $this->depends = [];
        $this->requiredby = []; // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
        $this->conflictwith = []; // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

        // The language file dedicated to your module
        $this->langfiles = ["seven@seven"];

        // Prerequisites
        $this->phpmin = [5, 5]; // Minimum version of PHP required by module
        $this->need_dolibarr_version = [11, -3]; // Minimum version of Dolibarr required by module

        // Messages at activation
        $this->warnings_activation = []; // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
        $this->warnings_activation_ext = []; // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)

        $this->const = [];

        if (!isset($conf->seven) || !isset($conf->seven->enabled)) {
            $conf->seven = new stdClass();
            $conf->seven->enabled = 0;
        }

        // Array to add new pages in new tabs
        $this->tabs = [];


        // Dictionaries
        $this->dictionaries = [];

        // Boxes/Widgets
        // Add here list of php file(s) stored in seven/core/boxes that contains a class to show a widget.
        $this->boxes = [];

        // Cronjobs (List of cron jobs entries to add when module is enabled)
        // unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
        $this->cronjobs = [];

        // Permissions provided by this module
        $this->rights = [];
        $r = 0;
        // Add here entries to declare new permissions
        /* BEGIN MODULEBUILDER PERMISSIONS */
        $this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
        $this->rights[$r][1] = 'Read objects of Seven'; // Permission label
        $this->rights[$r][4] = 'myobject'; // In php code, permission will be checked by test if ($user->rights->seven->level1->level2)
        $this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->seven->level1->level2)
        $r++;
        $this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
        $this->rights[$r][1] = 'Create/Update objects of Seven'; // Permission label
        $this->rights[$r][4] = 'myobject'; // In php code, permission will be checked by test if ($user->rights->seven->level1->level2)
        $this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->seven->level1->level2)
        $r++;
        $this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
        $this->rights[$r][1] = 'Delete objects of Seven'; // Permission label
        $this->rights[$r][4] = 'myobject'; // In php code, permission will be checked by test if ($user->rights->seven->level1->level2)
        $this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->seven->level1->level2)
        $r++;
        /* END MODULEBUILDER PERMISSIONS */

        // Main menu entries to add
        $this->menu = [];
        $r = 0;
        // Add here entries to declare new menus
        /* BEGIN MODULEBUILDER TOPMENU */
        $this->menu[$r++] = [
            'fk_menu' => '', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type' => 'top', // This is a Top menu entry
            'titre' => 'ModuleSevenName',
            'mainmenu' => 'seven',
            'leftmenu' => '',
            'url' => '/seven/sevenindex.php',
            'langs' => 'seven@seven', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position' => 1000 + $r,
            'enabled' => '$conf->seven->enabled', // Define condition to show or hide menu entry. Use '$conf->seven->enabled' if entry must be visible if module is enabled.
            'perms' => '1', // Use 'perms'=>'$user->rights->seven->myobject->read' if you want your menu with a permission rules
            'target' => '',
            'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
        ];
        /* END MODULEBUILDER TOPMENU */
    }

    /**
     *  Function called when module is enabled.
     *  The init function add constants, boxes, permissions and menus (defined in
     * constructor) into Dolibarr database. It also creates data directories
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return     int                1 if OK, 0 if KO
     */
    public function init($options = '') {
        global $conf, $langs;

        $result = $this->_load_tables('/seven/sql/');
        if ($result < 0) return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')

        // Permissions
        $this->remove($options);

        $sql = [];

        // Document templates
        $moduledir = 'seven';
        $myTmpObjects = [];
        $myTmpObjects['MyObject'] = ['includerefgeneration' => 0, 'includedocgeneration' => 0];

        foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
            if ($myTmpObjectKey == 'MyObject') continue;
            if ($myTmpObjectArray['includerefgeneration']) {
                $src = DOL_DOCUMENT_ROOT . '/install/doctemplates/seven/template_myobjects.odt';
                $dirodt = DOL_DATA_ROOT . '/doctemplates/seven';
                $dest = $dirodt . '/template_myobjects.odt';

                if (file_exists($src) && !file_exists($dest)) {
                    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
                    dol_mkdir($dirodt);
                    $result = dol_copy($src, $dest, 0, 0);
                    if ($result < 0) {
                        $langs->load("errors");
                        $this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
                        return 0;
                    }
                }

                $sql = array_merge($sql, [
                    "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'standard_" . strtolower($myTmpObjectKey) . "' AND type = '" . strtolower($myTmpObjectKey) . "' AND entity = " . $conf->entity,
                    "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES('standard_" . strtolower($myTmpObjectKey) . "','" . strtolower($myTmpObjectKey) . "'," . $conf->entity . ")",
                    "DELETE FROM " . MAIN_DB_PREFIX . "document_model WHERE nom = 'generic_" . strtolower($myTmpObjectKey) . "_odt' AND type = '" . strtolower($myTmpObjectKey) . "' AND entity = " . $conf->entity,
                    "INSERT INTO " . MAIN_DB_PREFIX . "document_model (nom, type, entity) VALUES('generic_" . strtolower($myTmpObjectKey) . "_odt', '" . strtolower($myTmpObjectKey) . "', " . $conf->entity . ")",
                ]);
            }
        }

        return $this->_init($sql, $options);
    }

    /**
     *  Function called when module is disabled.
     *  Remove from database constants, boxes and permissions from Dolibarr database.
     *  Data directories are not deleted
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return     int                 1 if OK, 0 if KO
     */
    public function remove($options = '') {
        return $this->_remove([], $options);
    }
}
