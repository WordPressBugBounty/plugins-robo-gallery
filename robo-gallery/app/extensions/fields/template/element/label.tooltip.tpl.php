<?php defined('WPINC') || exit; ?>
<div class="robo_gallery_tooltip">
	<h5 class="inline-block robo-gallery-help-label"><?php echo wp_kses_post($label); ?></h5>
 
	<span 
		class="dashicons dashicons-info robo-gallery-help-button" 
		data-help="help_content_<?php echo esc_attr($id); ?>"
	></span>
	<span class="robo_gallery_tooltiptext">
		<?php esc_html_e('Click for information', 'robo-gallery'); ?>	
	</span>

	<?php if($help) : ?>
		<div id="help_content_<?php echo esc_attr($id); ?>" class="robo-gallery-help-dialog">
			<?php echo wp_kses_post($help); ?>
		</div>
	<?php endif; ?>
</div>