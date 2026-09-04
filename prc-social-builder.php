<?php
/**
 * PRC Social Builder
 *
 * @package           PRC_Social_Builder
 * @author            Seth Rubenstein
 * @copyright         2024 Pew Research Center
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PRC Social Package Builder
 * Plugin URI:        https://github.com/pewresearch/prc-platform
 * Description:       Social media content builder for composing, previewing, and publishing social packages.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 * Author URI:        https://github.com/pewresearch
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prc-social-builder
 * Requires Plugins:  prc-scripts
 */

namespace PRC\Platform\Social_Builder;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRC_SOCIAL_BUILDER_FILE', __FILE__ );
define( 'PRC_SOCIAL_BUILDER_DIR', __DIR__ );
define( 'PRC_SOCIAL_BUILDER_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 */
function activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-activator.php';
	Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-plugin-deactivator.php';
	Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, '\PRC\Platform\Social_Builder\activate' );
register_deactivation_hook( __FILE__, '\PRC\Platform\Social_Builder\deactivate' );

/**
 * Helper utilities
 */
require plugin_dir_path( __FILE__ ) . 'includes/utils.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-message-text.php';

/**
 * The core bootstrap class that is used to define the hooks that initialize the various components.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bootstrap.php';

/**
 * Begins execution of the plugin.
 */
function run_prc_social_builder() {
	$plugin = new Bootstrap();
	$plugin->run();
}
run_prc_social_builder();
