<?php
/**
 * Generate Social Copy ability.
 *
 * Uses AI to generate a social copy for a post with optional tone.
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
 * Registers and executes prc-social-builder/generate-social-copy.
 *
 * @since 1.0.0
 */
class Generate_Social_Copy_Ability {

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
	public static $ability_name = 'prc-social-builder/generate-social-copy';

	/**
	 * Blocks allowed to use this ability.
	 *
	 * @var array<int, string>
	 */
	public static $allowed_blocks = array();

	/**
	 * Instructions for the compression pass.
	 *
	 * Condenses one or more source documents into a factual findings digest that
	 * the per-platform copy prompts consume. Output is plain text, not JSON.
	 */
	public static function get_social_style_guide(): string {
		return <<<'INSTRUCTION'
	Social Media Voice, Tone & Style Guide

	GUIDING PRINCIPLE
	We generate interest by offering a clear, accessible, relevant, and neutral presentation of our research findings.

	OUR VOICE
	The Center's social voice should be authoritative, impartial, humble, accessible, and human. It should help people understand what the research says without sounding partisan, sensational, or overly casual.

	Authoritative — We ground our content in our data. We are precise and credible in our language.
	Impartial — We are nonpartisan and nonadvocacy. We focus on informing, not persuading.
	Humble — We show, don't tell. The nuance and rigor of our work is imbued in our presentation.
	Accessible — We use plain language and format our social content so it is easy to understand.
	Relevant — We connect our findings to people's everyday lives and experiences.
	Respectful — We value the trust people place in us by sharing their opinions with care and respect.

	CORE DRAFTING RULES

	Inform, do not persuade. Present facts and context. Avoid advocacy, opinion, judgment, or telling audiences what to think. Never speculate or make claims the data does not support.

	Simplify without oversimplifying. Use plain language and short sentences, but preserve definitions, survey universes, uncertainty, and methodology when needed. Keep material caveats even in caption-length content — e.g., "roughly half" or a brief "among employed U.S. adults" qualifier — rather than dropping them for space.

	Spark curiosity, not outrage. Use questions or meaningful contrasts to invite learning. Do not use breathless or sensational framing to manufacture urgency or emotional reaction. It's rarely appropriate to use ALL CAPS. If there is a counterintuitive finding, consider leading with that to spark engagement.

	Be respectful. Never frame findings in a way that could be read as making fun of people's opinions or their lack of knowledge.

	Be conversational, not casual. Warm and clear is good; flippant, snarky, or overly promotional is not. Use emojis thoughtfully as visual aids (e.g., topics, list items, directional arrows) — not for emphasis or hype. Use one to two purposeful, topical hashtags rather than strings of tags. Ensure that hashtags are not affiliated with advocacy campaigns or could be perceived that way.

	Put findings first. Start with what people do, think, or experience. Instead of "Pew Research Center released a new report today…" lead with the finding or research question: "Just over half of U.S. teens say they've used chatbots for help with schoolwork" or "Why is Buddhism shrinking worldwide?"

	Do not overstate causality. Use "finds," "shows," "suggests," "indicates," "examines," "explores," or "documents."

	Preserve nuance. If results vary by party, age, race, gender, education, or other factors, avoid flattening those differences. Demographic breaks are sometimes our most interesting findings. At the same time, it's important not to overstate differences. Republicans might be 15 points more likely than Democrats to hold a particular view, but if majorities in BOTH parties express that view, the story may be less about differences and more about agreement.

	Match verbs to data type. Use "say," "report," or "feel" for self-reported attitudes and survey responses. Reserve factual verbs — "have," "are," "use" — for measured behaviors, not opinions.

	Describe visuals. Every chart or graphic used in social content should include alt text stating the chart's core finding in plain language for screen-reader accessibility.

	NAMING AND ATTRIBUTION

	First reference: Use "Pew Research Center." Example: "A new Pew Research Center survey finds…"

	Later references: Use "the Center" on second reference. Example: "The Center also found…"

	Avoid: Do not shorten to "Pew." Avoid: "Pew found…," "According to Pew…," "New Pew data…"

	BEST PRACTICES FOR ENGAGEMENT

	Let "the Center" do the asking. "We asked…" and "Our analysis found…" keep the Center as objective narrator to reinforce that this is impartial research, not commentary.

	Anchor to a real moment, when it fits. A dated anniversary, holiday, or news event can be a good opening hook. Use only when directly relevant to the data — don't stretch a finding to fit a trending moment. If a connection feels too tenuous, it probably is. Also remember that today's information environment is highly fragmented: A news hook for one audience may not even register with another audience.

	Highlight the contrast: "X, but also Y." Pairing a finding with its counterpoint paints a more complete picture, keeps nuance intact, and avoids tilting the frame to one side: "Republicans say X, while Democrats say Y."

	A plain declarative headline is a complete option. Not every post needs a question or a CTA. A single, confident sentence stating the finding often performs well on its own — especially when a lighter touch is called for: "U.S.-style birthright citizenship is uncommon around the world."

	AVOID BREATHLESS OR SENSATIONAL PHRASING
	The Center's research is interesting on its own. Avoid language that makes findings seem more dramatic than the data supports. A measured tone is always best.

	Avoid: shocking, stunning, unbelievable, incredible, explosive, game-changing, groundbreaking, alarming, must-see, jaw-dropping, unprecedented, massive

	Prefer: notable, substantial, growing, declining, increasingly common, less common, shift, trend, finding, difference, change over time

	FINAL QUALITY CHECK

	Accurate — Every claim is supported by the research.
	Understandable — A general audience can grasp it quickly.
	Nonpartisan — Wording does not imply a political side or policy position.
	Measured — No hype, clickbait, or emotional overstatement.
	On brand — Sounds like Pew Research Center.
	INSTRUCTION;
	}
	
		/**
	 * Instructions for the compression pass.
	 *
	 * Condenses one or more source documents into a factual findings digest that
	 * the per-platform copy prompts consume.
	 */
	public static function get_summarizer_instructions(): string {
			return <<<'PROMPT'
	You are a research analyst preparing source material for a social media copywriter at a nonpartisan research organization.
	
	The input contains one or more documents, each introduced by "Post Title:" and "Post Content:". Read all of them and produce a single digest of the most newsworthy findings.
	
	Format:
	- One opening sentence naming the subject and the population studied.
	- Then 4 to 8 bullets, each a complete sentence stating one finding. Lead with the finding, not the methodology.
	- Order bullets by how notable the finding is, most notable first.
	
	Rules:
	- Use only facts stated in the source. Never infer, extrapolate, or add outside context.
	- Reproduce every number, percentage, date, and margin exactly as written. Do not round, convert, average, or recompute. "38%" stays "38%".
	- Keep the subgroup or comparison attached to each number (for example "38% of adults under 30, compared with 12% of those 65 and older").
	- Keep the survey field dates and sample description if the source gives them.
	- Omit charts, tables, footnotes, boilerplate, and calls to action.
	- Write neutral, declarative prose. No hashtags, no @ handles, no emoji, no markdown, no headings, no bold.
	- Do not write a preamble, a conclusion, or any commentary about your own output.
	- Hard limit: 2000 characters total. If you must cut, drop the least notable bullets rather than trimming numbers or qualifiers out of the ones you keep.
	
	PROMPT;
		}

	/**
	 * The default system prompt template.
	 *
	 * Supports placeholders: {{platform}}, {{char_limit}}.
	 * The output format instruction is always appended separately via get_output_format_instruction(). 
	 */
	public static function get_default_system_prompt_template(): string {
		return 'You are a social media copywriter creating a single post for {{platform}}.
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

Do not use markdown fences or extra prose. Example:
[{"content":"Post content"}]';
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
				'label'               => __( 'Generate Social Copy', 'prc-social-builder' ),
				'description'         => __( 'Uses AI to generate social copy from provided content.', 'prc-social-builder' ),
				'category'            => 'communication',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'isDefaultList' => array(
							'type'        => 'boolean',
							'description' => 'Create the default social package set? (Facebook, LinkedIn, Twitter, Bluesky)'
						),
						'skipCopyEdits' => array(
							'type'        => 'boolean',
							'description' => 'Skip AI editorial skills?',
						),
						'returnSummary' => array(
							'type'        => 'boolean',
							'description' => 'Exit after summarization',
						),
						'content'       => array(
							'type'        => 'array',
							'description' => 'tk',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'contentType'              => array(
										'type'        => 'string',
										'description' => 'The type of content, must be either "wp-post" or "preformatted"',
									),
									'postId'                   => array(
										'type'        => 'number',
										'description' => 'The id of the post to generate social copy for.',
									),
									'includeReportChildren'    => array(
										'type'        => 'boolean',
										'description' => 'Add children',
									),
									'unselectedReportChildren' => array(
										'type'        => 'string',
										'description' => 'List of ids to skip',
									),
									'preformatted'             => array(
										'type'        => 'string',
										'description' => 'Preformatted text to generate the social copy from',
									),
									// 'isSupplementary'          => array(
									// 	'type'        => 'boolean',
									// 	'description' => 'These are supplementary materials'
									// ),
								)
							)
						),
						'copyList'      => array(
							'type'        => 'array',
							'description' => 'List of platforms to create social copy for',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'platform'              => array(
										'type'        => 'string',
										'description' => 'One of the supported social platforms: twitter, facebook, threads, bluesky, linkedin'
									),
									'additionalInstructions' => array(
										'type'        => 'string',
										'description' => 'Optional guidance for the AI (e.g. tone, focus, angle).',
									),
									'requestedEdits' => array(
										'type'        => 'string',
										'description' => 'Optional additional instructions from the editor to guide revision.',
									),
									'tone' => array(
										'type'        => 'string',
										'description' => 'Optional tone guidance for the copy.',
									),
									'previousOutput' => array(
										'type'                 => 'object',
										'description'          => 'Optional previous generation to revise when regenerating.',
										'properties'           => array(
											'copy'    => array(
												'type'        => 'string',
												'description' => 'Previous copy text.',
											),
										),
										'additionalProperties' => false,
									),
								)
							)
						),
						'site_id'                => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
					'required'             => array( 'isDefaultList', 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'platform' => array(
								'type'        => 'string',
								'description' => 'One of the supported social platforms: facebook, x-twitter, linkedin, bluesky, instagram'
							),
							'additionalInstructions' => array(
								'type'        => 'string',
								'description' => 'Optional additional instructions from the editor to guide generation.',
							),
							'copy' => array(
								'type'        => 'string',
								'description' => 'Generated social copy or an error message detailing what went wrong',
							),
							'numberCheck' => Number_Check::get_output_schema_fragment(),
							'error' => array(
								'type'        => 'boolean',
								'description' => 'True when unable to generate the copy',
							),
						)
					)
				),
				'execute_callback'    => function ( $input ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->generate_social_copy( $input );
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
						'instructions' => 'Generates social copy from provided context. Respects optional tone. If this plugin is inactive on the target site, the ability returns plugin_inactive_on_site. Optionally pass site_id to run against a specific multisite blog.',
						// 'readonly'     => true,
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
	 * Get the default social package.
	 *
	 * @return array<array<string, mixed>>
	 */
	private function get_default_social_package() {
		return array(
			array(
				'platform' => 'facebook',
				'additionalInstructions' => '',
			),
			array(
				'platform' => 'twitter',
				'additionalInstructions' => '',
			),
			array(
				'platform' => 'bluesky',
				'additionalInstructions' => '',
			),
			array(
				'platform' => 'linkedin',
				'additionalInstructions' => '',
			),
		);
	}

	/**
	 * Extract and sanitize a text field from an input array.
	 *
	 * @param array<string, mixed> $input Input array.
	 * @param string $key The key of the field to extract.
	 * @return string The sanitized text field.
	 */
	private function extract_sanitized_text_field( array $input, string $key ) {
		return isset( $input[$key] ) ? sanitize_text_field( (string) $input[$key] ) : '';
	}

	/**
	 * Generate the social copy.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_social_copy( $input ) {
		// Get the content; if there is no content, return.
		$content = $this->construct_content( $input['content'] );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		// Default copy
		$social_copy_context = $content; 
		$settings = ( class_exists( Settings::class ) ) ? Settings::get_settings() : false;
		// Explicit summary-only requests always run summarization, even when the
		// admin toggle is off. Otherwise honor the enable_summarization setting.
		$return_summary = isset( $input['returnSummary'] ) ? (bool) $input['returnSummary'] : false;
		$enable_summarization = ( $settings && ( $settings['enable_summarization'] ?? false ) );
		$maybe_summarize_content = $return_summary || $enable_summarization;

		// Attempt to summarize the content
		if ( $maybe_summarize_content ) { 

			$summarizer_prompt = self::get_summarizer_instructions(); 
			if ( $settings ) {
				$override_prompt = trim( $settings['system_prompts']['summarization_prompt'] ?? '' );
				if ('' !== $override_prompt) {
					$summarizer_prompt = $override_prompt;
				}
			}

			$summarize_content = $this->summarize_content( $content, $summarizer_prompt);
			if ( is_wp_error( $summarize_content ) ) {
				return $summarize_content;
			}
			// If we asked for a summary 
			if ( $return_summary ) {
				return array( $summarize_content );
			}
			// If the summary did not pass number checks 
			if ( ! $summarize_content['numberCheck']['valid'] ){
				return new WP_Error( 'number_checks_failed_on_summary', __( 'Summary did not pass number checks.', 'prc-social-builder' ) );
			}
			// Set the new context
			$social_copy_context = $summarize_content['copy'];
		}

		// Create the list of items to generate
		$social_package_list = ( ! $input['isDefaultList'] && isset( $input['copyList'] ) && is_array( $input['copyList'] ) ) ? 
			$input['copyList'] :
			$this->get_default_social_package();

		// For each item in the list: 
		foreach ( $social_package_list as $i => $social_copy_request ) {
			$platform = $this->extract_sanitized_text_field($social_copy_request, 'platform');
			if ( '' === $platform ) {
				$social_package_list[ $i ]['error'] = true;
				$social_package_list[ $i ]['copy'] = 'No platform provided.';
				continue;
			}
			// Optional extra instructions
			$requested_edits         = isset( $social_copy_request['requestedEdits'] )
				? sanitize_textarea_field( (string) $social_copy_request['requestedEdits'] )
				: '';
			$additional_instructions = $this->extract_sanitized_text_field($social_copy_request, 'additionalInstructions');

			$previous_output         = $this->extract_previous_output( $social_copy_request );
			// Character limit
			$char_limit              = $this->get_platform_char_limit( $platform );
			$style_guide             = '';
			if ( $settings && ( $settings['enable_style_guide'] ?? false ) ){
				$style_guide = self::get_social_style_guide(); 
				$override_prompt = trim( $settings['system_prompts']['style_guide_prompt'] ?? '' );
				if ('' !== $override_prompt) {
					$style_guide = $override_prompt;
				}
			}
			// Item specific instructions
			$system                  = $this->build_system_instruction( $platform, $style_guide, $additional_instructions, $char_limit, $requested_edits, $previous_output );
			$social_copy             = null; 
			$retry                   = false; 

			// Generat the copy, with one retry
			while ( is_null( $social_copy) ) {
				// Generate social copy
				$raw = $this->generate_text_via_ai_client( $system, $social_copy_context, $retry );
				// If there was an error, set social_copy to the error (this will exit the loop )
				if ( is_wp_error( $raw ) ) {
					$social_copy = $raw;
					continue; 
				}
				// Parse the results
				$raw    = trim( $raw );
				$parsed = $this->parse_ai_results( $raw, $char_limit );
				// If there was an error parsing the data, and we're on our first pass, retry
				if ( is_wp_error( $parsed ) && ! $retry ) { 
					$retry = true;
				// Otherwise exit with the results, error or no
				} else {
					$social_copy = $parsed;
				}
			}
			// If social copy is an error, set error to true and add the error message
			if ( is_wp_error( $social_copy ) ) {
				$social_package_list[ $i ]['error'] = true;
				$social_package_list[ $i ]['copy'] = $social_copy->get_error_message();
			// Otherwise, add the message
			} else {
				$social_package_list[ $i ]['copy'] = $social_copy['content'];
			}
		}

		$items = array();

		foreach ( $social_package_list as $index => $message ) {
			if ( isset($message['error']) && $message['error'] ){
				continue;
			}
			$items[] = array(
				'id'   => 'social-copy-' . $index,
				'text' => (string) $message['copy'],
			);
		}

		$skip_copy_edits = isset( $input['skipCopyEdits'] ) ? $input['skipCopyEdits'] : false ;

		if ( ! $skip_copy_edits && ! empty( $items ) ){
			$edited_items = Editorial_Passes::apply_batch( $items, $content );
			if ( is_wp_error( $edited_items ) ) {
				return $edited_items;
			} 	
			foreach ( $edited_items as $index => $edited_item ) {
				$id = (int) explode('-', $edited_item['id'])[2];
				$social_package_list[ $id ]['copy'] = $edited_item['text'];
				$items[ $index ]['text']            = $edited_item['text'];
			}
		}

		$number_checks = Number_Check::annotate_many( $items, $content );
		if ( null !== $number_checks ) {
			foreach ( $items as $num_check_item ) {
				$id = (int) explode('-', $num_check_item['id'])[2];
				$social_package_list[ $id ]['numberCheck'] = $number_checks[ $num_check_item['id'] ];
			}
		}

		return $social_package_list;
	}

	/**
	 * Extract and sanitize previousOutput from a copyList item.
	 *
	 * @param array<string, mixed> $social_copy_request Copy list item.
	 * @return array{copy: string}|null
	 */
	private function extract_previous_output( array $social_copy_request ): ?array {
		if ( ! isset( $social_copy_request['previousOutput'] ) || ! is_array( $social_copy_request['previousOutput'] ) ) {
			return null;
		}

		$copy = isset( $social_copy_request['previousOutput']['copy'] )
			? sanitize_textarea_field( (string) $social_copy_request['previousOutput']['copy'] )
			: '';
		if ( '' === $copy ) {
			return null;
		}

		$previous = array( 'copy' => $copy );

		return $previous;
	}

	/**
	 * Build the system instruction for the AI client.
	 *
	 * @param string                              $platform The platform to generate the copy for.
	 * @param string                              $tone The tone of the copy.
	 * @param string                              $additional_instructions Additional instructions from the editor.
	 * @param int                                 $char_limit The character limit for the copy.
	 * @param array{copy: string}|null $previous_output Previous generation to revise.
	 */
	private function build_system_instruction( string $platform, string $style_guide, string $additional_instructions, int $char_limit, string $requested_edits, ?array $previous_output = null): string {
		// Resolve base template: admin override > default.
		$override = '';
		$settings = array();
		if ( class_exists( Settings::class ) ) {
			$settings = Settings::get_settings();
			$override = trim( $settings['system_prompts']['generate-social-copy'] ?? '' );
		}

		if ( '' !== $override ) {
			$template = $override;
		} else {
			$template = self::get_default_system_prompt_template();
		}

		$instructions = strtr(
			$template,
			array(
				'{{platform}}'     => ucfirst( $platform ),
				'{{char_limit}}'   => (string) $char_limit,
			)
		);

		if ( '' !== $style_guide ) {	
			$instructions .= wp_sprintf( "\n\nAdhere to the style guide for voice, tone, and style: \n\n %s", $style_guide );
		}

		// Append per-network admin instructions.
		$network_instructions = trim( $settings['network_instructions'][ $platform ] ?? '' );
		if ( '' !== $network_instructions ) {
			$instructions .= "\n\nNetwork-level instructions:\n" . $network_instructions;
		}

		if ( null !== $previous_output ) {
			$instructions .= "\n\nRevise the following copy. Return a new result in the required JSON format.";
			if ('' !== $requested_edits ) {
				$instructions .= "\n\nThe editor has requested the following edits:\n" . $requested_edits;
			}
			$instructions .= "\n\nCopy to revise:\n" . $previous_output['copy'];
		}

		if ( '' !== $additional_instructions ) {
			$instructions .= "\n\nAdditional instructions from the editor:\n" . $additional_instructions;
		}

		$instructions .= "\n\n" . self::get_output_format_instruction();

		return $instructions;
	}

	/**
	 * Generate text via the AI client.
	 *
	 * @param string $system The system instruction for the AI client.
	 * @param string $content The content to generate the copy for.
	 * @param bool $retry Whether to retry the generation.
	 * @return string|WP_Error
	 */
	private function generate_text_via_ai_client( string $system, string $content, bool $retry ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error( 'ai_unavailable', __( 'AI client is not available.', 'prc-social-builder' ) );
		}

		$prompt = ( $retry ) ? 
		wp_sprintf( "%s\n\n%s\n\nPrevious output was invalid. Respond with ONLY valid JSON, e.g. [{\"content\":\"...\"}]:",
			$system, $content
		) : 
		wp_sprintf( "%s\n\n%s\n\nReturn ONLY a JSON array of objects with the key \"content\":",
			$system, $content
		);


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
	 * Parse the social copy response from the AI client.
	 *
	 * @param string $response The response from the AI client.
	 * @param int $char_limit The character limit for the copy.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function parse_ai_results( string $response, int $char_limit ) {
		$json_text = $response;
		if ( preg_match( '/\[.*\]/s', $response, $matches ) ) {
			$json_text = $matches[0];
		}

		$parsed = json_decode( $json_text, true );

		if ( ! is_array( $parsed ) || array() === $parsed ) {
			return new WP_Error( 'parse_error', __( 'Failed to parse AI copy response.', 'prc-social-builder' ) );
		}
		// Get the first item 
		$item = $parsed[0];
		if ( ! is_array( $item ) || empty( $item['content'] ) ) {
			return new WP_Error( 'missing_copy', __( 'AI failed to generate copy.', 'prc-social-builder' ) );
		}

		// Trim whitespace
		$text = trim( (string) $item['content'] );
		if ( '' === $text ) {
			return new WP_Error( 'blank_copy', __( 'AI generated empty copy.', 'prc-social-builder' ) );
		}

		// Shorten copy
		if ( mb_strlen( $text ) > $char_limit ) {
			$text = mb_substr( $text, 0, $char_limit - 1 ) . '…';
		}
		
		// Build results 
		$social_copy = array(
			'content'  => $text,
		);
		
		// Return results 
		return $social_copy;
	}

	/**
	 * Prepare the content for the social copy generation.
	 *
	 * @param int $post_id The post ID to prepare the content for.
	 * @param bool $use_children Whether to include the children of the post.
	 * @param array $unselect_ids The IDs of the children to unselect.
	 * @return array<string, mixed>|WP_Error
	 */
	public function prepare_content( $post_id, $use_children, $unselect_ids ){
		// Get the main post; return on error
		$main_post    = $this->gather_content( $post_id );
		if ( is_wp_error( $main_post ) ) {
			return $main_post;
		}
		$unselect_ids = is_array( $unselect_ids )
			? array_map( 'intval', $unselect_ids )
			: array();
		// If children, and functions to support 
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
				$child = $this->gather_content( $chapter_id );
				if ( is_wp_error( $child ) ) {
					continue;
				}
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

	/**
	 * Gather the content for the social copy generation.
	 *
	 * @param int $post_id The post ID to gather the content for.
	 * @return array<string, mixed>|WP_Error
	 */
	private function gather_content( $post_id ) { 
		// Return if the user lacks permissions
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

		$title   = $post->post_title;
		$content = wp_strip_all_tags( (string) $post->post_content, true );
		$content = mb_substr( $content, 0, 3000 );
		return array(
			'title' => $title,
			'content' => $content
		);
	}


	/**
	 * Construct the content for the social copy generation.
	 *
	 * @param array $content_list The content list to construct.
	 * @return string|WP_Error
	 */
	public function construct_content( $content_list ) {
        if ( ! is_array($content_list) ) {
			return new WP_Error( 'incorrectly_formatted_content', __( 'Content incorrectly formatted', 'prc-social-builder' ) );
		}

        $constructed_content = '';
        foreach( $content_list as $content_item ) {
			$content_type = $this->extract_sanitized_text_field($content_item, 'contentType');
            if ( 'preformatted' === $content_type ){
			    $content = $this->extract_sanitized_text_field($content_item, 'preformatted');
                if ( '' !== trim( $content ) ) {
                     $constructed_content = wp_sprintf( "%s\n\n%s", $constructed_content, $content );
                }
            }elseif( 'wp-post' === $content_type ){
                $content = $this->get_wp_post_content( $content_item );
				if ( is_wp_error( $content ) ) {
					return $content;
				}
				if ( '' !== $content ) {
                    $constructed_content = wp_sprintf( "%s\n\n%s", $constructed_content, $content );
                }
            }
        }

        return ( '' === $constructed_content ) ?  new WP_Error( 'missing_content', __( 'No content provided.', 'prc-social-builder' ) ) : $constructed_content;

    }

	private function summarize_content( string $content, string $summarizer_prompt){
		$system = $summarizer_prompt . "\n\n" . self::get_output_format_instruction();
		// Summarize the content
		$summarized_content = $this->generate_text_via_ai_client( $system, $content, false );
		if ( is_wp_error( $summarized_content ) ) {
			return $summarized_content;
		}

		// Parse content 
		$parsed_summarized_content = $this->parse_ai_results( $summarized_content, 3000 );
		if ( is_wp_error( $parsed_summarized_content ) ) {
			return $parsed_summarized_content;
		}
		// Run number checks 
		$passed_checks = Number_Check::annotate( $parsed_summarized_content['content'], $content );
		if ( null === $passed_checks ) { 
			return new WP_Error( 'number_checks_failed', __( 'Number checks failed on compression.', 'prc-social-builder' ) );
		}

		$summarized = array( 
			'platform' => 'summary',
			'copy'     => $parsed_summarized_content['content'],
			'numberCheck' => $passed_checks
		);

		return $summarized;
	}

	/**
	 * Get the content for the social copy generation.
	 *
	 * @param array $input The input to get the content for.
	 * @return string|WP_Error
	 */
	public function get_wp_post_content( $input ) {
        //Get the postid
        $post_id = isset( $input['postId'] ) ? (int) $input['postId'] : 0;
        // Should we include report children?
		$use_children = isset( $input['includeReportChildren'] ) ? (bool) $input['includeReportChildren'] : false;
		$decoded_unselect_ids = isset( $input['unselectedReportChildren'] )
			? json_decode( (string) $input['unselectedReportChildren'], true )
			: array();
		$unselect_ids         = is_array( $decoded_unselect_ids )
			? array_map( 'intval', $decoded_unselect_ids )
			: array();

		if ( ! $post_id ) {
			return new WP_Error( 'missing_post_id', __( 'No postId provided for copy generation.', 'prc-social-builder' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'Post not found.', 'prc-social-builder' ) );
		}

		$prepared_content = $this->prepare_content( $post_id, $use_children, $unselect_ids );
		if ( is_wp_error( $prepared_content ) ) {
			return $prepared_content;
		}
		$title         = $prepared_content['title'];
		$source_prefix = wp_sprintf("Post Title: %s\n\nPost Content:\n", $title );
		$content       = mb_substr(
			$prepared_content['content'],
			0,
			max( 0, Editorial_Passes::SOURCE_CHAR_LIMIT - mb_strlen( $source_prefix ) )
		);
		$titled_content = $source_prefix . $content;

        return $titled_content;
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
