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

function plugin_tokenauth_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/tokenauth/INFO', true);
	return $info['info'];
}

function plugin_tokenauth_install() {
	api_plugin_register_hook('tokenauth', 'config_arrays', 'plugin_tokenauth_config_arrays', 'setup.php');
	api_plugin_register_hook('tokenauth', 'auth_alternate_realms', 'plugin_tokenauth_auth_alternate_realms', 'setup.php');

	plugin_tokenauth_setup_table();
}

function plugin_tokenauth_uninstall() {

}

function plugin_tokenauth_check_config() {
	return true;
}

function plugin_tokenauth_upgrade() {
	return false;
}

function plugin_tokenauth_config_arrays() {
	global $menu, $fields_tokenauth;
	$menu[__('Utilities')]['plugins/tokenauth/user_admin.php'] = __('Token Auth', 'tokenauth');

	$fields_tokenauth = array(
		'tokenauth_header' => array(
			'friendly_name' => 'Token Authentication',
			'method'        => 'spacer',
		),
		'enabled' => array(
			'friendly_name' => __('Enabled'),
			'description'   => __('Check this Checkbox if you wish this Token Authentication to be enabled.', 'tokenauth'),
			'value'         => '|arg1:enabled|',
			'default'       => 'on',
			'method'        => 'checkbox',
		),
		'user' => array(
			'method'        => 'drop_sql',
			'friendly_name' => __('Username'),
			'description'   => __('The user that this Token Authentication is associated to.', 'tokenauth'),
			'value'         => '|arg1:user|',
			'sql'           => 'SELECT id, username AS name FROM user_auth ORDER BY name',
			'default'       => '0',
		),
		'salt' => array(
			'method'        => 'textbox',
			'friendly_name' => __('Salt', 'tokenauth'),
			'description'   => __('The string that is combined with the user data to verify a signed code'),
			'value'         => '|arg1:salt|',
			'max_length'    => '80',
			'size'          => '80',
			'default'       => '',
		),
		'token' => array(
			'method'        => 'textarea',
			'friendly_name' => __('Token', 'tokenauth'),
			'description'   => __('The public RSA token that is used to verify the signed code'),
			'value'         => '|arg1:token|',
                        'textarea_cols' => '80',
			'textarea_rows' => '5',
		),
	);

}

function plugin_tokenauth_setup_table() {
	$data = array();
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Token Authentication';

	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'user', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'enabled', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'token', 'type' => 'varchar(300)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'salt', 'type' => 'varchar(80)', 'NULL' => false, 'default' => '');

	$data['keys'][] = array('name' => 'user', 'columns' => 'user');
	$data['keys'][] = array('name' => 'user_enabled', 'columns' => 'user`, `enabled');
	$data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');

	api_plugin_db_table_create('tokenauth', 'plugin_tokenauth', $data);
}

function plugin_tokenauth_auth_alternate_realms() {
	global $config;

	$filters = array(
		'tokenauth_userid' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0',
			),
		'tokenauth_token' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'plugin_tokenauth_sanitize_auth_token')
			)
	);

	validate_store_request_vars($filters);

	$user_id    = get_request_var('tokenauth_userid');
	$user_token = get_request_var('tokenauth_token');

	if ($user_id > 0) {
		$sql = "SELECT ta.*
			FROM plugin_tokenauth ta
			INNER JOIN ua
			ON ua.id = ta.user AND ua.enabled = 'on'
			WHERE ta.user = ? and ta.enabled = 'on'";

		$db_data = db_fetch_row_prepared($sql, array($user_id));
		if ($db_data !== false) {
			include_once($config['include_path'] . '/vendor/phpseclib/Math/BigInteger.php');
			include_once($config['include_path'] . '/vendor/phpseclib/Crypt/Random.php');
			include_once($config['include_path'] . '/vendor/phpseclib/Crypt/Hash.php');
			include_once($config['include_path'] . '/vendor/phpseclib/Crypt/RSA.php');

			$rsa = new \phpseclib\Crypt\RSA();
			if ($rsa->loadKey($db_data['token'])) {
				if ($rsa->verify(date('Ymd') . $db_data['salt'] . $user_id, base64_decode($user_token))) {
					$_SESSION['sess_user_id'] = $user_id;
				}
			}
		}
	}
}
