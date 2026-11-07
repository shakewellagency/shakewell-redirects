<?php
/**
 * Plugin Name:       Shakewell Redirects
 * Plugin URI:        https://github.com/shakewellagency/shakewell-redirects
 * Description:       Manage redirects in wp-admin, serve them on WordPress and expose them to headless front ends over REST.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Shakewell
 * Author URI:        https://shakewell.agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shakewell-redirects
 *
 * @package Shakewell\Redirects
 */

namespace Shakewell\Redirects;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.1';
const FILE    = __FILE__;

foreach ( array( 'Rules', 'Store', 'Importer', 'Rest', 'Cli', 'Admin', 'Plugin' ) as $shakewell_redirects_class ) {
	require_once __DIR__ . '/src/' . $shakewell_redirects_class . '.php';
}

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
