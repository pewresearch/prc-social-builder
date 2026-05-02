<?php
/**
 * Bootstrap class.
 *
 * @package    PRC\Platform\Social_Builder
 */

namespace PRC\Platform\Social_Builder;

use WP_Error;

/**
 * Bootstrap class.
 *
 * @package    PRC\Platform\Social_Builder
 */
class Bootstrap {
	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->version     = '1.0.0';
		$this->plugin_name = 'prc-social-builder';

		$this->load_dependencies();
		$this->init_dependencies();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-loader.php';
		$this->loader = new Loader();
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-assets.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-content-type.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-hootsuite.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-settings.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/admin-surfaces/class-admin-surfaces.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/admin-surfaces/class-acp-column.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/admin-surfaces/class-admin-bar.php';
	}

	private function init_dependencies() {
		new Assets( $this->get_loader() );
		new Content_Type( $this->get_loader() );
		new Hootsuite( $this->get_loader() );
		new Settings( $this->get_loader() );
		new Admin_Surfaces( $this->get_loader() );
		new Admin_Bar( $this->get_loader() );
		new ACP_Column( $this->get_loader() );
		$this->loader->add_action( 'init', $this, 'register_blocks' );
		add_action( 'plugins_loaded', array( $this, 'register_wp_ai_features' ), 11 );
	}

	public function register_blocks(): void {
		$blocks_dir = plugin_dir_path( __DIR__ ) . 'build/';
		$blocks     = array( 'thread', 'message', 'story' );
		foreach ( $blocks as $block ) {
			$block_path = $blocks_dir . $block;
			if ( file_exists( $block_path ) ) {
				register_block_type_from_metadata( $block_path );
			}
		}
	}

	/**
	 * Load Social Builder AI classes and register the feature with the WP AI plugin.
	 */
	public function register_wp_ai_features(): void {
		if ( ! class_exists( '\WordPress\AI\Abstracts\Abstract_Feature' ) ) {
			return;
		}
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-generate-thread-ability.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-generate-message-ability.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-generate-story-ability.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-social-builder-ai-feature.php';

		add_action(
			'wpai_register_features',
			function ( $registry ) {
				$registry->register_feature( new Social_Builder_AI_Feature() );
			}
		);
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
