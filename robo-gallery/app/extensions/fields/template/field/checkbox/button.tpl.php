<?php defined('WPINC') || exit; ?>
<div class="field small-12 columns">
	<div id="field-element-<?php echo esc_attr($id); ?>" class="button-element">
		<input id="<?php echo esc_attr($id); ?>"
		       type="checkbox" name="<?php echo esc_attr($name); ?>"
		       value="1" <?php echo $value ? 'checked' : '' ?>
		       data-dependents='<?php echo esc_attr($dependents); ?>' >
		<label class="button" for="<?php echo esc_attr($id); ?>">
			<?php echo wp_kses_post($label); ?>
		</label>
	</div>

	<?php if ($description) : ?>
		<p class="help-text"><?php echo wp_kses_post($description); ?></p>
	<?php endif; ?>
</div>
