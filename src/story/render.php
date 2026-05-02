<?php
declare(strict_types=1);

$attributes = $attributes ?? array();
$platform   = $attributes['platform'] ?? 'instagram';
$media_url  = $attributes['mediaUrl'] ?? '';
$media_type = $attributes['mediaType'] ?? 'image';
$caption    = $attributes['caption'] ?? '';
$overlay    = $attributes['overlayText'] ?? '';

if ( empty( $media_url ) && empty( $caption ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'prc-social-story prc-social-story--' . esc_attr( $platform ) ) ); ?>>
	<?php if ( ! empty( $media_url ) ) : ?>
		<div class="prc-social-story__media">
			<?php if ( 'video' === $media_type ) : ?>
				<video src="<?php echo esc_url( $media_url ); ?>" controls></video>
			<?php else : ?>
				<img src="<?php echo esc_url( $media_url ); ?>" alt="" loading="lazy" />
			<?php endif; ?>
			<?php if ( ! empty( $overlay ) ) : ?>
				<div class="prc-social-story__overlay"><?php echo wp_kses_post( $overlay ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $caption ) ) : ?>
		<div class="prc-social-story__caption"><?php echo wp_kses_post( $caption ); ?></div>
	<?php endif; ?>
</div>
