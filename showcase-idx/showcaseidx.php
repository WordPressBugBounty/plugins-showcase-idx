<?php
/**
 * Plugin Name: Showcase IDX
 * Plugin URI: https://showcaseidx.com/
 * Description: Interactive, responsive, SEO-friendly real estate IDX property search.
 * Author: Showcase IDX
 * Author URI: https://showcaseidx.com/
 * Version: 3.2.11
 */

// NOTE: documentation for the above header block is at https://developer.wordpress.org/plugins/plugin-basics/header-requirements/


add_option( 'showcaseidx_product_version', get_option( 'showcaseidx_api_key' ) != '' ? '2' : '3' );

define('SHOWCASEIDX_ACCOUNT_STATUS_OK',      'ok');
define('SHOWCASEIDX_ACCOUNT_STATUS_REVOKED', 'revoked');
define('SHOWCASEIDX_ACCOUNT_STATUS_EXPIRED', 'expired');
define('SHOWCASEIDX_ACCOUNT_STATUS_INVALID', 'invalid');
define('SHOWCASEIDX_SEARCH_HOST', 'https://search.showcaseidx.com');
define('SHOWCASEIDX_AGENT_HOST', 'https://admin.showcaseidx.com');

if ( get_option( 'showcaseidx_product_version' ) != '3' ) {
  require_once(dirname(__FILE__) . '/2/showcaseidx.php');
} else {
  require_once(dirname(__FILE__) . '/3/install.php');

  if ( get_option( 'showcaseidx_website_uuid' ) != '' ) {
    require_once(dirname(__FILE__) . '/3/resources.php');
    require_once(dirname(__FILE__) . '/3/workarounds.php');
    require_once(dirname(__FILE__) . '/3/seo.php');
    require_once(dirname(__FILE__) . '/3/page.php');
    require_once(dirname(__FILE__) . '/3/shortcodes.php');
    require_once(dirname(__FILE__) . '/3/routes.php');
  }

  require_once(dirname(__FILE__) . '/3/admin.php');

  register_activation_hook( __FILE__, 'showcaseidx_plugin_activation' );
  register_deactivation_hook( __FILE__, 'showcaseidx_plugin_deactivation' );
}
