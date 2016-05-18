<?php

require_once dirname(__FILE__).'/../../lib/database/attendee.php';
require_once dirname(__FILE__).'/../../lib/util/util.php';
require_once dirname(__FILE__).'/../../lib/util/cmlists.php';
require_once dirname(__FILE__).'/../admin.php';

cm_admin_check_permission('attendee-blacklist', 'attendee-blacklist');

$atdb = new cm_attendee_db($db);

$list_def = array(
	'ajax-url' => get_site_url(false) . '/admin/attendee/blacklist.php',
	'entity-type' => 'blacklist entry',
	'entity-type-pl' => 'blacklist entries',
	'search-criteria' => 'name or contact info',
	'columns' => array(
		array(
			'name' => 'Real Name',
			'key' => 'real-name',
			'type' => 'text'
		),
		array(
			'name' => 'Fandom Name',
			'key' => 'fandom-name',
			'type' => 'text'
		),
		array(
			'name' => 'Email Address',
			'key' => 'email-address',
			'type' => 'email'
		),
		array(
			'name' => 'Phone Number',
			'key' => 'phone-number',
			'type' => 'text'
		),
		array(
			'name' => 'Added/Approved By',
			'key' => 'added-by',
			'type' => 'text'
		),
	),
	'sort-order' => array(0),
	'row-key' => 'id',
	'name-key' => 'real-name',
	'row-actions' => array('edit', 'delete'),
	'table-actions' => array('add'),
	'add-title' => 'Add Blacklist Entry',
	'edit-title' => 'Edit Blacklist Entry',
	'delete-title' => 'Delete Blacklist Entry'
);
$list_def['edit-clear-function'] = <<<END
	function() {
		$('#ea-first-name').val('');
		$('#ea-last-name').val('');
		$('#ea-fandom-name').val('');
		$('#ea-email-address').val('');
		$('#ea-phone-number').val('');
		$('#ea-added-by').val('');
	}
END;
$list_def['edit-load-function'] = <<<END
	function(id, e) {
		$('#ea-first-name').val(e['first-name']);
		$('#ea-last-name').val(e['last-name']);
		$('#ea-fandom-name').val(e['fandom-name']);
		$('#ea-email-address').val(e['email-address']);
		$('#ea-phone-number').val(e['phone-number']);
		$('#ea-added-by').val(e['added-by']);
	}
END;
$list_def['edit-save-function'] = <<<END
	function(id, e) {
		return {
			'first-name': $('#ea-first-name').val(),
			'last-name': $('#ea-last-name').val(),
			'fandom-name': $('#ea-fandom-name').val(),
			'email-address': $('#ea-email-address').val(),
			'phone-number': $('#ea-phone-number').val(),
			'added-by': $('#ea-added-by').val()
		};
	}
END;

if (isset($_POST['cm-list-action'])) {
	header('Content-type: text/plain');
	switch ($_POST['cm-list-action']) {
		case 'list':
			$blacklist_entries = $atdb->list_blacklist_entries();
			$response = cm_list_process_entities($list_def, $blacklist_entries);
			echo json_encode($response);
			break;
		case 'create':
			$blacklist_entry = json_decode($_POST['cm-list-entity'], true);
			$id = $atdb->create_blacklist_entry($blacklist_entry);
			$ok = ($id !== false);
			$response = array('ok' => $ok);
			if ($ok) {
				$blacklist_entry = $atdb->get_blacklist_entry($id);
				if ($blacklist_entry) {
					$response['row'] = cm_list_make_row($list_def, $blacklist_entry);
				}
			}
			echo json_encode($response);
			break;
		case 'update':
			$blacklist_entry = json_decode($_POST['cm-list-entity'], true);
			$blacklist_entry['id'] = $_POST['cm-list-key'];
			$ok = $atdb->update_blacklist_entry($blacklist_entry);
			$response = array('ok' => $ok);
			if ($ok) {
				$blacklist_entry = $atdb->get_blacklist_entry($blacklist_entry['id']);
				if ($blacklist_entry) {
					$response['row'] = cm_list_make_row($list_def, $blacklist_entry);
				}
			}
			echo json_encode($response);
			break;
		case 'delete':
			$id = $_POST['cm-list-key'];
			$ok = $atdb->delete_blacklist_entry($id);
			$response = array('ok' => $ok);
			echo json_encode($response);
			break;
	}
	exit(0);
}

cm_admin_head('Attendee Blacklist');
cm_list_head($list_def);
cm_admin_body('Attendee Blacklist');
cm_admin_nav('attendee-blacklist');

echo '<article>';
cm_list_search_box($list_def);
cm_list_table($list_def);
echo '</article>';

cm_admin_dialogs();
cm_list_edit_dialog_start();

echo '<table border="0" cellpadding="0" cellspacing="0" class="cm-form-table">';
	echo '<tr>';
		echo '<th><label for="ea-first-name">First Name:</label></th>';
		echo '<td><input type="text" name="ea-first-name" id="ea-first-name"></td>';
	echo '</tr>';
	echo '<tr>';
		echo '<th><label for="ea-last-name">Last Name:</label></th>';
		echo '<td><input type="text" name="ea-last-name" id="ea-last-name"></td>';
	echo '</tr>';
	echo '<tr>';
		echo '<th><label for="ea-fandom-name">Fandom Name:</label></th>';
		echo '<td><input type="text" name="ea-fandom-name" id="ea-fandom-name"></td>';
	echo '</tr>';
	echo '<tr>';
		echo '<th><label for="ea-email-address">Email Address:</label></th>';
		echo '<td><input type="email" name="ea-email-address" id="ea-email-address"></td>';
	echo '</tr>';
	echo '<tr>';
		echo '<th><label for="ea-phone-number">Phone Number:</label></th>';
		echo '<td><input type="text" name="ea-phone-number" id="ea-phone-number"></td>';
	echo '</tr>';
	echo '<tr>';
		echo '<th><label for="ea-added-by">Added/Approved By:</label></th>';
		echo '<td><input type="text" name="ea-added-by" id="ea-added-by"></td>';
	echo '</tr>';
echo '</table>';

cm_list_edit_dialog_end();
cm_list_dialogs($list_def);
cm_admin_tail();