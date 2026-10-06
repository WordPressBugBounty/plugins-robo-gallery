<?php
/* 
*      Robo Gallery     
*      Version: 5.2.6 - 24868
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('WPINC')) {
    exit;
}

$nonce_name = 'robo-gallery-clearstats';
$countPosts = wp_count_posts(ROBO_GALLERY_TYPE_POST);

// every gallery, not only the ones with a view count: Total Images counts them all
$args = array(
    'post_type'      => ROBO_GALLERY_TYPE_POST,
    'posts_per_page' => -1,
    'fields'         => 'ids',
);
$allViews  = 0;
$loop      = new WP_Query($args);
$allImages = 0;

$clearStat = 0;
if (isset($_GET['clearStat']) && $_GET['clearStat'] == 1) {

    if (isset($_REQUEST['_wpnonce']) && wp_verify_nonce( $_REQUEST['_wpnonce'], $nonce_name)) {
        if (current_user_can('edit_posts')) {
            $clearStat = 1;
        }
    }
}

foreach ($loop->posts as $galleryId) {

    $images = get_post_meta($galleryId, ROBO_GALLERY_PREFIX . 'galleryImages', true);
    if (is_array($images)) {
        $allImages += count($images);
    }

    // reset as before: only the galleries that have a counter
    if ($clearStat && metadata_exists('post', $galleryId, 'gallery_views_count')) {
        delete_post_meta($galleryId, 'gallery_views_count');
        add_post_meta($galleryId, 'gallery_views_count', '0');
    }
    $allViews += (int) get_post_meta($galleryId, 'gallery_views_count', true);
}

$nonce = wp_create_nonce($nonce_name);
$url   = admin_url("edit.php?post_type=robo_gallery_table&page=robo-gallery-stats&clearStat=1&_wpnonce=" . $nonce)
?>
<div class="wrap">
	<h1  class="rbs-stats">
		<?php esc_html_e( 'Robo Gallery Statistics', 'robo-gallery' );?>
		<a id="robo_gallery_reset_stat" href="<?php echo esc_url($url); ?>" class="page-title-action"><?php esc_html_e( 'Reset', 'robo-gallery' );?></a>
	</h1>

	<?php if ( $clearStat ) {?>
		<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible">
			<p><strong><?php esc_html_e( 'Statistics reset successfully!', 'robo-gallery' );?></strong></p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.'); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress ?></span>
			</button>
		</div>
	<?php }?>

<br>

<?php

if (!function_exists('rbs_stats_tabs')) {
    function rbs_stats_tabs($current = 'gallery')
    {
        $tabs = array(
            'gallery' => __('Gallery Statistics', 'robo-gallery'),
            'export'  => __('Images Statistics', 'robo-gallery'),
        );
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( 'edit.php?post_type=robo_gallery_table&page=robo-gallery-stats&tab=' . $tab ) . '">' . esc_html( $name ) . '</a>';
        }
        echo '</h2>';
    }
}
$tab = 'gallery';

switch ($tab) {
    case 'gallery':
        ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label ><?php esc_html_e( 'Total Views', 'robo-gallery' );?></label>
				</th>
				<td>
					<p><?php echo (int) ( $allViews ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label ><?php esc_html_e( 'Total Images', 'robo-gallery' );?></label>
				</th>
				<td>
					<p><?php echo (int) ( $allImages ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label ><?php esc_html_e( 'Total Galleries', 'robo-gallery' );?></label>
				</th>
				<td>
					<p><?php echo (int) ( $countPosts->publish + $countPosts->draft + $countPosts->trash ); ?></p>
				</td>
			</tr>
			<tr>
				<td><hr></td>
			</tr>
			<tr>
				<th scope="row">
					<label ><?php esc_html_e( 'Published', 'robo-gallery' );?></label>
				</th>
				<td>
					<p><?php echo (int) ( $countPosts->publish ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label ><?php esc_html_e( 'Drafts', 'robo-gallery' );?></label>
				</th>
				<td>
					<p><?php echo (int) ( $countPosts->draft ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label ><?php esc_html_e( 'Trash', 'robo-gallery' );?></label>
				</th>
				<td>
					<p><?php echo (int) ( $countPosts->trash ); ?></p>
				</td>
			</tr>

		</tbody>
	</table>
	<?php
break;

    default:
    case 'images':
        ?>
<?php
break;

    case 'import':

}?>
</div>
<?php