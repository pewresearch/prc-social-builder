<?php
/**
 * Publishable social message text from paragraph inner blocks.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts between plain social copy and `core/paragraph` inner blocks.
 */
class Message_Text {

	const PARAGRAPH_BLOCK = 'core/paragraph';
	const MESSAGE_BLOCK   = 'prc-social/message';

	/**
	 * Plain publishable copy from a parsed `prc-social/message` block.
	 *
	 * Inner paragraphs win. The legacy `content` attribute is read only when
	 * inner blocks are empty.
	 *
	 * @param array $block Parsed block.
	 * @return string
	 */
	public static function from_block( array $block ): string {
		$inner = $block['innerBlocks'] ?? array();
		if ( is_array( $inner ) && array() !== $inner ) {
			return self::from_inner_blocks( $inner );
		}

		return trim( (string) ( $block['attrs']['content'] ?? '' ) );
	}

	/**
	 * Plain publishable copy from message inner blocks.
	 *
	 * @param array $inner_blocks Parsed inner blocks.
	 * @return string
	 */
	public static function from_inner_blocks( array $inner_blocks ): string {
		$parts = array();
		foreach ( $inner_blocks as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( self::PARAGRAPH_BLOCK !== ( $child['blockName'] ?? '' ) ) {
				continue;
			}
			$text = self::plain_text_from_paragraph( $child );
			if ( '' !== $text ) {
				$parts[] = $text;
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Parsed `core/paragraph` blocks for a copy string.
	 *
	 * @param string $text Plain copy.
	 * @return array
	 */
	public static function paragraph_blocks( string $text ): array {
		$chunks = self::split_copy( $text );
		$blocks = array();
		foreach ( $chunks as $chunk ) {
			$html     = self::paragraph_html( $chunk );
			$blocks[] = array(
				'blockName'    => self::PARAGRAPH_BLOCK,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			);
		}

		return $blocks;
	}

	/**
	 * Parsed `prc-social/message` block wrapping copy as paragraphs.
	 *
	 * @param string $text  Plain copy.
	 * @param array  $attrs Message attributes.
	 * @return array
	 */
	public static function message_block( string $text, array $attrs = array() ): array {
		unset( $attrs['content'] );
		$paragraphs = self::paragraph_blocks( $text );

		return array(
			'blockName'    => self::MESSAGE_BLOCK,
			'attrs'        => $attrs,
			'innerBlocks'  => $paragraphs,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $paragraphs ), null ),
		);
	}

	/**
	 * Split copy into paragraph strings.
	 *
	 * @param string $text Plain copy.
	 * @return list<string>
	 */
	public static function split_copy( string $text ): array {
		$trimmed = trim( $text );
		if ( '' === $trimmed ) {
			return array( '' );
		}

		$chunks = preg_split( '/\n{2,}/', $trimmed );
		if ( ! is_array( $chunks ) || array() === $chunks ) {
			return array( '' );
		}

		$chunks = array_map( 'trim', $chunks );
		$chunks = array_values( array_filter( $chunks, static fn( string $chunk ) => '' !== $chunk ) );

		return array() === $chunks ? array( '' ) : $chunks;
	}

	/**
	 * Plain text from one paragraph block.
	 *
	 * @param array $child Parsed paragraph block.
	 * @return string
	 */
	private static function plain_text_from_paragraph( array $child ): string {
		$html = (string) ( $child['innerHTML'] ?? '' );
		if ( '' === $html ) {
			$html = (string) ( $child['attrs']['content'] ?? '' );
		}

		$replaced = preg_replace( '/<br\s*\/?>\n?/i', "\n", $html );
		if ( is_string( $replaced ) ) {
			$html = $replaced;
		}

		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Saved paragraph markup for one copy chunk.
	 *
	 * @param string $chunk Plain text.
	 * @return string
	 */
	private static function paragraph_html( string $chunk ): string {
		if ( '' === $chunk ) {
			return '<p></p>';
		}

		return '<p>' . nl2br( esc_html( $chunk ), false ) . '</p>';
	}
}
