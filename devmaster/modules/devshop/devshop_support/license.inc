<?php

/**
 * Wrapper for devshop_support_network_client_post_data();
 */
function devshop_support_check_license() {
  devshop_support_network_client_post_data();
}

/**
 * Post server data to devshop.support.
 *
 * @return object
 */
function devshop_support_network_client_post_data($license_key = NULL) {

  if (!$license_key) {
    $license_key = variable_get('devshop_support_license_key', NULL);
    if (!$license_key) {
      return;
    }
  }

  if ($license_key == 'automated_testing_license_key') {
    return TRUE;
  }

  $get_support_url = variable_get('devshop_support_api_url', 'https://devshop.support');

  $options = array('method' => 'POST');
  $options['data'] = http_build_query(devshop_support_network_client_client_data());
  $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';

  $devshop_hostname = $_SERVER['HTTP_HOST'];
  $response = drupal_http_request($get_support_url . '/network/server/' . $devshop_hostname . "/" . $license_key, $options);
  $response_data = json_decode($response->data);
//  drupal_set_message($get_support_url . '/network/server/' . $devshop_hostname . "/" . $license_key);

  watchdog('devshop_support_api', 'DevShop Support License Request ' . ($response->code == 200? 'Success: ': 'Failure: ') . print_r($response_data, 1 ));


  // Save raw data to a variable.
  variable_set('devshop_support_license_raw_data', (array) $response_data);

  // Set all arbitrary variables
  $variables = devshop_support_network_default_variables();

  if (!empty($response_data->_VARIABLES)) {
    $variables = array_merge($variables, (array) $response_data->_VARIABLES);
  }

  if (!empty($variables)) {
    foreach ($variables as $name => $value) {

      if (is_object($value)) {
        $value = (array) $value;
      }
      variable_set($name, $value);
    }
  }

  // Enable needed modules.
  module_enable(array(
    'cas',
    'intercomio',
    'devshop_permissions',
    'composer_manager',
    'hosting_statsd',
  ));

  // Enable HTTPS on the hostmaster site.
  $node = hosting_context_load('hostmaster');
  if (module_exists('hosting_https') && $node->https_enabled != HOSTING_HTTPS_REQUIRED) {
    watchdog('devshop_support_api', 'Hostmaster HTTPS was not enabled. Enabling.');
    $node->https_enabled = variable_get('DEVSHOP_SUPPORT_DEFAULT_HOSTING_HTTPS_ENABLED', HOSTING_HTTPS_REQUIRED);
    node_save($node);
  }

  drupal_flush_all_caches();
//
//  // Don't do any more for certain hostnames.
//  if ($devshop_hostname == 'devshop.local.computer') {
//    return $response;
//  }

  // Create all CAS users.
  module_load_include('batch.inc', 'cas');

  // Always keep uid 1 active.
  $active_local_uids = array(1);
  $mapped_accounts = array();
  $mapped_account_names = array();

  // Payload comes back an array of CAS usernames.
  // @TODO: We need to retrieve email here as well to ensure intercom sync.
  // @TODO: Here's where we could sync SSH keys.
  foreach ($response_data->_USERS as $cas_user) {

    // Let's prevent admin from being set here.
    if ($cas_user == 'admin') {
      continue;
    }

    // Try to create a CAS account. Load if not created.
    // @TODO: cas_user_register() returns false if user already exists.
    $account = cas_user_register($cas_user);

    if (empty($account)) {
      $account = cas_user_load_by_name($cas_user, FALSE, TRUE);
    }

    // if there is an account found, with a UID, save to $active_local_uids.
    if ($account && is_numeric($account->uid)){
      $active_local_uids[] = $account->uid;
      $mapped_accounts[] = $account;
      $mapped_account_names[] = $account->name;
    }
  }

  // Disable all users not included in the license payload.
  db_update('users')
    ->fields(array(
      'status' => 0
    ))
    ->condition('uid', $active_local_uids, 'NOT IN')
    ->execute();

  // Disable all users not included in the license payload.
  db_update('users')
    ->fields(array(
      'status' => 1
    ))
    ->condition('uid', $active_local_uids, 'IN')
    ->execute();

  variable_set('devshop_support_license_key', $license_key);

  watchdog('devshop_support_api', 'Loaded !count devshop.support users: !users', array(
    '!count' => count($active_local_uids),
    '!users' => implode(' ', $mapped_account_names),
  ));

  return $response;
}
