<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
			'class'         => 'monoSpace',
			'value'         => '|arg1:token|',
			'textarea_cols' => '120',
			'textarea_rows' => '20',
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
	$data['columns'][] = array('name' => 'token', 'type' => 'varchar(1000)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'salt', 'type' => 'varchar(80)', 'NULL' => false, 'default' => '');

	$data['keys'][] = array('name' => 'user', 'columns' => 'user');
	$data['keys'][] = array('name' => 'user_enabled', 'columns' => 'user`, `enabled');
	$data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');

	api_plugin_db_table_create('tokenauth', 'plugin_tokenauth', $data);
}

function plugin_tokenauth_auth_alternate_realms() {
	global $config, $tokenauth_status;

	$tokenauth_status = plugin_tokenauth_default_status();
	$tokenauth_status['Message'] = 'Started';
	if (!isset($_SESSION['sess_user_id'])) {
		cacti_log('No user session found',false,'TOKENAUTH',POLLER_VERBOSITY_DEBUG);
		$filters = array(
			'tokenauth_id' => array(
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

		$auth_id    = get_request_var('tokenauth_id');
		$auth_token = get_request_var('tokenauth_token');

		$tokenauth_status['Authenticated'] = false;

		if ($auth_id > 0) {
			$sql = "SELECT ta.*, ua.username
				FROM plugin_tokenauth ta
				INNER JOIN user_auth ua ON ua.id = ta.user AND ua.enabled = 'on'
				WHERE ta.id = ? and ta.enabled = 'on'";

			$auth_base64 = str_replace(' ', '+', $auth_token);
			$auth_token = base64_decode($auth_base64, true);

			if ($auth_token === false) {
				cacti_log('Failed to decode supplied auth token for auth id ' . $auth_id,false,'TOKENAUTH',POLLER_VERBOSITY_MEDIUM);
				$tokenauth_status['Message'] = 'Bad token';
			} else {
				$db_data = db_fetch_row_prepared($sql, array($auth_id));
				if ($db_data !== false && sizeof($db_data)) {

					$package = date('Ymd') . $db_data['salt'] . $auth_id;

					include_once($config['include_path'] . '/vendor/phpseclib/Math/BigInteger.php');
					include_once($config['include_path'] . '/vendor/phpseclib/Crypt/Random.php');
					include_once($config['include_path'] . '/vendor/phpseclib/Crypt/Hash.php');
					include_once($config['include_path'] . '/vendor/phpseclib/Crypt/RSA.php');

					$rsa = new \phpseclib\Crypt\RSA();
					$rsa->setHash('sha256');
					if ($rsa->loadKey($db_data['token'])) {
						$verify_result = $rsa->verify($package, $auth_token);
						if ($verify_result !== false) {
							cacti_log('LOGIN: Authenticated user \'' . $db_data['username'] .
								'\' (' . $db_data['user'] .') using tokenauth ' . $db_data['id'], false, 'TOKENAUTH');
							$_SESSION['sess_user_id'] = $db_data['user'];
							$tokenauth_status['Message'] = '';
							$tokenauth_status['Authentication'] = true;
						} else {
							cacti_log('Failed to verify token for auth id ' . $auth_id,false,'TOKENAUTH', POLLER_VERBOSITY_DEBUG);
							$tokenauth_status['Message'] = 'Bad token';
							$tokenauth_status['Authentication'] = false;
						}
					} else {
						cacti_log('Failed to load key for auth id ' . $auth_id,false,'TOKENAUTH', POLLER_VERBOSITY_DEBUG);
						$tokenauth_status['Message'] = 'Bad token';
						$tokenauth_status['Authentication'] = false;
					}
				} else {
					cacti_log('Failed to find auth id ' . $auth_id,false,'TOKENAUTH', POLLER_VERBOSITY_DEBUG);
					$tokenauth_status['Message'] = 'Bad token';
					$tokenauth_status['Authentication'] = false;
				}
			}
		}
	}
}

function plugin_tokenauth_sanitize_auth_token($string) {
	$decoded = base64_decode($string);
	return $decoded === false ? '' : $string;
}

function plugin_tokenauth_default_status() {
	return array(
		'Session' => isset($_SESSION['sess_user_id']),
		'Authenticated' => false,
		'Message' => '',
	);
}
