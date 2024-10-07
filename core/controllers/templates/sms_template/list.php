<?php
extract($templateVariables);
$paginationHref = $_SERVER['PHP_SELF'] . '?pageno=';
global $langs;
?>

<div style='display:flex;gap:20px;'>
	<h2><?= $langs->trans('seven_list_sms_templates') ?></h2>
	<a href='<?= $_SERVER['PHP_SELF'] . '?action=create_sms_template'; ?>' style='margin:20px;'>
		<button type='button' id='add_sms_templates'><?= $langs->trans('seven_add_sms_template') ?></button>
	</a>
</div>
<nav aria-label='Pagination'>
	<ul class='pagination'>
		<li class='page-item'>
			<a class='page-link' href='<?= $paginationHref . $first_page ?>'>
				<?= $langs->trans('seven_pagination_first') ?>
			</a>
		</li>
		<li class='page-item'>
			<a class='page-link' href='<?= $paginationHref . $previous_page ?>'>
				<?= $langs->trans('seven_pagination_previous') ?>
			</a>
		</li>
		<?php for ($i = $start_page; $i <= $end_page; $i++) : ?>
			<li class='page-item'><a class='page-link' href='<?= $paginationHref . $i ?>'><?= $i; ?></a></li>
		<?php endfor ?>
		<li class='page-item'>
			<a class='page-link' href='<?= $paginationHref . $next_page ?>'>
				<?= $langs->trans('seven_pagination_next') ?>
			</a>
		</li>
		<li class='page-item'>
			<a class='page-link' href='<?= $paginationHref . $last_page ?>'>
				<?= $langs->trans('seven_pagination_last') ?>
			</a>
		</li>
	</ul>
</nav>

<p><?= $langs->trans('seven_pagination_total_records') ?>: <?= $total; ?></p>
<p><?= $langs->trans('seven_pagination_page') ?>: <?= $current_page; ?></p>

<table class='smsGenericTable'>
	<tr>
		<th><?= $langs->trans('seven_msg_table_title') ?></th>
		<th><?= $langs->trans('seven_msg_table_message') ?></th>
		<th><?= $langs->trans('seven_msg_table_action') ?></th>
	</tr>
	<?php foreach ($smsTemplates as $tpl): ?>
		<tr>
			<td><?= $tpl['title'] ?></td>
			<td><?= $tpl['message'] ?></td>
			<td>
				<a href='<?= $_SERVER['PHP_SELF'] . '?action=edit_sms_template&sms_template_id=' . $tpl['id'] ?>'>
					<?= $langs->trans('seven_msg_table_edit') ?>
				</a>
				<a onclick='deleteSMSTemplate(`<?= $tpl['id'] ?>`)' href='#'>
					<?= $langs->trans('seven_msg_table_delete') ?>
				</a>
			</td>
		</tr>
	<?php endforeach ?>
</table>
