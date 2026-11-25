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

/*
 * The features as cards, by group. The Overview page is one of the few places where the Pro
 * features may be listed (root CLAUDE.md, "The Pro offer"); the 'pro' group is what works only
 * with the key - keep it in step with the locks (GalleryTypeList::PRO_TYPES, proOnly fields).
 */
$featureGroups = array(
    array(
        'title'    => __( 'Features', 'robo-gallery' ),
        'pro'      => false,
        'features' => array(
            array( 'dashicons-screenoptions', __( 'Fusion Grid', 'robo-gallery' ), __( 'Grid, Masonry, Horizontal Masonry, Justified and Slider layouts in one gallery, set up with a live preview.', 'robo-gallery' ) ),
            array( 'dashicons-admin-post', __( 'Posts Gallery', 'robo-gallery' ), __( "Your site's posts as a gallery: the newest ones or hand-picked, each with the image you choose.", 'robo-gallery' ) ),
            array( 'dashicons-format-video', __( 'Video from 10+ Platforms', 'robo-gallery' ), __( 'YouTube, Vimeo, Facebook, Twitch, Wistia, DailyMotion, SoundCloud and more, played in the lightbox.', 'robo-gallery' ) ),
            array( 'dashicons-fullscreen-alt', __( 'Fancy Lightbox', 'robo-gallery' ), __( 'Slideshow, full screen, zoom, download and sharing to 15+ networks, including X, Reddit, LinkedIn and Pinterest.', 'robo-gallery' ) ),
            array( 'dashicons-admin-appearance', __( 'Hover Effects', 'robo-gallery' ), __( 'Modern hover animations with your own colors, text highlights and the texts to show.', 'robo-gallery' ) ),
            array( 'dashicons-images-alt2', __( 'Image Manager', 'robo-gallery' ), __( 'Drag & drop uploads, import from links or a server folder, duplicate search and bulk editing.', 'robo-gallery' ) ),
            array( 'dashicons-search', __( 'Search & Filter', 'robo-gallery' ), __( 'A search field and filters by tags, sub-galleries and type: photos, videos or links.', 'robo-gallery' ) ),
            array( 'dashicons-heart', __( 'Likes & Comments', 'robo-gallery' ), __( 'Visitors like images and logged-in users comment on them, in the lightbox or on the polaroid panel.', 'robo-gallery' ) ),
            array( 'dashicons-art', __( 'Option Themes', 'robo-gallery' ), __( "Save a gallery's look as a theme and give it to other galleries in one click.", 'robo-gallery' ) ),
            array( 'dashicons-block-default', __( 'Block, Elementor & Popups', 'robo-gallery' ), __( 'A Gutenberg block and an Elementor widget; open any gallery in a popup from a button or a link.', 'robo-gallery' ) ),
            array( 'dashicons-lock', __( 'Access Control', 'robo-gallery' ), __( 'Choose who may see a gallery, or share it with a private link.', 'robo-gallery' ) ),
            array( 'dashicons-smartphone', __( 'Mobile & SEO Ready', 'robo-gallery' ), __( 'Responsive, retina-ready grids; search engines and feeds get plain linked images and sitemaps.', 'robo-gallery' ) ),
            array( 'dashicons-layout', __( 'Classic Gallery Types', 'robo-gallery' ), __( 'Grid, Masonry, Mosaic, Polaroid, Wall Style and Slider with 40+ ready-made presets.', 'robo-gallery' ) ),
        ),
    ),
    array(
        'title'    => __( 'Pro Features', 'robo-gallery' ),
        'pro'      => true,
        'features' => array(
            array( 'dashicons-video-alt3', __( 'YouTube Gallery', 'robo-gallery' ), __( 'Channels, playlists and single videos as a Fusion Grid, played in the lightbox; hide, reorder or retitle any video.', 'robo-gallery' ) ),
            array( 'dashicons-yes-alt', __( 'Client Proofing', 'robo-gallery' ), __( 'Clients open the gallery by links of their own, choose images, add notes and a comment and send the choice; you get an e-mail and the file names for Lightroom.', 'robo-gallery' ) ),
            array( 'dashicons-editor-textcolor', __( 'Hover Fonts', 'robo-gallery' ), __( '50+ Google Fonts for the hover title and description.', 'robo-gallery' ) ),
            array( 'dashicons-portfolio', __( 'Nested Albums', 'robo-gallery' ), __( 'Albums up to 25 levels deep (two without Pro), with their own icon and color, the cover image shown or hidden.', 'robo-gallery' ) ),
            array( 'dashicons-id-alt', __( 'Polaroid Panels', 'robo-gallery' ), __( "A panel with the image's texts and buttons on the top, bottom, left or right of every thumbnail.", 'robo-gallery' ) ),
            array( 'dashicons-admin-links', __( 'Links, Videos & Tags', 'robo-gallery' ), __( 'Give every image its own link, video and tags in the image window.', 'robo-gallery' ) ),
            array( 'dashicons-admin-customizer', __( 'Your Own Navigation', 'robo-gallery' ), __( 'The color of menus and buttons, button labels and the slideshow timing.', 'robo-gallery' ) ),
        ),
    ),
);
?>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'Everything Your Galleries Need', 'robo-gallery' ); ?></h2>
    <p class="rbsDashboardGallery-p">
        <?php esc_html_e( 'Photos, videos and posts in fast, responsive galleries, set up in a few clicks.', 'robo-gallery' ); ?>
    </p>

    <?php foreach ( $featureGroups as $group ) : ?>
        <h3 class="rbsDashboardGallery-group">
            <?php echo esc_html( $group['title'] ); ?>
            <?php if ( $group['pro'] ) : ?><span class="rbsDashboardGallery-pro">PRO</span><?php endif; ?>
        </h3>
        <div class="rbsDashboardGallery-features">
            <?php foreach ( $group['features'] as list( $icon, $title, $text ) ) : ?>
                <div class="rbsDashboardGallery-feature<?php echo $group['pro'] ? ' is-pro' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
                    <h4><?php echo esc_html( $title ); ?></h4>
                    <p><?php echo esc_html( $text ); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'How To Configure Your First Gallery?', 'robo-gallery' );?></h2>

	<ol>
		<li><?php
        /* translators: %s: link that opens the gallery type dialog */
        echo wp_kses_post( wp_sprintf( __( 'Click <a href="%s">Add Gallery / Images</a> in the Robo Gallery menu.', 'robo-gallery' ), admin_url( 'edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&showDialog=1' ) ) ); ?></li>
		<li><?php esc_html_e( 'Choose what the gallery shows: Fusion Grid with your images, posts or YouTube videos and its layout, or one of the classic types and its preset.', 'robo-gallery' ); ?></li>
		<li><?php esc_html_e( 'Enter a title. If left blank, one is made when you save.', 'robo-gallery' ); ?></li>
		<li><?php esc_html_e( 'Add the content: a Fusion Grid gallery on its Content tab (upload, pick from the media library, import from links or a server folder), a classic type in the Images box.', 'robo-gallery' ); ?></li>
		<li><?php esc_html_e( 'Set up the look on the other tabs of the settings panel, with a live preview of the hover effects, or apply an option theme.', 'robo-gallery' ); ?></li>
		<li><?php esc_html_e( 'Publish the gallery and put it on a page: the Robo Gallery block, the Elementor widget or the shortcode from the Gallery Tools box. The gallery also has a page of its own (the permalink under the title).', 'robo-gallery' ); ?></li>
	</ol>
    <p class="rbsDashboardGallery-p">
    	<strong><?php esc_html_e( "That's it! Your first Robo Gallery is ready!", 'robo-gallery' ); ?></strong>
    </p>
</div>

<div class="rbsDashboardGallery-div">
    <h2><?php esc_html_e( 'Works Where You Build Your Pages', 'robo-gallery' );?></h2>
    <ul class="rbsDashboardGallery-list">
		<li><strong><?php esc_html_e( 'Block editor', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'the Robo Gallery block draws a Fusion Grid gallery right in the editor, and its "Edit options" opens the gallery settings without leaving the page; a link of the text can open a Fusion Grid gallery in a popup.', 'robo-gallery' ); ?></li>
		<li><strong><?php esc_html_e( 'Elementor', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'the Robo Gallery widget: the gallery on the page or, for Fusion Grid, in a popup from a button or a link.', 'robo-gallery' ); ?></li>
		<li><strong><?php esc_html_e( 'Classic editor', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'the "Add Robo Gallery" button above the editor.', 'robo-gallery' ); ?></li>
		<li><strong><?php esc_html_e( 'Widgets', 'robo-gallery' ); ?></strong> - <?php esc_html_e( 'the Robo Gallery Widget for sidebars and footers.', 'robo-gallery' ); ?></li>
		<li><strong><?php esc_html_e( 'Shortcode', 'robo-gallery' ); ?></strong> - <?php
            /* translators: %s: the shortcode */
            echo wp_kses_post( wp_sprintf( __( '%s anywhere shortcodes work: copy it from the Shortcode column of the galleries list (a click copies it) or from the Gallery Tools box of the gallery editor.', 'robo-gallery' ), '<code>[robo-gallery id="123"]</code>' ) ); ?></li>
    </ul>
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
