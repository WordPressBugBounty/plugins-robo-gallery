<?php defined('WPINC') || exit; ?>
<div id="<?php echo esc_attr($id); ?>" class="field small-12 columns">
	<?php echo $options['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the field config, written by the plugin ?>
</div>
