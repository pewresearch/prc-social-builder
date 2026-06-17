<?php
/**
 * Generate Message ability.
 *
 * Uses AI to generate three social message options for a post.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and executes prc-social-builder/generate-message.
 *
 * @since 1.0.0
 */
class Generate_Message_Ability {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public static $ability_name = 'prc-social-builder/generate-message';

	/**
	 * @var array<int, string>
	 */
	public static $allowed_blocks = array();

	const OPTION_COUNT = 3;

	/**
	 * The default system prompt template.
	 *
	 * Supports placeholders: {{platform}}, {{max_length}}, {{option_count}}.
	 * The output format instruction is always appended separately via get_output_format_instruction().
	 */
	public static function get_default_system_prompt_template(): string {
		return 'You are a social media editor. Platform: {{platform}}. Each message must be at most {{max_length}} characters.

Produce exactly {{option_count}} meaningfully different angles. Do not use @ handles or hashtags unless essential.';
	}

	/**
	 * The hard-coded output format instruction.
	 *
	 * Always appended to the final system instruction regardless of any admin override.
	 * This cannot be changed via the settings UI.
	 */
	public static function get_output_format_instruction(): string {
		return 'CRITICAL: Return ONLY a JSON array of exactly ' . self::OPTION_COUNT . ' objects. Each object must have:
- "content" (string, the message text)
- "linkUrl" (optional string, a single URL or omit)

Do not use markdown fences or extra prose. Example:
[{"content":"First option"},{"content":"Second option","linkUrl":"https://example.org"},{"content":"Third option"}]';
	}

	/**
	 * @hook wp_abilities_api_init
	 */
	public function register_ability(): void {
		wp_register_ability(
			self::$ability_name,
			array(
				'label'               => __( 'Generate Social Builder Messages', 'prc-social-builder' ),
				'description'         => __( 'Uses AI to generate three social message options from post content.', 'prc-social-builder' ),
				'category'            => 'communication',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'postId'   => array(
							'type'        => 'number',
							'description' => 'Post ID for context.',
						),
						'platform' => array(
							'type'        => 'string',
							'description' => 'Target platform key.',
						),
						'tone'     => array(
							'type'        => 'string',
							'description' => 'Optional tone guidance.',
						),
						'context'  => array(
							'type'        => 'string',
							'description' => 'Optional extra context for generation.',
						),
					),
					'required'             => array( 'postId', 'platform' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'options' => array(
							'type'        => 'array',
							'description' => 'Exactly three message options.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'content' => array(
										'type'        => 'string',
										'description' => 'Message text.',
									),
									'linkUrl' => array(
										'type'        => 'string',
										'description' => 'Optional URL.',
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'generate_messages' ),
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations'    => array(
						'instructions' => 'Generates three distinct social message options. Respects platform length and optional tone/context.',
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => false,
					),
					'show_in_rest'   => true,
					'allowed_blocks' => self::$allowed_blocks,
					'mcp'            => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	private function get_content_guidelines( int $post_id ): string {
		if ( ! function_exists( 'PRC\Platform\AI\Utils\get_content_guidelines_for_post' ) ) {
			return '';
		}

		$result = \PRC\Platform\AI\Utils\get_content_guidelines_for_post( $post_id, array( 'task' => 'social_message' ) );
		if ( empty( $result['packet_text'] ) || ! is_string( $result['packet_text'] ) ) {
			return '';
		}

		return trim( $result['packet_text'] );
	}

	private function get_max_length_for_platform( string $platform ): int {
		$limits = array(
			'twitter'  => 280,
			'facebook' => 500,
			'threads'  => 274,
			'bluesky'  => 274,
		);

		return $limits[ strtolower( $platform ) ] ?? 280;
	}

	/**
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_messages( $input ) {
		$post_id = isset( $input['postId'] ) ? (int) $input['postId'] : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'No postId provided.', 'prc-social-builder' ) );
		}

		$platform = isset( $input['platform'] ) ? sanitize_text_field( (string) $input['platform'] ) : '';
		if ( '' === $platform ) {
			return new WP_Error( 'missing_platform', __( 'No platform provided.', 'prc-social-builder' ) );
		}

		$tone    = isset( $input['tone'] ) ? sanitize_text_field( (string) $input['tone'] ) : '';
		$context = isset( $input['context'] ) ? sanitize_textarea_field( (string) $input['context'] ) : '';

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'prc-social-builder' ) );
		}

		$max_length = $this->get_max_length_for_platform( $platform );
		$title      = $post->post_title;
		$content    = wp_strip_all_tags( (string) $post->post_content, true );

		$guidelines = $this->get_content_guidelines( $post_id );
		$system     = $this->build_system_instruction( $platform, $tone, $context, $max_length, $guidelines );

		$prompt = wp_sprintf(
			"%s\n\nPost Title: %s\n\nPost Content:\n%s\n\nReturn ONLY a JSON array of exactly %d objects. Each object: {\"content\":\"...\",\"linkUrl\":\"optional url or omit\"}. No markdown fences.",
			$system,
			$title,
			$content,
			self::OPTION_COUNT
		);

		$raw = $this->generate_text_via_ai_client( $prompt );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$raw     = trim( $raw );
		$options = $this->parse_options( $raw, $max_length );

		if ( count( $options ) < self::OPTION_COUNT ) {
			$retry_raw = $this->generate_text_via_ai_client( $prompt );
			if ( is_wp_error( $retry_raw ) ) {
				if ( array() === $options ) {
					return $retry_raw;
				}
			} else {
				$retry = $this->parse_options( trim( $retry_raw ), $max_length );
				if ( count( $retry ) > count( $options ) ) {
					$options = $retry;
				}
			}
		}

		if ( array() === $options ) {
			return new WP_Error( 'no_options', __( 'Could not generate message options.', 'prc-social-builder' ) );
		}

		$options = array_slice( $options, 0, self::OPTION_COUNT );
		while ( count( $options ) < self::OPTION_COUNT ) {
			$options[] = $options[ count( $options ) - 1 ];
		}

		return array( 'options' => $options );
	}

	private function build_system_instruction( string $platform, string $tone, string $extra_context, int $max_length, string $guidelines ): string {
		// Resolve base template: admin override > default.
		$override = '';
		if ( class_exists( Settings::class ) ) {
			$settings = Settings::get_settings();
			$override = trim( $settings['system_prompts']['generate-message'] ?? '' );
		}

		$template = '' !== $override ? $override : self::get_default_system_prompt_template();

		$text = strtr(
			$template,
			array(
				'{{platform}}'     => $platform,
				'{{max_length}}'   => (string) $max_length,
				'{{option_count}}' => (string) self::OPTION_COUNT,
			)
		);

		if ( '' !== $tone ) {
			$text .= "\n\nTone: " . $tone;
		}

		if ( '' !== $extra_context ) {
			$text .= "\n\nAdditional context from the editor:\n" . $extra_context;
		}

		// Append per-network admin instructions.
		if ( class_exists( Settings::class ) ) {
			$settings             = Settings::get_settings();
			$network_instructions = trim( $settings['network_instructions'][ $platform ] ?? '' );
			if ( '' !== $network_instructions ) {
				$text .= "\n\nNetwork-level instructions:\n" . $network_instructions;
			}
		}

		if ( '' !== $guidelines ) {
			$text .= "\n\nSITE CONTENT GUIDELINES (authoritative):\n\n" . $guidelines;
		}

		$text .= "\n\n" . self::get_output_format_instruction();

		return $text;
	}

	private function generate_text_via_ai_client( string $prompt ): string|WP_Error {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'ai_unavailable', __( 'AI client is not available.', 'prc-social-builder' ) );
		}
		$builder = wp_ai_client_prompt( $prompt );
		if ( is_wp_error( $builder ) ) {
			return $builder;
		}
		$result = $builder
			->using_model_preference( ...\WordPress\AI\get_preferred_models_for_text_generation() )
			->generate_text();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return (string) $result;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function parse_options( string $raw, int $max_length ): array {
		$raw = trim( $raw );
		$raw = preg_replace( '/^```(?:json)?\s*/i', '', $raw );
		$raw = preg_replace( '/\s*```$/', '', $raw );
		if ( preg_match( '/\[.*\]/s', (string) $raw, $matches ) ) {
			$raw = $matches[0];
		}

		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$out = array();
		foreach ( $decoded as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$text = trim( $item );
				if ( mb_strlen( $text ) > $max_length ) {
					$text = mb_substr( $text, 0, $max_length );
				}
				$out[] = array( 'content' => $text );
				continue;
			}
			if ( is_array( $item ) && ! empty( $item['content'] ) ) {
				$text = trim( (string) $item['content'] );
				if ( '' === $text ) {
					continue;
				}
				if ( mb_strlen( $text ) > $max_length ) {
					$text = mb_substr( $text, 0, $max_length );
				}
				$row = array( 'content' => $text );
				if ( ! empty( $item['linkUrl'] ) && is_string( $item['linkUrl'] ) ) {
					$row['linkUrl'] = esc_url_raw( $item['linkUrl'] );
				}
				$out[] = $row;
			}
		}

		return $out;
	}
}
