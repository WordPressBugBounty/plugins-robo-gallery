<?php defined('WPINC') || exit; ?>
<div class="field small-2 columns">
	<div class="switch-element">
		<?php if ($label) : ?>
			<p><?php echo wp_kses_post($label); ?></p>
		<?php endif; ?>

		<div id="field-element-<?php echo esc_attr($id); ?>" class="switch <?php echo esc_attr($options['size']); ?>">
			<input id="<?php echo esc_attr($id); ?>" class="switch-input"
			       type="checkbox" name="<?php echo esc_attr($name); ?>"
			       value="1" <?php echo $value ? 'checked' : '' ?>
				   data-dependents='<?php echo esc_attr($dependents); ?>' >
			<label class="switch-paddle" for="<?php echo esc_attr($id); ?>">
				<span class="switch-active" aria-hidden="true">
					<?php echo wp_kses_post($options['onLabel']); ?>
				</span>
				<span class="switch-inactive" aria-hidden="true">
					<?php echo wp_kses_post($options['offLabel']); ?>
				</span>
			</label>
		</div>

		<?php if ($description) : ?>
			<p class="help-text"><?php echo wp_kses_post($description); ?></p>
		<?php endif; ?>
	</div>
</div>
