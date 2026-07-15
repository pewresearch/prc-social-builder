<?php
/**
 * Generate Thread ability.
 *
 * Uses AI to generate a thread of social messages for a post with optional tone.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

use WP_Error;
use PRC\Platform\Report_Package\get_package_chapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and executes prc-social-builder/generate-thread.
 *
 * @since 1.0.0
 */
class Generate_Thread_Ability {

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public static $ability_name = 'prc-social-builder/generate-thread';

	/**
	 * Blocks allowed to use this ability.
	 *
	 * @var array<int, string>
	 */
	public static $allowed_blocks = array();

	/**
	 * The default system prompt template.
	 *
	 * Supports placeholders: {{platform}}, {{max_messages}}, {{char_limit}}.
	 * Tone and additional instructions are appended at runtime, not part of the template.
	 * The output format instruction is always appended separately via get_output_format_instruction().
	 */
	public static function get_default_system_prompt_template(): string {
		return 'You are a social media copywriter creating a thread for {{platform}}.
Create 2-{{max_messages}} messages, each at most {{char_limit}} characters.

NEVER reference @PewResearch or other @ handles. Keep content factual and concise.';
	}

	/**
	 * The default system prompt template for single-post (non-threaded) platforms.
	 *
	 * Supports placeholders: {{platform}}, {{char_limit}}.
	 * The output format instruction is always appended separately via get_output_format_instruction().
	 */
	public static function get_default_single_post_system_prompt_template(): string {
		return 'You are a social media copywriter creating a single post for {{platform}} (this platform does not support threaded posts).
Create exactly 1 message, at most {{char_limit}} characters.

NEVER reference @PewResearch or other @ handles. Keep content factual and concise.';
	}

	/**
	 * The hard-coded output format instruction.
	 *
	 * Always appended to the final system instruction regardless of any admin override.
	 * This cannot be changed via the settings UI.
	 */
	public static function get_output_format_instruction(): string {
		return 'CRITICAL: Return ONLY a JSON array. Each element must be an object with:
- "content" (string, the message text)
- "position" (number, 1-based order in the thread)
- "linkUrl" (optional string, a single URL if relevant)

Do not use markdown fences or extra prose. Example:
[{"content":"First post","position":1},{"content":"Second","position":2,"linkUrl":"https://example.org"}]';
	}

	/**
	 * Register the ability with the Abilities API.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_ability(): void {
		wp_register_ability(
			self::$ability_name,
			array(
				'label'               => __( 'Generate Social Builder Thread', 'prc-social-builder' ),
				'description'         => __( 'Uses AI to generate a thread of social messages from post content.', 'prc-social-builder' ),
				'category'            => 'communication',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'postId'                 => array(
							'type'        => 'number',
							'description' => 'The id of the post to generate a thread for.',
						),
						'platform'               => array(
							'type'        => 'string',
							'description' => 'The social media platform (e.g. twitter, facebook, threads, bluesky, linkedin).',
						),
						'tone'                   => array(
							'type'        => 'string',
							'description' => 'Optional tone guidance for the thread.',
						),
						'includeReportChildren'                   => array(
							'type'        => 'boolean',
							'description' => 'Add children',
						),
						'unselectedReportChildren'                   => array(
							'type'        => 'string',
							'description' => 'List of Ids to skip',
						),
						'additionalInstructions' => array(
							'type'        => 'string',
							'description' => 'Optional additional instructions from the editor to guide generation.',
						),
						'messageCount'           => array(
							'type'        => 'number',
							'description' => 'How many messages to generate (2–10 for threaded platforms; use 1 for Facebook, which does not support threads). Defaults to the configured setting.',
						),
					),
					'required'             => array( 'postId', 'platform' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'messages' => array(
							'type'        => 'array',
							'description' => 'Ordered thread messages.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'content'  => array(
										'type'        => 'string',
										'description' => 'Message body text.',
									),
									'position' => array(
										'type'        => 'number',
										'description' => '1-based order in the thread.',
									),
									'linkUrl'  => array(
										'type'        => 'string',
										'description' => 'Optional URL to include with this message.',
									),
									'numberCheck' => Number_Check::get_output_schema_fragment(),
								),
							),
						),
					),
				),
				'execute_callback'    => array( $this, 'generate_thread' ),
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations'    => array(
						'instructions' => 'Generates a thread of social messages from a post. Respects optional tone. When Content Guidelines is active, editorial constraints are applied.',
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

	/**
	 * Content guidelines packet for social thread tasks.
	 */
	private function get_content_guidelines( int $post_id ): string {
		if ( ! function_exists( 'PRC\Platform\AI\Utils\get_content_guidelines_for_post' ) ) {
			return '';
		}

		$result = \PRC\Platform\AI\Utils\get_content_guidelines_for_post( $post_id, array( 'task' => 'social_thread' ) );
		if ( empty( $result['packet_text'] ) || ! is_string( $result['packet_text'] ) ) {
			return '';
		}

		return trim( $result['packet_text'] );
	}

	private function get_platform_char_limit( string $platform ): int {
		$limits = array(
			'twitter'  => 280,
			'facebook' => 500,
			'threads'  => 274,
			'bluesky'  => 274,
			'linkedin' => 3000,
		);

		return $limits[ strtolower( $platform ) ] ?? 280;
	}

	/**
	 * Platforms that only support a single post (no thread).
	 */
	private function is_single_post_platform( string $platform ): bool {
		return in_array( strtolower( $platform ), array( 'facebook', 'linkedin' ), true );
	}

	/**
	 * Resolve the default thread count for a platform from settings, falling back to 4.
	 */
	private function get_default_message_count( string $platform ): int {
		if ( $this->is_single_post_platform( $platform ) ) {
			return 1;
		}

		if ( class_exists( Settings::class ) ) {
			$settings = Settings::get_settings();
			$counts   = $settings['thread_counts'] ?? array();
			if ( isset( $counts[ $platform ] ) ) {
				return (int) $counts[ $platform ];
			}
		}

		return 4;
	}

	

	public function prepareContent( $post_id, $use_children, $unselect_ids ){
		
		$main_post    = $this->gatherContent( $post_id );
		$unselect_ids = is_array( $unselect_ids )
			? array_map( 'intval', $unselect_ids )
			: array();

		if ( $use_children  && function_exists( '\PRC\Platform\Report_Package\get_package_chapters' ) ) {
			$chapters = \PRC\Platform\Report_Package\get_package_chapters( $post_id );
			foreach ( $chapters as $chapter ) {
				if ( empty( $chapter['id'] ) ) {
					continue;
				}
				$chapter_id = (int) $chapter['id'];
				if ( (int) $post_id === $chapter_id || in_array( $chapter_id, $unselect_ids, true ) ) {
					continue;
				}
				if ( ! current_user_can( 'edit_post', $chapter_id ) ) {
					return new WP_Error(
						'forbidden_report_child',
						__( 'You cannot generate social copy from one or more report chapters.', 'prc-social-builder' ),
						array( 'status' => 403 )
					);
				}
				$child = $this->gatherContent( $chapter_id );
				$main_post['content'] = wp_sprintf(
					"%s\n%s\n%s",
					$main_post['content'],
					$child['title'],
					$child['content']
				);
			}

		}

		return $main_post;
	}

	private function gatherContent( $post_id ) { 
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'title' => '',
				'content' => ''
			);
			// LOG THIS?? --> new WP_Error( 'post_not_found', __( 'Post not found.', 'prc-social-builder' ) );
		}

		$title   = $post->post_title;
		$content = wp_strip_all_tags( (string) $post->post_content, true );
		$content = mb_substr( $content, 0, 3000 );
		return array(
			'title' => $title,
			'content' => $content
		);
	}

	/**
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_thread( $input ) {
		$post_id = isset( $input['postId'] ) ? (int) $input['postId'] : 0;
		$use_children = isset( $input['includeReportChildren'] ) ? (bool) $input['includeReportChildren'] : false;
		$decoded_unselect_ids = isset( $input['unselectedReportChildren'] )
			? json_decode( (string) $input['unselectedReportChildren'], true )
			: array();
		$unselect_ids         = is_array( $decoded_unselect_ids )
			? array_map( 'intval', $decoded_unselect_ids )
			: array();

		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'No postId provided for thread generation.', 'prc-social-builder' ) );
		}

		$platform = isset( $input['platform'] ) ? sanitize_text_field( (string) $input['platform'] ) : '';
		if ( '' === $platform ) {
			return new WP_Error( 'missing_platform', __( 'No platform provided.', 'prc-social-builder' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'forbidden_post',
				__( 'You cannot generate social copy for this post.', 'prc-social-builder' ),
				array( 'status' => 403 )
			);
		}

		$tone                    = isset( $input['tone'] ) ? sanitize_text_field( (string) $input['tone'] ) : '';
		$additional_instructions = isset( $input['additionalInstructions'] ) ? sanitize_textarea_field( (string) $input['additionalInstructions'] ) : '';
		$char_limit              = $this->get_platform_char_limit( $platform );

		$default_count = $this->get_default_message_count( $platform );
		$max_messages  = $this->is_single_post_platform( $platform )
			? 1
			: ( isset( $input['messageCount'] ) ? max( 2, min( 10, (int) $input['messageCount'] ) ) : $default_count );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'prc-social-builder' ) );
		}

		// $title   = $post->post_title;
		// $content = wp_strip_all_tags( (string) $post->post_content, true );
		// $content = mb_substr( $content, 0, 3000 );

		$gather_content = $this->prepareContent( $post_id, $use_children, $unselect_ids );
		if ( is_wp_error( $gather_content ) ) {
			return $gather_content;
		}
		$title         = $gather_content['title'];
		$source_prefix = $title . "\n\n";
		$content       = mb_substr(
			$gather_content['content'],
			0,
			max( 0, Editorial_Passes::SOURCE_CHAR_LIMIT - mb_strlen( $source_prefix ) )
		);
		$source_text   = $source_prefix . $content;

		$guidelines = $this->get_content_guidelines( $post_id );
		$system     = $this->build_system_instruction( $platform, $tone, $additional_instructions, $char_limit, $max_messages, $guidelines );

		$prompt = wp_sprintf(
			"%s\n\nPost Title: %s\n\nPost Content:\n%s\n\nReturn ONLY a JSON array of objects with keys content, position (1-based integer), and optional linkUrl (string or omit):",
			$system,
			$title,
			$content
		);

		$raw = $this->generate_text_via_ai_client( $prompt );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$raw      = trim( $raw );
		$messages = $this->parse_thread_messages( $raw, $char_limit, $max_messages );

		if ( is_wp_error( $messages ) ) {
			$retry = wp_sprintf(
				"%s\n\nPost Title: %s\n\nPost Content:\n%s\n\nPrevious output was invalid. Respond with ONLY valid JSON, e.g. [{\"content\":\"...\",\"position\":1},{\"content\":\"...\",\"position\":2}]:",
				$system,
				$title,
				$content
			);
			$raw = $this->generate_text_via_ai_client( $retry );
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			$raw      = trim( $raw );
			$messages = $this->parse_thread_messages( $raw, $char_limit, $max_messages );
			if ( is_wp_error( $messages ) ) {
				return $messages;
			}
		}

		$items = array();
		foreach ( $messages as $index => $message ) {
			$items[] = array(
				'id'   => 'thread-message-' . $index,
				'text' => (string) $message['content'],
			);
		}

		$edited_items = Editorial_Passes::apply_batch( $items, $source_text );
		if ( is_wp_error( $edited_items ) ) {
			return $edited_items;
		}

		foreach ( $edited_items as $index => $item ) {
			$messages[ $index ]['content'] = $item['text'];
			$items[ $index ]['text']       = $item['text'];
		}

		$number_checks = Number_Check::annotate_many( $items, $source_text );
		if ( null !== $number_checks ) {
			foreach ( $items as $index => $item ) {
				$messages[ $index ]['numberCheck'] = $number_checks[ $item['id'] ];
			}
		}

		return array( 'messages' => $messages );
	}

	private function build_system_instruction( string $platform, string $tone, string $additional_instructions, int $char_limit, int $max_messages, string $content_guidelines ): string {
		// Resolve base template: admin override > default.
		$override = '';
		if ( class_exists( Settings::class ) ) {
			$settings = Settings::get_settings();
			$override = trim( $settings['system_prompts']['generate-thread'] ?? '' );
		}

		if ( '' !== $override ) {
			$template = $override;
		} elseif ( 1 === $max_messages ) {
			$template = self::get_default_single_post_system_prompt_template();
		} else {
			$template = self::get_default_system_prompt_template();
		}

		$instructions = strtr(
			$template,
			array(
				'{{platform}}'     => ucfirst( $platform ),
				'{{max_messages}}' => (string) $max_messages,
				'{{char_limit}}'   => (string) $char_limit,
			)
		);

		if ( '' !== $tone ) {
			$instructions .= wp_sprintf( "\n\nTone guidance: %s", $tone );
		}

		// Append per-network admin instructions.
		if ( class_exists( Settings::class ) ) {
			$settings             = Settings::get_settings();
			$network_instructions = trim( $settings['network_instructions'][ $platform ] ?? '' );
			if ( '' !== $network_instructions ) {
				$instructions .= "\n\nNetwork-level instructions:\n" . $network_instructions;
			}
		}

		if ( '' !== $additional_instructions ) {
			$instructions .= "\n\nAdditional instructions from the editor:\n" . $additional_instructions;
		}

		if ( '' !== $content_guidelines ) {
			$instructions .= "\n\nSITE CONTENT GUIDELINES (authoritative):\n\n" . $content_guidelines;
		}

		$instructions .= "\n\n" . self::get_output_format_instruction();

		return $instructions;
	}

	/**
	 * @return string|WP_Error
	 */
	private function generate_text_via_ai_client( string $prompt ) {
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
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function parse_thread_messages( string $response, int $char_limit, int $max_messages ) {
		$json_text = $response;
		if ( preg_match( '/\[.*\]/s', $response, $matches ) ) {
			$json_text = $matches[0];
		}

		$parsed = json_decode( $json_text, true );
		if ( ! is_array( $parsed ) || array() === $parsed ) {
			return new WP_Error( 'parse_error', __( 'Failed to parse AI thread response.', 'prc-social-builder' ) );
		}

		$out = array();
		$pos = 1;
		foreach ( $parsed as $item ) {
			if ( ! is_array( $item ) || empty( $item['content'] ) ) {
				continue;
			}
			$text = trim( (string) $item['content'] );
			if ( '' === $text ) {
				continue;
			}
			if ( mb_strlen( $text ) > $char_limit ) {
				$text = mb_substr( $text, 0, $char_limit - 1 ) . '…';
			}
			$position = isset( $item['position'] ) ? (int) $item['position'] : $pos;
			$row      = array(
				'content'  => $text,
				'position' => $position > 0 ? $position : $pos,
			);
			if ( ! empty( $item['linkUrl'] ) && is_string( $item['linkUrl'] ) ) {
				$row['linkUrl'] = esc_url_raw( $item['linkUrl'] );
			}
			$out[] = $row;
			++$pos;
		}

		if ( array() === $out ) {
			return new WP_Error( 'empty_thread', __( 'AI generated an empty thread.', 'prc-social-builder' ) );
		}

		return array_slice( $out, 0, $max_messages );
	}
}
