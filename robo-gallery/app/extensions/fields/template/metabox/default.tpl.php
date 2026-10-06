<?php defined('WPINC') || exit; ?>
<div class="roboGalleryFields">
	<?php if ($contentBefore) : ?>
		<div class="metabox content-before row">
			<div class="large-12 columns">
				<?php echo $contentBefore; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the metabox config (buttons, a script), written by the plugin ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ($content) : ?>
		<div class="metabox content row">
			<div class="large-12 columns">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the metabox config or its template, written by the plugin ?>
			</div>
		</div>
	<?php endif; ?>

	<?php foreach ($fields as $field) : ?>
		<div id="wrap-field-<?php echo esc_attr($field['id']); ?>"
		     class="row metabox wrap-field <?php echo esc_attr("{$field['type']}-{$field['view']}"); ?>" 
		     <?php if( $field['is_hide'] ) echo ' style="display:none;"';?> 
		>
			<?php if ($field['is_lock']) : ?>
				<div class="lock-overlay  twoj-gallery-option-premium">
					<div class="lock-message">
						<?php esc_html_e('Premium function', 'robo-gallery'); ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($field['is_new']) : ?>
				<button class="button warning tiny twoj-gallery-option-new"><?php esc_html_e('New Feature', 'robo-gallery'); ?></button>
			<?php endif; ?>


			<?php if ($field['contentBefore']) : ?>
				<div class="content-before small-12 columns">
					<?php echo $field['contentBefore']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the field config, written by the plugin ?>
				</div>
			<?php endif; ?>

			<?php if ($field['content']) : ?>
				<div class="content small-12 columns">
					<?php echo $field['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the field config, written by the plugin ?>
				</div>
			<?php endif; ?>

			<?php echo $field['field']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the field rendered (and escaped) by its own template ?>

			<?php if ($field['contentAfter']) : ?>
				<div class="content-after small-12 columns">
					<?php echo $field['contentAfter']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the field config, written by the plugin ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($field['contentAfterBlock']) : ?>
			<?php echo $field['contentAfterBlock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the field config, written by the plugin ?>
		<?php endif; ?>
		
	<?php endforeach; ?>

	<?php if ($contentAfter) : ?>
		<div class="metabox content-after row">
			<div class=" large-12 columns">
				<?php echo $contentAfter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML of the metabox config (buttons, a script), written by the plugin ?>
			</div>
		</div>
	<?php endif; ?>
</div>
