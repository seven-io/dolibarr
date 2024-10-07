<?php
extract($template_variables);
?>

<div style="display:flex;gap:20px;">
	<h2>Edit SMS Template</h2>
</div>

<form method="POST" action="<?= $_SERVER["PHP_SELF"] ?>">
	<input type="hidden" name="token" value="<?= newToken(); ?>">
	<input type="hidden" name="action" value="<?= "save_sms_template" ?>">
	<input type="hidden" name="sms_template_id" value="<?= $sms_template['id'] ?>">

	<table class="border seven-table">
		<tr>
			<td width="200px"><?= $langs->trans("seven_sms_template_title") ?></td>
			<td>
				<input type="text" name="sms_template_title" value="<?= $sms_template['title']; ?>">
			</td>
		</tr>
		<tr>
			<td width="200px"><?= $langs->trans("seven_sms_template_message") ?></td>
			<td>
				<label for="sms_template_message">
					<textarea id="sms_template_message" name="sms_template_message" cols="40"
							  rows="4"><?= $sms_template['message']; ?></textarea>
				</label>
				<p>Customize your SMS with keywords
					<button type="button" class="seven_open_keyword" data-attr-target="sms_template_message">
						Keywords
					</button>
				</p>
			</td>
		</tr>
	</table>
	<center>
		<input class="button" type="submit" name="submit"
			   value="<?= $langs->trans("seven_sms_template_save") ?>">
	</center>
</form>
