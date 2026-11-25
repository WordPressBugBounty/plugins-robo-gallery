<?php
/* 
*      Robo Gallery     
*      Version: 6.0.0 - 27445
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('WPINC') || exit;

/* Robo Watermark, our plugin that marks the gallery's images: its settings page when it is active (known by its
   constant, whatever its folder is called; the page needs manage_options, as the plugin registers it), else its
   page on our site. A new tab, so the gallery being edited stays open with its unsaved changes */
$watermarkActive = defined('ROBO_WATERMARK_PLUGIN_DIR') && current_user_can('manage_options');
$watermarkUrl    = $watermarkActive ? admin_url('admin.php?page=robo-watermark') : 'https://www.robogallery.co/robo-watermark/';
?>
<div class="robo-gallery-tool">
	<span class="robo-gallery-tool-title"><?php esc_html_e('Watermark', 'robo-gallery'); ?></span>
	<a class="robo-gallery-tool-link" href="<?php echo esc_url($watermarkUrl); ?>" target="_blank" rel="noopener noreferrer">
		<span class="dashicons <?php echo $watermarkActive ? 'dashicons-admin-generic' : 'dashicons-shield'; ?>" aria-hidden="true"></span>
		<span class="robo-gallery-tool-link-text">
			<?php
			if ($watermarkActive) {
				esc_html_e('Watermark Settings', 'robo-gallery');
			} else {
				esc_html_e('Robo Watermark', 'robo-gallery');
			}
			?>
		</span>
		<span class="dashicons dashicons-external" aria-hidden="true"></span>
		<span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'robo-gallery'); ?></span>
	</a>
	<p class="robo-gallery-tool-description">
		<?php
		if ($watermarkActive) {
			esc_html_e('The text or logo watermark of your gallery images: settings, marking and restoring.', 'robo-gallery');
		} else {
			esc_html_e('Protect the images of your galleries with a text or logo watermark.', 'robo-gallery');
		}
		?>
	</p>
</div>
