<?php

function sevenAdminPrepareHead(): array {
	global $langs, $conf;

	$langs->load('seven@seven');

	$h = 0;
	$head = [];

	$head[$h][0] = dol_buildpath('/seven/admin/sms_outbox.php', 1);
	$head[$h][1] = $langs->trans('SMSOutboxSettingPageTitle');
	$head[$h][2] = 'sms_outbox';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/setting.php', 1);
	$head[$h][1] = $langs->trans('setting_page_menu_title');
	$head[$h][2] = 'settings';
	$h++;


	$head[$h][0] = dol_buildpath('/seven/admin/sms_send.php', 1);
	$head[$h][1] = $langs->trans('SendSMSSettingPageTitle');
	$head[$h][2] = 'send_sms';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/sms_bulk.php', 1);
	$head[$h][1] = $langs->trans('BulkSMSSettingPageTitle');
	$head[$h][2] = 'sms_bulk';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/sms_template.php', 1);
	$head[$h][1] = $langs->trans('SMSTemplateSettingPageTitle');
	$head[$h][2] = 'sms_template';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/third_party.php', 1);
	$head[$h][1] = $langs->trans('ThirdPartySettingPageTitle');
	$head[$h][2] = 'third_party';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/proposal.php', 1);
	$head[$h][1] = $langs->trans('ProposalSettingPageTitle');
	$head[$h][2] = 'propal';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/project.php', 1);
	$head[$h][1] = $langs->trans('ProjectSettingPageTitle');
	$head[$h][2] = 'project';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/sales_order.php', 1);
	$head[$h][1] = $langs->trans('SalesOrderSettingPageTitle');
	$head[$h][2] = 'sales_order';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/supplier_order.php', 1);
	$head[$h][1] = $langs->trans('SupplierOrderSettingPageTitle');
	$head[$h][2] = 'supplier_order';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/ticket.php', 1);
	$head[$h][1] = $langs->trans('TicketSettingPageTitle');
	$head[$h][2] = 'ticket';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/shipment.php', 1);
	$head[$h][1] = $langs->trans('ShipmentSettingPageTitle');
	$head[$h][2] = 'shipment';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/member.php', 1);
	$head[$h][1] = $langs->trans('MemberSettingPageTitle');
	$head[$h][2] = 'member';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/logs.php', 1);
	$head[$h][1] = $langs->trans('LogsSettingPageTitle');
	$head[$h][2] = 'logs';
	$h++;

	$head[$h][0] = dol_buildpath('/seven/admin/help.php', 1);
	$head[$h][1] = $langs->trans('HelpSettingPageTitle');
	$head[$h][2] = 'help';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'seven@seven');

	return $head;
}
