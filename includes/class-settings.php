<?php
/**
 * Settings management for Social Builder AI.
 *
 * Registers the admin settings page, REST endpoints, and option defaults
 * for configuring default thread counts, per-network AI instructions,
 * and system prompt overrides.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin settings via a React admin page backed by a REST API.
 *
 * @since 1.0.0
 */
class Settings {

	const OPTION_KEY      = 'prc_social_builder_settings';
	const REST_NAMESPACE  = 'prc-social-builder/v1';
	const ADMIN_PAGE_SLUG = 'prc-social-builder-settings';

	/**
	 * Default settings values.
	 *
	 * @var array<string, mixed>
	 */
	private static array $defaults = array(
		'thread_counts'        => array(
			'twitter'  => 4,
			'facebook' => 1,
			'threads'  => 4,
			'bluesky'  => 4,
			'linkedin' => 1,
		),
		'network_instructions' => array(
			'twitter'   => '',
			'facebook'  => '',
			'threads'   => '',
			'bluesky'   => '',
			'linkedin'  => '',
			'instagram' => '',
			'tiktok'    => '',
			'youtube'   => '',
		),
		'system_prompts'       => array(
			'generate-thread'  => '',
			'generate-message' => '',
			'generate-story'   => array(
				'caption'            => '',
				'overlay_text'       => '',
				'media_descriptions' => '',
			),
		),
	);

	public function __construct( Loader $loader ) {
		$loader->add_action( 'admin_menu', $this, 'register_admin_page' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	/**
	 * Returns the stored settings merged with defaults.
	 *
	 * Used by ability classes to read configured values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored_prompts = is_array( $stored['system_prompts'] ?? null ) ? $stored['system_prompts'] : array();

		// Merge flat string prompts shallowly, but deep-merge the generate-story sub-array.
		$merged_prompts                   = array_merge( self::$defaults['system_prompts'], $stored_prompts );
		$default_story                    = self::$defaults['system_prompts']['generate-story'];
		$stored_story                     = isset( $stored_prompts['generate-story'] ) && is_array( $stored_prompts['generate-story'] )
			? $stored_prompts['generate-story']
			: array();
		$merged_prompts['generate-story'] = array_merge( $default_story, $stored_story );

		return array(
			'thread_counts'        => array_merge(
				self::$defaults['thread_counts'],
				is_array( $stored['thread_counts'] ?? null ) ? $stored['thread_counts'] : array()
			),
			'network_instructions' => array_merge(
				self::$defaults['network_instructions'],
				is_array( $stored['network_instructions'] ?? null ) ? $stored['network_instructions'] : array()
			),
			'system_prompts'       => $merged_prompts,
		);
	}

	/**
	 * @hook admin_menu
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'edit.php?post_type=social-package',
			__( 'Social Builder AI Settings', 'prc-social-builder' ),
			__( 'AI Settings', 'prc-social-builder' ),
			'manage_options',
			self::ADMIN_PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page(): void {
		echo '<div class="wrap"><div id="prc-social-builder-settings-admin"></div></div>';
	}

	/**
	 * @hook admin_enqueue_scripts
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'social-package_page_' . self::ADMIN_PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = plugin_dir_path( __DIR__ ) . 'build/settings/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset  = require $asset_file;
		$handle = 'prc-social-builder-settings';

		wp_enqueue_script(
			$handle,
			plugins_url( 'build/settings/index.js', PRC_SOCIAL_BUILDER_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( plugin_dir_path( __DIR__ ) . 'build/settings/style-index.css' ) ) {
			$style_deps = array( 'wp-components' );
			if ( in_array( 'prc-components', $asset['dependencies'], true ) ) {
				$style_deps[] = 'prc-components';
			}

			wp_enqueue_style(
				$handle,
				plugins_url( 'build/settings/style-index.css', PRC_SOCIAL_BUILDER_FILE ),
				$style_deps,
				$asset['version']
			);
		}
	}

	/**
	 * @hook rest_api_init
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings_endpoint' ),
					'permission_callback' => function (): bool {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings_endpoint' ),
					'permission_callback' => function (): bool {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
	}

	public function get_settings_endpoint(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'settings'             => self::get_settings(),
				'defaults'             => $this->get_default_templates(),
				'format_instructions'  => $this->get_output_format_instructions(),
				'story_field_defaults' => $this->get_story_field_defaults(),
			)
		);
	}

	public function save_settings_endpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid payload.' ), 400 );
		}

		$sanitized = $this->sanitize_settings( $body );
		update_option( self::OPTION_KEY, $sanitized );

		return rest_ensure_response(
			array(
				'settings'             => self::get_settings(),
				'defaults'             => $this->get_default_templates(),
				'format_instructions'  => $this->get_output_format_instructions(),
				'story_field_defaults' => $this->get_story_field_defaults(),
			)
		);
	}

	/**
	 * Sanitizes and clamps incoming settings values.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $input ): array {
		$thread_counts = array();
		if ( isset( $input['thread_counts'] ) && is_array( $input['thread_counts'] ) ) {
			foreach ( self::$defaults['thread_counts'] as $platform => $default ) {
				$raw = isset( $input['thread_counts'][ $platform ] ) ? (int) $input['thread_counts'][ $platform ] : $default;
				// Facebook and LinkedIn only support single posts (no threads).
				$thread_counts[ $platform ] = in_array( $platform, array( 'facebook', 'linkedin' ), true )
					? 1
					: max( 2, min( 10, $raw ) );
			}
		} else {
			$thread_counts = self::$defaults['thread_counts'];
		}

		$network_instructions = array();
		if ( isset( $input['network_instructions'] ) && is_array( $input['network_instructions'] ) ) {
			foreach ( self::$defaults['network_instructions'] as $platform => $default ) {
				$network_instructions[ $platform ] = sanitize_textarea_field(
					(string) ( $input['network_instructions'][ $platform ] ?? $default )
				);
			}
		} else {
			$network_instructions = self::$defaults['network_instructions'];
		}

		$system_prompts = array();
		if ( isset( $input['system_prompts'] ) && is_array( $input['system_prompts'] ) ) {
			// thread and message are flat strings.
			foreach ( array( 'generate-thread', 'generate-message' ) as $key ) {
				$value                  = sanitize_textarea_field(
					(string) ( $input['system_prompts'][ $key ] ?? '' )
				);
				$system_prompts[ $key ] = '' === trim( $value ) ? '' : $value;
			}
			// story is a sub-array of per-field instruction strings.
			$raw_story                        = isset( $input['system_prompts']['generate-story'] ) && is_array( $input['system_prompts']['generate-story'] )
				? $input['system_prompts']['generate-story']
				: array();
			$system_prompts['generate-story'] = array(
				'caption'            => '' === trim( sanitize_textarea_field( (string) ( $raw_story['caption'] ?? '' ) ) )
					? ''
					: sanitize_textarea_field( (string) ( $raw_story['caption'] ?? '' ) ),
				'overlay_text'       => '' === trim( sanitize_textarea_field( (string) ( $raw_story['overlay_text'] ?? '' ) ) )
					? ''
					: sanitize_textarea_field( (string) ( $raw_story['overlay_text'] ?? '' ) ),
				'media_descriptions' => '' === trim( sanitize_textarea_field( (string) ( $raw_story['media_descriptions'] ?? '' ) ) )
					? ''
					: sanitize_textarea_field( (string) ( $raw_story['media_descriptions'] ?? '' ) ),
			);
		} else {
			$system_prompts = self::$defaults['system_prompts'];
		}

		return array(
			'thread_counts'        => $thread_counts,
			'network_instructions' => $network_instructions,
			'system_prompts'       => $system_prompts,
		);
	}

	/**
	 * Returns the default system prompt templates for thread and message abilities.
	 *
	 * Story no longer has a single template — its per-field defaults are returned
	 * separately via get_story_field_defaults().
	 *
	 * @return array<string, string>
	 */
	private function get_default_templates(): array {
		$ai_dir = plugin_dir_path( __DIR__ ) . 'includes/ai/';

		$templates = array();

		if ( ! class_exists( Generate_Thread_Ability::class ) ) {
			require_once $ai_dir . 'class-generate-thread-ability.php';
		}
		if ( ! class_exists( Generate_Message_Ability::class ) ) {
			require_once $ai_dir . 'class-generate-message-ability.php';
		}

		if ( class_exists( Generate_Thread_Ability::class ) ) {
			$templates['generate-thread'] = Generate_Thread_Ability::get_default_system_prompt_template();
		}
		if ( class_exists( Generate_Message_Ability::class ) ) {
			$templates['generate-message'] = Generate_Message_Ability::get_default_system_prompt_template();
		}

		return $templates;
	}

	/**
	 * Returns the locked output format instruction string for each ability.
	 *
	 * These are always appended at runtime and cannot be overridden via settings.
	 *
	 * @return array<string, string>
	 */
	private function get_output_format_instructions(): array {
		$ai_dir = plugin_dir_path( __DIR__ ) . 'includes/ai/';

		if ( ! class_exists( Generate_Thread_Ability::class ) ) {
			require_once $ai_dir . 'class-generate-thread-ability.php';
		}
		if ( ! class_exists( Generate_Message_Ability::class ) ) {
			require_once $ai_dir . 'class-generate-message-ability.php';
		}
		if ( ! class_exists( Generate_Story_Ability::class ) ) {
			require_once $ai_dir . 'class-generate-story-ability.php';
		}

		return array(
			'generate-thread'  => class_exists( Generate_Thread_Ability::class )
				? Generate_Thread_Ability::get_output_format_instruction()
				: '',
			'generate-message' => class_exists( Generate_Message_Ability::class )
				? Generate_Message_Ability::get_output_format_instruction()
				: '',
			'generate-story'   => class_exists( Generate_Story_Ability::class )
				? Generate_Story_Ability::get_output_format_instruction()
				: '',
		);
	}

	/**
	 * Returns the default per-field instruction strings for the story ability.
	 *
	 * @return array<string, string>
	 */
	private function get_story_field_defaults(): array {
		$ai_dir = plugin_dir_path( __DIR__ ) . 'includes/ai/';

		if ( ! class_exists( Generate_Story_Ability::class ) ) {
			require_once $ai_dir . 'class-generate-story-ability.php';
		}

		if ( ! class_exists( Generate_Story_Ability::class ) ) {
			return array(
				'caption'            => '',
				'overlay_text'       => '',
				'media_descriptions' => '',
			);
		}

		return array(
			'caption'            => Generate_Story_Ability::get_default_caption_instruction(),
			'overlay_text'       => Generate_Story_Ability::get_default_overlay_instruction(),
			'media_descriptions' => Generate_Story_Ability::get_default_media_descriptions_instruction(),
		);
	}
}
