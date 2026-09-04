<?php
/**
 * Server render for prc-social/message.
 *
 * @package PRC\Platform\Social_Builder
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$attributes = $attributes ?? array();
$inner_html = isset( $content ) && is_string( $content ) ? $content : '';
$legacy     = $attributes['content'] ?? '';
$media_url  = $attributes['mediaUrl'] ?? '';
$platform   = $block->context['prc-social/platform'] ?? 'twitter';

if ( '' === trim( wp_strip_all_tags( $inner_html ) ) && '' === trim( (string) $legacy ) && empty( $media_url ) ) {
	return;
}

$display = '' !== trim( $inner_html ) ? $inner_html : wp_kses_post( (string) $legacy );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'prc-social-message prc-social-message--' . esc_attr( $platform ) ) ) ); ?>>
	<div class="prc-social-message__content">
		<?php echo wp_kses_post( $display ); ?>
	</div>
	<?php if ( ! empty( $media_url ) ) : ?>
		<div class="prc-social-message__media">
			<img src="<?php echo esc_url( $media_url ); ?>" alt="" loading="lazy" />
		</div>
	<?php endif; ?>
</div>
