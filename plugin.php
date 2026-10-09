<?php // phpcs:ignore Generic.Commenting.DocComment.MissingShort
/**
 * @wordpress-plugin
 * Plugin Name:       Outstand Term Selector
 * Description:       Use the hierarchical (checkbox tree) term selector for non-hierarchical taxonomies.
 * Plugin URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-term-selector&utm_campaign=plugin-uri
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Version:           1.0.1
 * Author:            Outstand
 * Author URI:        https://outstand.site/?utm_source=wp-plugins&utm_medium=outstand-term-selector&utm_campaign=author-uri
 * License:           GPL-3.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-3.0-or-later.html
 * Update URI:        https://outstand.site/
 * GitHub Plugin URI: https://github.com/pixelalbatross/outstand-term-selector
 * Text Domain:       outstand-term-selector
 * Domain Path:       /languages
 */

namespace Outstand\WP\TermSelector;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'OUTSTAND_TERM_SELECTOR_VERSION', '1.0.1' );
define( 'OUTSTAND_TERM_SELECTOR_BASENAME', plugin_basename( __FILE__ ) );
define( 'OUTSTAND_TERM_SELECTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'OUTSTAND_TERM_SELECTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'OUTSTAND_TERM_SELECTOR_DIST_URL', OUTSTAND_TERM_SELECTOR_URL . 'build/' );
define( 'OUTSTAND_TERM_SELECTOR_DIST_PATH', OUTSTAND_TERM_SELECTOR_PATH . 'build/' );

if ( file_exists( OUTSTAND_TERM_SELECTOR_PATH . 'vendor/autoload.php' ) ) {
	require_once OUTSTAND_TERM_SELECTOR_PATH . 'vendor/autoload.php';
}

if ( class_exists( PucFactory::class ) ) {
	PucFactory::buildUpdateChecker(
		'https://github.com/pixelalbatross/outstand-term-selector/',
		__FILE__,
		'outstand-term-selector'
	)->setBranch( 'main' );
}

add_action(
	'plugins_loaded',
	function () {
		Plugin::get_instance()->enable();
	}
);
