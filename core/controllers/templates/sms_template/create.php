<?php
extract($templateVariables);
?>

<div style='display:flex;gap:20px;'>
	<h2><?= $langs->trans('seven_create_sms_template') ?></h2>
</div>

<form method='POST' action='<?= $_SERVER['PHP_SELF'] ?>'>
	<input type='hidden' name='token' value='<?= newToken(); ?>'/>
	<input type='hidden' name='action' value='<?= 'save_sms_template' ?>'>

	<table class='border seven-table'>
		<tr>
			<td><label for='sms_template_title'><?= $langs->trans('seven_sms_template_title') ?></label></td>
			<td><input id='sms_template_title' name='sms_template_title' value='' /></td>
		</tr>
		<tr>
			<td><?= $langs->trans('seven_sms_template_message') ?></td>
			<td>
				<label for='sms_template_message'>
					<textarea id='sms_template_message' name='sms_template_message' cols='40' rows='4'></textarea>
				</label>
				<button type='button' class='seven_open_keyword' data-attr-target='sms_template_message'>
					Insert Placeholders
				</button>
			</td>
		</tr>
	</table>
	<div style='text-align:center'>
		<input class='button' type='submit' name='submit' value='<?= $langs->trans('seven_sms_template_save') ?>'>
	</div>
</form>
