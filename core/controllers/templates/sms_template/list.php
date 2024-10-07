<?php
extract($template_variables);
?>

<div style="display:flex;gap:20px;">
	<h2>SMS Template</h2>
	<a href="<?= $_SERVER['PHP_SELF'] . "?action=create_sms_template"; ?>" style="margin:20px;">
		<button type="button" id="add_sms_templates">Add New</button>
	</a>
</div>
<div class="bootstrap-wrapper">
	<nav aria-label="Page navigation example">
		<ul class="pagination">
			<li class="page-item"><a class="page-link"
									 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $first_page ?>">First</a></li>
			<li class="page-item"><a class="page-link"
									 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $previous_page ?>">Previous</a>
			</li>
			<?php for ($i = $start_page; $i <= $end_page; $i++) { ?>
				<li class="page-item"><a class="page-link"
										 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $i ?>"><?= $i; ?></a>
				</li>
			<?php } ?>
			<li class="page-item"><a class="page-link"
									 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $next_page ?>">Next</a></li>
			<li class="page-item"><a class="page-link"
									 href="<?= $_SERVER['PHP_SELF'] . '?pageno=' . $last_page ?>">Last</a></li>
		</ul>
	</nav>
</div>

<p>Total records : <?= $total; ?></p>
<p>Page : <?= $current_page; ?></p>

<table id="sms-template-table" class="sms-generic-table">
	<tr>
		<th>Title</th>
		<th>Message</th>
		<th>Action</th>
	</tr>

	<?php foreach ($sms_templates_list as $sms_template) { ?>
		<tr>
			<td><?= $sms_template['title'] ?></td>
			<td><?= $sms_template['message'] ?></td>
			<td>
				<a href="<?= $_SERVER['PHP_SELF'] . "?action=edit_sms_template&sms_template_id={$sms_template['id']}"; ?>">Edit</a>
				<a onclick="deleteSMSTemplate('<?= $sms_template['id'] ?>')" href="#">Delete</a>
			</td>
		</tr>
	<?php } ?>
</table>
