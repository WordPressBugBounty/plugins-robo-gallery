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

/* the bundle also holds the image manager - the Content tab of Fusion Grid, the "Images" box of the other types
   (imageManager\ImageManager, registered on admin_enqueue_scripts when the type has one): its config and wp.media
   come first */
$imageManagerHandle = \RoboGallery\app\extensions\imageManager\ImageManager::HANDLE;
wp_register_script(
    ROBO_GALLERY_ASSETS_PREFIX . 'robogrid-options',
    ROBO_GALLERY_FIELDS_URL . 'template/content/robogrid/static/js/main.js',
    wp_script_is($imageManagerHandle, 'registered') ? [$imageManagerHandle] : [],
    ROBO_GALLERY_VERSION,
    true
);

/* the header above the settings names the type and opens the type dialog (TypeDialog, only for who may create galleries) to change it;
   no ID here: a new gallery's auto-draft has no type meta yet (read by ID it would be grid), its type is ?rsg_gallery_type=.
   The panel shows the tabs of the type's option set (GalleryOptions::setForType()): robogrid, slider or
   classic (the settings of the old boxes, classic\ClassicOptions). */
$typeId    = rbsGalleryUtils::getTypeGallery();
$optionSet = \RoboGallery\app\GalleryOptions::setForType($typeId);

/* window.roboGalleryHoverPreview of the gallery: the hover preview card on the Thumbs tab of the robogrid settings
   (the posts type has them too) */
if (\RoboGallery\app\extensions\galleryType\GalleryTypeList::isGridScriptType($typeId)) {
    wp_enqueue_script(
        ROBO_GALLERY_ASSETS_PREFIX . 'robogrid-hover-preview',
        \RoboGallery\app\extensions\fusionGrid\FusionGridAssets::url('hover-preview.js'),
        [],
        ROBO_GALLERY_VERSION,
        true
    );
}

/* Font Awesome 4.7 of the classic galleries: the icons of the Hover tab (FontAwesomeField). The panel is in a
   Shadow DOM, where only the @font-face of the page works; enqueued this late it is printed in the footer */
if (\RoboGallery\app\GalleryOptions::SET_CLASSIC === $optionSet) {
    wp_enqueue_style(
        ROBO_GALLERY_ASSETS_PREFIX . 'options-font-awesome',
        ROBO_GALLERY_URL . 'css/gallery.font.css',
        [],
        ROBO_GALLERY_VERSION
    );
}

/* the Content tab of the posts and YouTube types chooses the image of a post / video in the media library window (wp.media) */
if (in_array($optionSet, [\RoboGallery\app\GalleryOptions::SET_POSTS, \RoboGallery\app\GalleryOptions::SET_YOUTUBE], true) && current_user_can('upload_files')) {
    wp_enqueue_media();
}

$js_vars = [
    'endpoint'     => get_rest_url(null, 'robogallery/v1'),
    'images_nonce' => wp_create_nonce('wp_rest'),
];

global $post;
if (isset($post->ID)) {
    echo "<script>window['robogallery_new_id']=" . (int) $post->ID . ";</script>";
}

wp_enqueue_script(ROBO_GALLERY_ASSETS_PREFIX . 'robogrid-options');
wp_localize_script(ROBO_GALLERY_ASSETS_PREFIX . 'robogrid-options', 'robogridVars', $js_vars);

$blockPro = true;
if (defined('ROBO_GALLERY_TYR') && ROBO_GALLERY_TYR == 1) {
    if (defined('ROBO_GALLERY_KEY') && ROBO_GALLERY_KEY == 1) {
        if (rbsGalleryUtils::compareVersion('3.0')) {
            $blockPro = false;
        }
    }
    // the Pro fields of the old boxes needed any key plugin, not a 3.0 one
    if (\RoboGallery\app\GalleryOptions::SET_CLASSIC === $optionSet) {
        $blockPro = false;
    }
}

/* "Clone Gallery" (its own box): the gallery renders with the settings of another one, the panel
   edits the gallery's own; the title of the source, empty without one */
$cloneSourceTitle = '';
if (isset($post->ID) && \RoboGallery\app\GalleryOptions::SET_CLASSIC === $optionSet) {
    $cloneSourceId = \RoboGallery\app\extensions\cloneSource\CloneSource::resolve((int) $post->ID);
    if ($cloneSourceId !== (int) $post->ID) {
        // the raw title: the panel prints it as text (get_the_title() gives HTML entities)
        $cloneSourceTitle = (string) get_post_field('post_title', $cloneSourceId, 'raw');
        $cloneSourceTitle = '' !== $cloneSourceTitle ? $cloneSourceTitle : '#' . $cloneSourceId;
    }
}

$galleryType    =\RoboGallery\app\extensions\galleryType\GalleryTypeList::getByType($typeId);
$typeName       = $galleryType ? $galleryType['name'] : '';
$postTypeObject = get_post_type_object(ROBO_GALLERY_TYPE_POST);
// before the first save the header has no type change, album hierarchy or option theme: the type
// of a new gallery was just chosen in the dialog, its change link fails the nonce check, and an
// auto-draft that may never be saved belongs in no album and should not be linked to a theme
$isNew          = 'auto-draft' === get_post_status($post);
$canChangeType  = $postTypeObject && !$isNew && current_user_can($postTypeObject->cap->create_posts) && current_user_can('edit_post', (int) $post->ID);

echo '
<div class="RoboGalleryOptions" robogallery_id="' . (int) $post->ID . '"></div>
<div class="RoboGalleryV5Wrapper" robogallery_id="' . (int) $post->ID . '"></div>
<script>
window["robogallery_option_url"] = "' . esc_url(ROBO_GALLERY_FIELDS_URL . 'template/content/robogrid/') . '";
window["robogallery_config"] = {
    imagesUrl: "' . esc_url(ROBO_GALLERY_FIELDS_URL . 'template/content/robogrid/') . '",
    restUrl: "' . esc_url(get_rest_url()) . '",
    wp_rest: "' . esc_js(wp_create_nonce('wp_rest')) . '",
    blockPro: ' . ($blockPro ? 'true' : 'false') . ',
    proUrl: ' . wp_json_encode(esc_url_raw(ROBO_GALLERY_URL_UPDATEPRO)) . ',
    keyUpdate: ' . wp_json_encode(\RoboGallery\app\extensions\proofing\Proofing::keyUpdate()) . ',
    typeName: ' . wp_json_encode($typeName) . ',
    galleryType: ' . wp_json_encode($typeId) . ',
    canChangeType: ' . ($canChangeType ? 'true' : 'false') . ',
    isNew: ' . ($isNew ? 'true' : 'false') . ',
    optionSet: ' . wp_json_encode($optionSet) . ',
    cloneSourceTitle: ' . wp_json_encode(wp_strip_all_tags($cloneSourceTitle)) . ',
};
window["robogallery_config_id_' . (int) $post->ID . '"] = {
    restUrl: "' . esc_url(get_rest_url()) . '",
    wp_rest: "' . esc_js(wp_create_nonce('wp_rest')) . '",
    errorImageUrl: "' . esc_url(site_url('wp-content/plugins/robo-gallery/images/')) . '",
    debug: true,
};
</script>';
