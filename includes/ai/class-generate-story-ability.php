<?php
/**
 * Generate Story ability.
 *
 * Uses AI to suggest story caption, overlay text, and media hints for a post.
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
 * Registers and executes prc-social-builder/generate-story.
 *
 * @since 1.0.0
 */
class Generate_Story_Ability {

	/**
	 * Plugin file used to validate activation on the target site.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'prc-social-builder/prc-social-builder.php';

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public static $ability_name = 'prc-social-builder/generate-story';

	/**
	 * @var array<int, string>
	 */
	public static $allowed_blocks = array();

	/**
	 * Default instruction for the caption field.
	 */
	public static function get_default_caption_instruction(): string {
		return '1-3 sentences, clear and concise, fits story UI.';
	}

	/**
	 * Default instruction for the overlayText field.
	 */
	public static function get_default_overlay_instruction(): string {
		return 'Very short (under 80 chars), large-type friendly, no hashtags unless required.';
	}

	/**
	 * Default instruction for the suggestedMediaDescriptions field.
	 */
	public static function get_default_media_descriptions_instruction(): string {
		return 'One short line per suggested image explaining why it fits the story.';
	}

	/**
	 * The hard-coded output format instruction.
	 *
	 * Always appended to the final system instruction regardless of any admin override.
	 * This cannot be changed via the settings UI.
	 */
	public static function get_output_format_instruction(): string {
		return 'CRITICAL: Return ONLY a valid JSON object with keys:
- "caption" (string)
- "overlayText" (string)
- "suggestedMediaIds" (array of integers from the provided list, or [])
- "suggestedMediaDescriptions" (array of strings, same length as suggestedMediaIds)

Do not use markdown fences or extra prose.';
	}

	/**
	 * @hook wp_abilities_api_init
	 */
	public function register_ability(): void {
		wp_register_ability(
			self::$ability_name,
			array(
				'label'               => __( 'Generate Social Builder Story', 'prc-social-builder' ),
				'description'         => __( 'Uses AI to suggest story caption, overlay text, and image attachments from the post.', 'prc-social-builder' ),
				'category'            => 'communication',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'postId'                 => array(
							'type'        => 'number',
							'description' => 'Post ID for context.',
						),
						'platform'               => array(
							'type'        => 'string',
							'description' => 'Target platform (e.g. instagram, facebook).',
						),
						'additionalInstructions' => array(
							'type'        => 'string',
							'description' => 'Optional extra guidance for tone, focus, or style.',
						),
						'previousOutput'         => array(
							'type'                 => 'object',
							'description'          => 'Optional previous generation to revise when regenerating.',
							'properties'           => array(
								'caption'     => array(
									'type'        => 'string',
									'description' => 'Previous story caption.',
								),
								'overlayText' => array(
									'type'        => 'string',
									'description' => 'Previous overlay text.',
								),
							),
							'additionalProperties' => false,
						),
						'site_id'                => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
					'required'             => array( 'postId', 'platform' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'caption'                    => array(
							'type'        => 'string',
							'description' => 'Story caption text.',
						),
						'overlayText'                => array(
							'type'        => 'string',
							'description' => 'Short on-image overlay text.',
						),
						'suggestedMediaIds'          => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'number' ),
							'description' => 'Suggested attachment IDs from the post.',
						),
						'suggestedMediaDescriptions' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Parallel descriptions for suggested media.',
						),
						'numberCheck'                => Number_Check::get_output_schema_fragment(),
					),
				),
				'execute_callback'    => function ( $input ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->generate_story( $input );
						}
					);
				},
				'permission_callback' => function ( $input = null ) {
					return $this->with_site(
						$input,
						function () {
							return current_user_can( 'edit_posts' );
						}
					);
				},
				'meta'                => array(
					'annotations'    => array(
						'instructions' => 'Suggests story-style caption and overlay text; maps suggestions to image attachments on the post when possible. Optionally pass site_id to run against a specific multisite blog; defaults to the content site (20). If this plugin is inactive on the target site, the ability returns plugin_inactive_on_site.',
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

	/**
	 * @return array<int, int>
	 */
	private function get_image_attachment_ids_for_post( int $post_id ): array {
		$ids         = array();
		$attachments = get_attached_media( 'image', $post_id );
		foreach ( $attachments as $attachment ) {
			if ( $attachment instanceof \WP_Post ) {
				$ids[] = (int) $attachment->ID;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_story( $input ) {
		$post_id = isset( $input['postId'] ) ? (int) $input['postId'] : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'No postId provided.', 'prc-social-builder' ) );
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

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'prc-social-builder' ) );
		}

		$media_ids = $this->get_image_attachment_ids_for_post( $post_id );
		$id_list   = array() === $media_ids ? '(none)' : implode( ', ', array_map( 'strval', $media_ids ) );

		$title   = $post->post_title;
		$content = wp_strip_all_tags( (string) $post->post_content, true );
		$content = mb_substr( $content, 0, 4000 );

		$additional      = isset( $input['additionalInstructions'] ) ? sanitize_textarea_field( (string) $input['additionalInstructions'] ) : '';
		$previous_output = $this->extract_previous_output( $input );
		$guidelines      = $this->get_content_guidelines( $post_id );
		$system          = $this->build_system_instruction( $platform, $id_list, $guidelines, $additional, $previous_output );

		$prompt = wp_sprintf(
			"%s\n\nPost Title: %s\n\nPost Content:\n%s\n\nRespond with ONLY valid JSON: {\"caption\":\"...\",\"overlayText\":\"...\",\"suggestedMediaIds\":[...ids from list only...],\"suggestedMediaDescriptions\":[\"...\"]}. Arrays must align in length when IDs are used.",
			$system,
			$title,
			$content
		);

		$raw = $this->generate_text_via_ai_client( $prompt );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$raw  = trim( $raw );
		$data = $this->parse_story_response( $raw, $media_ids );

		if ( is_wp_error( $data ) ) {
			$retry = $prompt . "\n\nPrevious JSON was invalid. Fix and return only JSON.";
			$raw   = $this->generate_text_via_ai_client( $retry );
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			$raw  = trim( $raw );
			$data = $this->parse_story_response( $raw, $media_ids );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
		}

		$source_text = mb_substr( $title . "\n\n" . $content, 0, Editorial_Passes::SOURCE_CHAR_LIMIT );
		$items       = array();
		if ( '' !== $data['caption'] ) {
			$data['caption'] = mb_substr( $data['caption'], 0, Editorial_Passes::MAX_ITEM_CHARS );
			$items[]         = array(
				'id'   => 'caption',
				'text' => $data['caption'],
			);
		}
		if ( '' !== $data['overlayText'] ) {
			$data['overlayText'] = mb_substr( $data['overlayText'], 0, 80 );
			$items[]             = array(
				'id'   => 'overlayText',
				'text' => $data['overlayText'],
			);
		}

		$edited_items = Editorial_Passes::apply_batch( $items, $source_text );
		if ( is_wp_error( $edited_items ) ) {
			return $edited_items;
		}

		$items = array();
		foreach ( $edited_items as $item ) {
			if ( 'caption' === $item['id'] ) {
				$data['caption'] = $item['text'];
			} elseif ( 'overlayText' === $item['id'] ) {
				$data['overlayText'] = $item['text'];
			}
			$items[] = array(
				'id'   => $item['id'],
				'text' => $data[ $item['id'] ],
			);
		}

		$number_checks = Number_Check::annotate_many( $items, $source_text );
		if ( null !== $number_checks ) {
			$flagged = array();
			foreach ( $number_checks as $number_check ) {
				$flagged = array_merge( $flagged, $number_check['flagged'] );
			}
			$flagged             = array_values( array_unique( $flagged ) );
			$data['numberCheck'] = array(
				'valid'   => array() === $flagged,
				'flagged' => $flagged,
			);
		}

		return $data;
	}

	/**
	 * Extract and sanitize previousOutput from ability input.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array{caption: string, overlayText: string}|null
	 */
	private function extract_previous_output( array $input ): ?array {
		if ( ! isset( $input['previousOutput'] ) || ! is_array( $input['previousOutput'] ) ) {
			return null;
		}

		$caption      = isset( $input['previousOutput']['caption'] )
			? sanitize_textarea_field( (string) $input['previousOutput']['caption'] )
			: '';
		$overlay_text = isset( $input['previousOutput']['overlayText'] )
			? sanitize_textarea_field( (string) $input['previousOutput']['overlayText'] )
			: '';

		if ( '' === $caption && '' === $overlay_text ) {
			return null;
		}

		return array(
			'caption'     => $caption,
			'overlayText' => $overlay_text,
		);
	}

	/**
	 * @param array{caption: string, overlayText: string}|null $previous_output Previous generation to revise.
	 */
	private function build_system_instruction( string $platform, string $available_ids, string $guidelines, string $additional = '', ?array $previous_output = null ): string {
		// Resolve per-field overrides from settings sub-array.
		$caption_instruction    = self::get_default_caption_instruction();
		$overlay_instruction    = self::get_default_overlay_instruction();
		$media_desc_instruction = self::get_default_media_descriptions_instruction();

		if ( class_exists( Settings::class ) ) {
			$settings      = Settings::get_settings();
			$story_prompts = $settings['system_prompts']['generate-story'] ?? array();
			if ( is_array( $story_prompts ) ) {
				$override_caption    = trim( (string) ( $story_prompts['caption'] ?? '' ) );
				$override_overlay    = trim( (string) ( $story_prompts['overlay_text'] ?? '' ) );
				$override_media_desc = trim( (string) ( $story_prompts['media_descriptions'] ?? '' ) );
				if ( '' !== $override_caption ) {
					$caption_instruction = $override_caption;
				}
				if ( '' !== $override_overlay ) {
					$overlay_instruction = $override_overlay;
				}
				if ( '' !== $override_media_desc ) {
					$media_desc_instruction = $override_media_desc;
				}
			}
		}

		// Assemble from hard-coded structure + editable per-field instructions.
		$text  = 'You are creating a short-form "story" concept for ' . ucfirst( $platform ) . '.';
		$text .= "\n- caption: " . $caption_instruction;
		$text .= "\n- overlayText: " . $overlay_instruction;
		// suggestedMediaIds is always hard-coded — never exposed for editing.
		$text .= "\n- suggestedMediaIds: choose zero or more IDs ONLY from this list: " . $available_ids . '. If the list is (none), use [].';
		$text .= "\n- suggestedMediaDescriptions: " . $media_desc_instruction . ' Same count as suggestedMediaIds.';

		if ( '' !== $guidelines ) {
			$text .= "\n\nSITE CONTENT GUIDELINES (authoritative):\n\n" . $guidelines;
		}

		// Append per-network admin instructions.
		if ( class_exists( Settings::class ) ) {
			$settings             = Settings::get_settings();
			$network_instructions = trim( $settings['network_instructions'][ $platform ] ?? '' );
			if ( '' !== $network_instructions ) {
				$text .= "\n\nNetwork-level instructions:\n" . $network_instructions;
			}
		}

		if ( '' !== $additional ) {
			$text .= "\n\nADDITIONAL INSTRUCTIONS:\n\n" . $additional;
		}

		if ( null !== $previous_output ) {
			$text .= "\n\nPrevious generation to revise:";
			$text .= "\ncaption: " . $previous_output['caption'];
			$text .= "\noverlayText: " . $previous_output['overlayText'];
			$text .= "\n\nRevise that previous output using the editor's additional instructions. Keep what still works unless the instructions say otherwise. Return a new result in the required JSON format.";
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
	 * @param array<int, int> $allowed_ids
	 * @return array<string, mixed>|WP_Error
	 */
	private function parse_story_response( string $response, array $allowed_ids ) {
		$json_text = $response;
		if ( preg_match( '/\{.*\}/s', $response, $matches ) ) {
			$json_text = $matches[0];
		}

		$parsed = json_decode( $json_text, true );
		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'parse_error', __( 'Failed to parse story JSON.', 'prc-social-builder' ) );
		}

		$caption = isset( $parsed['caption'] ) && is_string( $parsed['caption'] ) ? trim( $parsed['caption'] ) : '';
		$overlay = isset( $parsed['overlayText'] ) && is_string( $parsed['overlayText'] ) ? trim( $parsed['overlayText'] ) : '';
		$ids     = isset( $parsed['suggestedMediaIds'] ) && is_array( $parsed['suggestedMediaIds'] ) ? $parsed['suggestedMediaIds'] : array();
		$descs   = isset( $parsed['suggestedMediaDescriptions'] ) && is_array( $parsed['suggestedMediaDescriptions'] ) ? $parsed['suggestedMediaDescriptions'] : array();

		if ( '' === $caption && '' === $overlay ) {
			return new WP_Error( 'empty_story', __( 'AI returned empty story fields.', 'prc-social-builder' ) );
		}

		$allowed_lookup = array_fill_keys( $allowed_ids, true );
		$clean_ids      = array();
		foreach ( $ids as $id ) {
			$int = (int) $id;
			if ( isset( $allowed_lookup[ $int ] ) ) {
				$clean_ids[] = $int;
			}
		}
		$clean_ids = array_values( array_unique( $clean_ids ) );

		$clean_descs = array();
		foreach ( $descs as $d ) {
			if ( is_string( $d ) && '' !== trim( $d ) ) {
				$clean_descs[] = trim( $d );
			}
		}

		$count = count( $clean_ids );
		if ( $count > 0 && count( $clean_descs ) < $count ) {
			while ( count( $clean_descs ) < $count ) {
				$clean_descs[] = '';
			}
		}
		$clean_descs = array_slice( $clean_descs, 0, $count );

		return array(
			'caption'                    => $caption,
			'overlayText'                => $overlay,
			'suggestedMediaIds'          => $clean_ids,
			'suggestedMediaDescriptions' => $clean_descs,
		);
	}

	/**
	 * Run a callback on the requested target site.
	 *
	 * @param array|null $input    Ability input.
	 * @param callable   $callback Callback to run after site validation/switching.
	 * @return mixed
	 */
	private function with_site( $input, callable $callback ) {
		return \PRC\Platform\AI\Utils\with_site(
			\PRC\Platform\AI\Utils\resolve_site_id( is_array( $input ) ? $input : null ),
			self::PLUGIN_FILE,
			$callback
		);
	}
}
