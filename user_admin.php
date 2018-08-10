<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/auth.php');

$actions = array(
	1 => __('Enable'),
	2 => __('Disable'),
	3 => __('Delete')
);


set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();
		break;
	case 'actions':
		form_actions();
		break;
	case 'edit':
		top_header();
		tokenauth_edit();
		bottom_footer();
		break;
	case 'view':
		top_header();
		tokenauth_view();
		bottom_footer();
		break;
	default:
		top_header();
		tokenauth_listview();
		bottom_footer();
		break;
}

function form_actions() {
	global $actions, $assoc_actions;


	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ================= input validation ================= */



	$selected_items = array();
	if (isset_request_var('save_list')) {
		/* loop through each of the lists selected on the previous page and get more info about them */
		while (list($var,$val) = each($_POST)) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				$selected_items[] = $matches[1];
			}
		}

	/* if we are to save this form, instead of display it */
		if (isset_request_var('save_list')) {
			if (get_request_var('drp_action') == '1' || /* enable */
			    get_request_var('drp_action') == '2') { /* disable */
				tokenauth_enable($selected_items, get_request_var('drp_action') == 1);
			}elseif (get_request_var('drp_action') == '3') { /* delete */
				tokenauth_delete($selected_items);
			}
			header('Location: user_admin.php?header=false');
			exit;
		}
	}
}


function form_save() {

	if (isset_request_var('save_component')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('user');

		/* ==================================================== */
		$save = array();
		$save['id']      = get_nfilter_request_var('id');
		$save['user']    = get_nfilter_request_var('user');
		$save['salt']    = get_nfilter_request_var('salt');
		$save['token']   = get_nfilter_request_var('token');
		$save['enabled'] = get_nfilter_request_var('enabled');

		if (!is_error_message()) {
			$id = sql_save($save, 'plugin_tokenauth');
			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: user_admin.php?header=false');
		exit;
	}
}


function tokenauth_enable($selected_items, $enabled = false) {
	if (!empty($selected_items)) {
		foreach($selected_items as $id) {
			$stime = time();
			db_execute_prepared('UPDATE plugin_tokenauth SET enabled = ? WHERE id = ? LIMIT 1', array(($enabled?'on':''), $id));
		}
	}

	header('Location: user_admin.php?header=false');
	exit;
}

function tokenauth_delete($selected_items) {
	if (!empty($selected_items)) {
		foreach($selected_items as $id) {
			db_execute_prepared('DELETE FROM plugin_tokenauth WHERE id = ? LIMIT 1', array($id));
		}
	}

	header('Location: user_admin.php?header=false');
	exit;
}

function tokenauth_edit() {
	global $config, $fields_tokenauth;

	/* ================= input validation ================= */
	$id = get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$token = db_fetch_assoc('SELECT ta.*
					 FROM plugin_tokenauth ta
					 WHERE ta.id = ?', array($id));

	        $header_label = __('Token Authentication [edit: %s]', $token['username'], 'tokenauth');
	} else {
	        $header_label = __('Token Authentication [new]', 'tokenauth');
	}

	form_start('user_admin.php', 'tokenauth');

	html_start_box(htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_tokenauth, (isset($token) ? $token : array())),
		)
	);
	html_end_box();

	form_hidden_box('id', (isset($token['id']) ? $token['id'] : '0'), '');
	form_hidden_box('save_component', '1', '');

	form_save_button('user_admin.php', 'return');


}

function tokenauth_listview() {
	global $actions, $refresh;
	$tokens = db_fetch_assoc('SELECT ta.*, ua.username FROM plugin_tokenauth ta
		LEFT JOIN user_auth ua
		ON ua.id = ta.user
		ORDER BY ua');

	form_start('user_admin.php', 'chk');

	html_start_box(__('Data Source Debugger'), '100%', '', '4', 'center', 'user_admin.php?action=edit');

	html_header_checkbox(array(__('ID'), __('User'), __('Enabled')));

	if (sizeof($tokens)) {
		foreach ($tokens as $token) {
			form_alternate_row('line' . $check['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('user_admin.php?action=view&id=' . $check['id']) . '">' . $id . '</a>', $check['id']);
			form_selectable_cell($check['username'], $check['id']);
			form_selectable_cell($check['enabled'] ? 'Yes' : 'No');
			form_checkbox_cell($check['id'], $check['id']);
			form_end_row();
		}
	}else{
		print "<tr><td colspan='4'><em>" . __('No Checks') . "</em></td></tr>\n";
	}

	html_end_box(false);

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}
