<?php
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modSeven extends DolibarrModules {
	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db) {
		parent::__construct($db);

		global $conf;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 530000; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		$this->rights_class = 'seven'; // Key text used to identify module (for permissions, menus, etc...)
		$this->family = 'crm'; // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		$this->module_position = '90'; // Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->name = preg_replace('/^mod/i', '', get_class($this)); // Module label (no space allowed), used if translation string 'ModuleSevenName' not found (seven is name of module).
		$this->description = 'SevenDescription'; // Module description, used if translation string 'ModuleSevenAPIDesc' not found (SevenAPI is name of module).
		$this->descriptionlong = 'SevenDescription'; // Used only if file README.md and README-LL.md not found.
		$this->editor_name = 'seven communications GmbH & Co. KG';
		$this->editor_url = 'https://www.seven.io';
		$this->version = '3.0.0'; // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name); // Key used in llx_const table to save module status enabled/disabled (where seven is value of property name of module in uppercase)

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'module_128.png@seven'; // TODO

		$this->module_parts = [ // Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
			'triggers' => 1, // Set this to 1 if module has its own trigger directory (core/triggers)
			'login' => 0,  // Set this to 1 if module has its own login method file (core/login)
			'substitutions' => 0, // Set this to 1 if module has its own substitution function file (core/substitutions)
			'menus' => 0,  // Set this to 1 if module has its own menus handler directory (core/menus)
			'tpl' => 0, // Set this to 1 if module overwrite template dir (core/tpl)
			'barcode' => 0, // Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'models' => 0, // Set this to 1 if module has its own models directory (core/modules/xxx)
			'printing' => 0, // Set this to 1 if module has its own printing directory (core/modules/printing)
			'theme' => 0, // Set this to 1 if module has its own theme directory (theme)
			'css' => [ // Set this to relative path of css file if module has its own css file
				'/seven/css/index.css',
				'/seven/css/bootstrap.css',
				'/seven/css/jquery.modal.min.css',
			],
			'js' => [ // Set this to relative path of js file if module must load a js on all pages
				'/seven/js/jquery.modal.min.js',
				'/seven/js/helper.js',
			],
			'hooks' => [ // Set here all hooks context managed by module. To find available hook context, make a 'grep -r '>initHooks(' *' on source code. You can also set hook context to 'all'
				'data' => [
					'contactcard',
					'thirdpartycard',
					'invoicecard',
					'ordersuppliercard',
					'invoicesuppliercard',
					'projectcard',
					'hookcontext2',
				],
			],
			'moduleforexternal' => 0, // Set this to 1 if features of module are opened to external users
		];


		$this->dirs = ['/seven/temp']; // Data directories to create when module is enabled.
		$this->config_page_url = ['setting.php@seven']; // Config pages. Put here list of php page, stored into seven/admin directory, to use to setup module.
		$this->hidden = false; // A condition to hide module
		$this->depends = []; // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->requiredby = []; // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = []; // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->langfiles = ['seven@seven']; // The language file dedicated to your module
		$this->phpmin = [8, 1]; // Minimum version of PHP required by module
		$this->need_dolibarr_version = [11, 0]; // Minimum version of Dolibarr required by module
		$this->warnings_activation = []; // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = []; // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)

		$this->const = [];

		if (!isset($conf->seven) || !isset($conf->seven->enabled)) {
			$conf->seven = new stdClass;
			$conf->seven->enabled = 0;
		}

		$this->tabs = [];
		$this->tabs[] = ['data' => 'thirdparty:+sevensendsms:SevenTabTitle:seven@seven:$user->rights->seven->permission->read:/seven/tab_view.php?thirdparty_id=__ID__&entity=thirdparty'];                    // To add a new tab identified by code tabname1
		$this->tabs[] = ['data' => 'contact:+sevensendsms:SevenTabTitle:seven@seven:$user->rights->seven->permission->read:/seven/tab_view.php?contact_id=__ID__&entity=contact'];                    // To add a new tab identified by code tabname1
		$this->tabs[] = ['data' => 'invoice:+sevensendsms:SevenTabTitle:seven@seven:$user->rights->seven->permission->read:/seven/tab_view.php?invoice_id=__ID__&entity=invoice'];                    // To add a new tab identified by code tabname1
		$this->tabs[] = ['data' => 'supplier_invoice:+sevensendsms:SevenTabTitle:seven@seven:$user->rights->seven->permission->read:/seven/tab_view.php?supplier_invoice_id=__ID__&entity=supplier_invoice'];                    // To add a new tab identified by code tabname1
		$this->tabs[] = ['data' => 'supplier_order:+sevensendsms:SevenTabTitle:seven@seven:$user->rights->seven->permission->read:/seven/tab_view.php?supplier_order_id=__ID__&entity=supplier_order'];                    // To add a new tab identified by code tabname1
		$this->tabs[] = ['data' => 'project:+sevensendsms:SevenTabTitle:seven@seven:$user->rights->seven->permission->read:/seven/tab_view.php?project_id=__ID__&entity=project'];                    // To add a new tab identified by code tabname1
		$this->dictionaries = [];
		$this->boxes = []; // Add here list of php file(s) stored in seven/core/boxes that contains a class to show a widget.
		$this->cronjobs = []; // Cronjobs (List of cron jobs entries to add when module is enabled) - unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$this->rights = []; // Permissions provided by this module
		$r = 0;
		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read objects of seven'; // Permission label
		$this->rights[$r][4] = 'permission';
		$this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->seven->permission->read)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update objects of seven'; // Permission label
		$this->rights[$r][4] = 'permission';
		$this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->seven->permission->write)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete objects of seven'; // Permission label
		$this->rights[$r][4] = 'permission';
		$this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->seven->permission->delete)
		$r++;
		/* END MODULEBUILDER PERMISSIONS */

		// Main menu entries to add
		$this->menu = [];

		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$this->menu[] = [
			'fk_menu' => '', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type' => 'top', // This is a Top menu entry
			'titre' => 'ModuleSevenAPIName',
			'mainmenu' => 'seven',
			'leftmenu' => '',
			'url' => '/seven/admin/sms_outbox.php',
			'langs' => 'seven@seven', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 100,
			'enabled' => '$conf->seven->enabled', // Define condition to show or hide menu entry. Use '$conf->seven->enabled' if entry must be visible if module is enabled.
			'perdms' => '$user->rights->seven->permission->read', // Use 'perms'=>'$user->rights->seven->myobject->read' if you want your menu with a permission rules
			'target' => '',
			'user' => 2, // 0=Menu for internal users, 1=external users, 2=both
		];
		/* END MODULEBUILDER TOPMENU */

		// Imports profiles provided by this module
		$r = 1;
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return     int                1 if OK, 0 if KO
	 */
	public function init($options = '') {
		global $conf, $langs;

		$result = $this->_load_tables('/seven/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Permissions
		$this->remove($options);

		$sql = [];

		// Document templates
		$moduledir = 'seven';
		$myTmpObjects = [];
		$myTmpObjects['MyObject'] = ['includerefgeneration' => 0, 'includedocgeneration' => 0];

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectKey == 'MyObject') {
				continue;
			}
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT . '/install/doctemplates/seven/template_myobjects.odt';
				$dirodt = DOL_DATA_ROOT . '/doctemplates/seven';
				$dest = $dirodt . '/template_myobjects.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, 0, 0);
					if ($result < 0) {
						$langs->load('errors');
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, [
					sprintf('DELETE FROM %sdocument_model WHERE nom = \'standard_%s\' AND type = \'%s\' AND entity = %d', MAIN_DB_PREFIX, strtolower($myTmpObjectKey), $this->db->escape(strtolower($myTmpObjectKey)), $conf->entity),
					sprintf('INSERT INTO %sdocument_model (nom, type, entity) VALUES(\'standard_%s\', \'%s\', %d)', MAIN_DB_PREFIX, strtolower($myTmpObjectKey), $this->db->escape(strtolower($myTmpObjectKey)), $conf->entity),
					sprintf('DELETE FROM %sdocument_model WHERE nom = \'generic_%s_odt\' AND type = \'%s\' AND entity = %d', MAIN_DB_PREFIX, strtolower($myTmpObjectKey), $this->db->escape(strtolower($myTmpObjectKey)), $conf->entity),
					sprintf('INSERT INTO %sdocument_model (nom, type, entity) VALUES(\'generic_%s_odt\', \'%s\', %d)', MAIN_DB_PREFIX, strtolower($myTmpObjectKey), $this->db->escape(strtolower($myTmpObjectKey)), $conf->entity)
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
