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

defined('WPINC') || exit;
?>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'Big Release of 2025!', 'robo-gallery' );?></h2>
    <p>
        <?php echo wp_kses_post( __( 'We are happy to announce the new version of <strong>Robo Gallery</strong> with a lot of new features and improvements. 
        The main goal of this release is to make the gallery more user-friendly and easy to use. 
        We hope you will enjoy the new version of Robo Gallery.', 'robo-gallery' ) );?>
    </p>


    <ol>
        <li><strong><?php esc_html_e( 'Albums with Unlimited Depth', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Mix different content types and use', 'robo-gallery' ); ?> <strong><?php esc_html_e( 'Cover Gallery Mode', 'robo-gallery' ); ?></strong>.</li>
        <li><strong><?php esc_html_e( 'Enhanced Polaroid Layout', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Choose panel locations:', 'robo-gallery' ); ?> <strong><?php esc_html_e( 'top, bottom, left, or right', 'robo-gallery' ); ?></strong>.</li>
        <li><strong><?php esc_html_e( 'Advanced Social Sharing', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Supports', 'robo-gallery' ); ?> <strong><?php esc_html_e( '18+ platforms', 'robo-gallery' ); ?></strong>, <?php esc_html_e( 'including', 'robo-gallery' ); ?> <strong><?php esc_html_e( 'Twitter, Reddit, LinkedIn, and Pinterest', 'robo-gallery' ); ?></strong>.</li>
        <li><strong><?php esc_html_e( 'Expanded Video Gallery Support', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Now compatible with 10+ platforms, including', 'robo-gallery' ); ?> <strong><?php esc_html_e( 'YouTube, Facebook, Twitch, SoundCloud, Streamable, Vimeo, Wistia, Mixcloud and DailyMotion', 'robo-gallery' ); ?></strong>.</li>
        <li><strong><?php esc_html_e( 'New Gallery Types', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Fusion Grid, Horizontal Masonry, Vertical Masonry, Enhanced Polaroid, Justify + 7 Classic Types.', 'robo-gallery' ); ?></li>
        <li><strong><?php esc_html_e( 'Fancy Lightbox', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Includes slideshow mode, full-screen view, zoom, download, and social sharing.', 'robo-gallery' ); ?></li>
        <li><strong><?php esc_html_e( 'Mobile Optimized', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Enjoy', 'robo-gallery' ); ?> <strong><?php esc_html_e( 'responsive grids, smooth hover effects, and retina-ready visuals.', 'robo-gallery' ); ?></strong></li>
        <li><strong><?php esc_html_e( 'New Hover Effects', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'Fresh, modern animations for a sleek user experience.', 'robo-gallery' ); ?></li>
    </ol>

    <p>
    <?php esc_html_e( "We're actively developing Robo Gallery v5 and planning", 'robo-gallery' ); ?> <strong><?php esc_html_e( 'feature releases every two weeks', 'robo-gallery' ); ?></strong>!
    </p>

    <h2><?php esc_html_e( 'How To Configure Your First Gallery?', 'robo-gallery' );?></h2>
   
	<ol>
		<li><?php 
        /* translators: %s: create gallery link */
        echo wp_kses_post( wp_sprintf( __('Click <a href="%s" target="_blank">Add New Robo Gallery link</a> in the left side menu.', 'robo-gallery' ), admin_url('edit.php?post_type='.ROBO_GALLERY_TYPE_POST.'&showDialog=1')) ); ?><br/></li>
		<li><?php esc_html_e( 'Select the gallery type in the Gallery Wizard', 'robo-gallery' ); ?><br/></li>
        <li><?php esc_html_e( 'Enter a Gallery Title at the top. If left blank, a title will be generated automatically when you save.', 'robo-gallery' ); ?><br/></li>
		<li><?php esc_html_e( 'Click the Manage Images button on the right to upload or manage your gallery resources.', 'robo-gallery' ); ?><br/></li>
		<li><?php esc_html_e( "After saving or publishing, you'll find the Permalink field below the title, which provides a direct link to your gallery on the front end.", 'robo-gallery' ); ?><br/></li>
	</ol>
    <p class="rbsDashboardGallery-p">
    	<strong><?php esc_html_e( "That's it! Your first Robo Gallery is ready!", 'robo-gallery' ); ?></strong>
    </p>
</div>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'Robo Gallery is Compatible with Gutenberg & Elementor!', 'robo-gallery' );?></h2>
    <p class="rbsDashboardGallery-p">
    	<?php esc_html_e( ' The latest version of Robo Gallery has been tested and works seamlessly with the Gutenberg editor. Simply create a gallery, copy the shortcode, and paste it into a Gutenberg post.', 'robo-gallery' ); ?>
    </p>
</div>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'Where to Find the Shortcode?', 'robo-gallery' );?></h2>
    <p class="rbsDashboardGallery-p">
	<ol>
		<li><?php esc_html_e( 'In the Gallery List, the last column contains the shortcode for each gallery. Click on the field, and the shortcode will be copied to your clipboard.', 'robo-gallery' ); ?><br/></li>
        <li><?php esc_html_e( 'You can also find it in the Gallery Settings under the right-side column widget.', 'robo-gallery' ); ?><br/></li>
	</ol>
    </p>
</div>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'Need some Help ?', 'robo-gallery' );?></h2>
    <p class="rbsDashboardGallery-p"><?php
        /* translators: %s: support link */
        echo wp_kses_post( wp_sprintf(  __( 'If you have any questions or run into issues with installation or configuration, feel free to <a href="%s"  target="_blank">submit a support ticket here</a>. ', 'robo-gallery' ), 'https://wordpress.org/support/plugin/robo-gallery' ) ); 
        ?> <br/>  <?php 
        /* translators: %s: support link */
        echo wp_kses_post( wp_sprintf(  __( 'Have a feature request or a suggestion for improving the plugin? Let us know by <a href="%s"  target="_blank">sending us a message</a> with a brief description!', 'robo-gallery' ), 'https://wordpress.org/support/plugin/robo-gallery' ) ); 
        ?>
    </p>
    <a class="button button-primary button-hero " href="https://wordpress.org/support/plugin/robo-gallery" target="_blank">POST SUPPORT TICKET</a>
</div>
