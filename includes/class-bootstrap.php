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
	/**
	 * The plugin loader.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Set up the plugin loader and dependencies.
	 */
	public function __construct() {
		$this->version     = '1.0.0';
		$this->plugin_name = 'prc-social-builder';

		$this->load_dependencies();
		$this->init_dependencies();
	}

	/**
	 * Load plugin class files and create the loader.
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-loader.php';
		$this->loader = new Loader();
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-assets.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-content-type.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-hootsuite.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-settings.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/admin-surfaces/class-admin-surfaces.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/admin-surfaces/class-acp-column.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/admin-surfaces/class-dataviews-provider.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-admin-dataview-lists.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/class-nexus-system.php';
	}

	/**
	 * Instantiate plugin services and register hooks.
	 */
	private function init_dependencies() {
		new Assets( $this->get_loader() );
		new Content_Type( $this->get_loader() );
		new Hootsuite( $this->get_loader() );
		new Settings( $this->get_loader() );
		new Admin_Surfaces( $this->get_loader() );
		new ACP_Column( $this->get_loader() );
		new DataViews_Provider( $this->get_loader() );
		new Admin_Dataview_Lists( $this->get_loader() );
		$this->loader->add_action( 'init', $this, 'register_blocks' );
		add_action( 'plugins_loaded', array( $this, 'register_wp_ai_features' ), 11 );
		add_action(
			'prc_nexus_systems',
			static function ( $systems ): void {
				if ( ! $systems instanceof \PRC\Platform\Slack\Nexus\Systems ) {
					return;
				}
				( new Nexus_System() )->register( $systems );
			}
		);
	}

	/**
	 * Register Social Builder blocks from build metadata.
	 */
	public function register_blocks(): void {
		$blocks_dir = plugin_dir_path( __DIR__ ) . 'build/';
		$blocks     = array( 'container', 'message', 'story' );
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
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-editorial-passes.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-number-check.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-generate-story-ability.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-generate-social-copy-ability.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-add-social-package-ability.php';
		require_once plugin_dir_path( __DIR__ ) . '/includes/ai/class-social-builder-ai-feature.php';

		add_action(
			'wpai_register_features',
			function ( $registry ) {
				$registry->register_feature( new Social_Builder_AI_Feature() );
			}
		);
	}

	/**
	 * Run the plugin loader.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the plugin slug.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get the plugin loader.
	 *
	 * @return Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
