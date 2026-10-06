<?php
/**
 * Character limits for Social Builder platforms.
 *
 * Reads includes/platform-limits.php. The editor UI gets the same numbers through
 * get_editor_payload(), localized as `prcSocialBuilderLimits`.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-platform character limits.
 *
 * Each entry has a `max` (the network's hard limit, shown in the editor) and a
 * `target` (what generation aims for and enforces, never above `max`).
 *
 * @since 1.5.0
 */
class Platform_Limits {

	const SOCIAL        = 'social';
	const STORY         = 'story';
	const FIELDS        = 'fields';
	const DEFAULT_ENTRY = 'default';

	/**
	 * Decoded limits, keyed by section.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $limits = null;

	/**
	 * Path to the limits file.
	 */
	public static function get_file_path(): string {
		return dirname( __DIR__ ) . '/platform-limits.php';
	}

	/**
	 * All limits from the limits file.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		if ( null === self::$limits ) {
			$limits       = require self::get_file_path();
			self::$limits = is_array( $limits ) ? $limits : array();
		}

		return self::$limits;
	}

	/**
	 * Limits the editor UI needs. Server-only `fields` are left out.
	 *
	 * @return array{default: array{max: int, target: int}, social: array<string, array{max: int, target: int}>, story: array<string, array{max: int, target: int}>}
	 */
	public static function get_editor_payload(): array {
		$all = self::get_all();

		return array(
			self::DEFAULT_ENTRY => $all[ self::DEFAULT_ENTRY ] ?? array(),
			self::SOCIAL        => $all[ self::SOCIAL ] ?? array(),
			self::STORY         => $all[ self::STORY ] ?? array(),
		);
	}

	/**
	 * Drop the cached limits. Used by tests.
	 */
	public static function reset(): void {
		self::$limits = null;
	}

	/**
	 * Limits for one platform in a section, falling back to the section's default entry, then the top-level default.
	 *
	 * @param string $section  One of the section constants.
	 * @param string $platform Platform slug.
	 * @return array{max: int, target: int}
	 */
	public static function get( string $section, string $platform ): array {
		$all      = self::get_all();
		$fallback = $all[ self::DEFAULT_ENTRY ] ?? array();
		$entry    = $all[ $section ][ $platform ] ?? $all[ $section ][ strtolower( $platform ) ] ?? $all[ $section ][ self::DEFAULT_ENTRY ] ?? $fallback;

		$max = (int) ( $entry['max'] ?? $fallback['max'] ?? 280 );

		return array(
			'max'    => $max,
			'target' => min( $max, (int) ( $entry['target'] ?? $max ) ),
		);
	}

	/**
	 * Length generation aims for and enforces for a social platform.
	 *
	 * @param string $platform Platform slug.
	 * @return int
	 */
	public static function get_social_target( string $platform ): int {
		return self::get( self::SOCIAL, $platform )['target'];
	}

	/**
	 * Length generation aims for and enforces for a story platform caption.
	 *
	 * @param string $platform Platform slug.
	 * @return int
	 */
	public static function get_story_target( string $platform ): int {
		return self::get( self::STORY, $platform )['target'];
	}

	/**
	 * Length generation aims for and enforces for a named text field.
	 *
	 * @param string $field Field name.
	 * @return int
	 */
	public static function get_field_target( string $field ): int {
		return self::get( self::FIELDS, $field )['target'];
	}
}
