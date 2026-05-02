<?php
declare(strict_types=1);

$attributes = $attributes ?? array();
$content    = $attributes['content'] ?? '';
$media_url  = $attributes['mediaUrl'] ?? '';
$platform   = $block->context['prc-social/platform'] ?? 'twitter';

if ( empty( $content ) && empty( $media_url ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'prc-social-message prc-social-message--' . esc_attr( $platform ) ) ); ?>>
	<div class="prc-social-message__content">
		<?php echo wp_kses_post( $content ); ?>
	</div>
	<?php if ( ! empty( $media_url ) ) : ?>
		<div class="prc-social-message__media">
			<img src="<?php echo esc_url( $media_url ); ?>" alt="" loading="lazy" />
		</div>
	<?php endif; ?>
</div>
