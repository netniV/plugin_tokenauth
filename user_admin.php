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

$tokenauth_actions = array(
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
	global $actions, $tokenauth_actions;


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
	global $config;

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

		if (!empty($save['token'])) {
			include_once($config['include_path'] . '/vendor/phpseclib/Math/BigInteger.php');
			include_once($config['include_path'] . '/vendor/phpseclib/Crypt/Random.php');
			include_once($config['include_path'] . '/vendor/phpseclib/Crypt/Hash.php');
			include_once($config['include_path'] . '/vendor/phpseclib/Crypt/RSA.php');

			$rsa = new \phpseclib\Crypt\RSA();
			if (!$rsa->loadKey($save['token'])) {
				$save['token'] = '';
			}
		}

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
		$sql_token = "SELECT ta.id, ta.salt, ta.token, ta.user,
			      if (ua.username is null, CONCAT('Missing User ID: ',ta.user), ua.username) as username
			      FROM plugin_tokenauth ta
			      LEFT JOIN user_auth ua ON ua.id = ta.user
			      WHERE ta.id = ?";
		$token = db_fetch_row_prepared($sql_token, array($id));

	        $header_label = __('Token Authentication [edit: %s]', $token['username'], 'tokenauth');
	} else {
	        $header_label = __('Token Authentication [new]', 'tokenauth');
	}

	form_start('user_admin.php', 'tokenauth');

	html_start_box(htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_tokenauth, $token),
		)
	);
	html_end_box();

	form_hidden_box('id', (isset($token['id']) ? $token['id'] : '0'), '');
	form_hidden_box('save_component', '1', '');

	form_save_button('user_admin.php', 'return');
}

function tokenauth_listview() {
	global $config, $tokenauth_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'username',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
	);

	validate_store_request_vars($filters, 'sess_tokenauth');
	/* ================= input validation ================= */

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_admin.php?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_admin.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_user_admin').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Token Authentication Management'), '100%', '', '3', 'center', 'user_admin.php?action=edit');

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<tr class='even'>
		<td>
		<form id='form_user_admin' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Token Authentications');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (ua.username LIKE '%" . get_request_var('filter') . "%' OR ua.full_name LIKE '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(ta.id)
		FROM plugin_tokenauth ta
		LEFT JOIN user_auth ua
		ON ua.id = ta.user
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql_query = "SELECT ta.id,
		if(ta.enabled<>'on','No - Token Auth Disabled', if(ua.enabled<>'on','No - User Disabled',if(ta.token>'','Yes', 'No - Token Not Set'))) enabled,
		if(ta.salt>'','Yes','No') salt,
		if(ta.token>'','Yes','No') token,
		ta.user, ua.username, ua.full_name
		FROM plugin_tokenauth ta
		LEFT JOIN user_auth ua ON (ua.id = ta.user)
		$sql_where
		GROUP BY id
		$sql_order
		$sql_limit";

	$tokens = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('user_admin.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 9, __('Token Authentications'), 'page', 'main');

	form_start('user_admin.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'id_nosort' => array(__('ID'), 'ASC'),
		'username'  => array(__('User Name'), 'ASC'),
		'enabled'   => array(__('Enabled'), 'ASC'),
		'salt'      => array(__('Has Salt'), 'ASC'),
		'token'     => array(__('Has Token'), 'ASC'),
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if ($tokens !== false && sizeof($tokens)) {
		foreach ($tokens as $token) {
			form_alternate_row('line' . $token['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('user_admin.php?action=edit&id=' . $token['id']) . '">' . $token['id'] . '</a>', $token['id']);
			form_selectable_cell(filter_value($token['username'], get_request_var('filter'), $config['url_path'] . 'plugins/tokenauth/user_admin.php?action=edit&id=' . $token['id']), $token['id']);
			form_selectable_cell(filter_value($token['enabled'], get_request_var('filter')), $token['id']);
			form_selectable_cell(filter_value($token['salt'], get_request_var('filter')), $token['id']);
			form_selectable_cell(filter_value($token['token'], get_request_var('filter')), $token['id']);
			form_checkbox_cell($token['id'], $token['id']);
			form_end_row();
		}
	}else{
		print "<tr><td colspan='6'><em>" . __('No Token Authentications') . "</em></td></tr>\n";
	}

	html_end_box(false);

	form_hidden_box('save_list', '1', '');

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($tokenauth_actions);

	form_end();
}
