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

namespace RoboGallery\app\extensions\imageManager;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\extensions\media\MediaFields;
use RoboGallery\app\extensions\restapi\models\ServerFolder;
use RoboGallery\app\extensions\settings\SettingsPage;

defined('WPINC') || exit;

/**
 * The image manager (react_parts/options/src/imageManager, a chunk of the
 * settings panel's bundle): the Content tab of the panel for PANEL_TYPES, the
 * "Images" metabox, which the bundle mounts it into, for the others. It keeps the
 * gallery's list in the rsg_galleryImages meta (an array of ID strings, read by
 * the frontend, REST and the statistics), saved over REST
 * (restapi/endpoints/GalleryImagesController) as soon as it changes. Its "Legacy
 * mode" is the media window the images field had before (legacy/gallery.lib.js).
 */
class ImageManager
{
    const BOX_ID        = 'robo_gallery_image_manager';
    // a handle without a file: the config and the scripts the image manager needs; the settings panel's script depends on it
    const HANDLE        = ROBO_GALLERY_ASSETS_PREFIX . 'image-manager';
    // the settings panel's bundle (fields/template/content/robogrid/options.tpl.php)
    const BUNDLE        = 'fields/template/content/robogrid/static/js/main.js';
    const LEGACY_HANDLE = ROBO_GALLERY_ASSETS_PREFIX . 'image-manager-legacy';
    const LEGACY_FILE   = 'legacy/gallery.lib.js';

    // the gallery types that have a list of images (the others take theirs from a source, e.g. YouTube)
    const TYPES = array(
        'robogrid',
        'grid',
        'gridpro',
        'masonry',
        'masonrypro',
        'mosaic',
        'mosaicpro',
        'polaroid',
        'polaroidpro',
        'wallstylepro',
        'slider',
        'custom',
    );

    // the types whose images are edited in the settings panel (its Content tab), not in the box
    const PANEL_TYPES = array('robogrid');

    public function register(): void
    {
        add_action('add_meta_boxes_' . ROBO_GALLERY_TYPE_POST, array($this, 'addBox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    /**
     * @param \WP_Post $post
     */
    public function addBox($post): void
    {
        if (!self::isForType() || in_array(\rbsGalleryUtils::getTypeGallery(), self::PANEL_TYPES, true)) {
            return;
        }

        add_meta_box(
            self::BOX_ID,
            __('Images', 'robo-gallery'),
            array($this, 'renderBox'),
            ROBO_GALLERY_TYPE_POST,
            'normal',
            'high'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function renderBox($post): void
    {
        if (!is_file(self::bundlePath())) {
            echo '<p>' . esc_html__('The image manager is not built yet: run build-react.bat in the plugin folder.', 'robo-gallery') . '</p>';
            return;
        }

        echo '<div class="RoboGalleryImageManager" data-gallery-id="' . (int) $post->ID . '"></div>';
        echo '<noscript>' . esc_html__('The image manager needs JavaScript.', 'robo-gallery') . '</noscript>';
    }

    /**
     * @param string $hook
     */
    public function enqueueAssets($hook): void
    {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || ROBO_GALLERY_TYPE_POST !== $screen->post_type || !self::isForType() || !is_file(self::bundlePath())) {
            return;
        }

        self::enqueueDependencies();

        // JSON_HEX_*: no "</script>" or "&" from a translated label can end the inline script
        wp_add_inline_script(
            self::HANDLE,
            'window.roboGalleryImageManager = ' . wp_json_encode(self::config(\rbsGalleryUtils::getTypeGallery()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );

        self::showImageFields(\rbsGalleryUtils::getTypeGallery());
    }

    /**
     * The handle without a file and what the app needs on the page. Also used by the block editor
     * (block\Block), where the settings panel with its Content tab opens in a window: there the
     * config comes with the panel's (restapi\models\OptionsPanelConfig), not from this handle.
     */
    public static function enqueueDependencies(): void
    {
        // the "Add from library" button opens the core media modal
        wp_enqueue_media();

        // utils: getUserSetting / setUserSetting, where the view settings of the user are kept
        $deps = array('media-views', 'utils');
        if (is_file(__DIR__ . '/' . self::LEGACY_FILE)) {
            wp_register_script(self::LEGACY_HANDLE, plugin_dir_url(__FILE__) . self::LEGACY_FILE, array('jquery', 'media-views'), ROBO_GALLERY_VERSION, true);
            $deps[] = self::LEGACY_HANDLE;
        }

        wp_register_script(self::HANDLE, false, $deps, ROBO_GALLERY_VERSION, true);
        wp_enqueue_script(self::HANDLE);
    }

    /**
     * MediaFields hides the Robo Gallery fields of an image in the media modal
     * everywhere; in the gallery editor (the legacy window, "Add from library")
     * they are shown again, the classic ones only for the classic types. The rows
     * have no other style to keep.
     */
    private static function showImageFields(string $galleryType): void
    {
        $fields = self::hasClassicFields($galleryType) ? MediaFields::FIELDS : array_diff(MediaFields::FIELDS, MediaFields::CLASSIC_FIELDS);

        $rows = array();
        foreach ($fields as $name) {
            $rows[] = 'table.compat-attachment-fields tr.compat-field-' . ROBO_GALLERY_PREFIX . 'gallery_' . $name;
        }

        wp_register_style(self::HANDLE, false, array(), ROBO_GALLERY_VERSION);
        wp_enqueue_style(self::HANDLE);
        wp_add_inline_style(self::HANDLE, implode(',', $rows) . '{display:block !important;}');
    }

    /**
     * What the app needs from WordPress; the gallery ID comes from the container.
     *
     * @param string $galleryType the gallery's type (rbsGalleryUtils::getTypeGallery())
     */
    public static function config(string $galleryType): array
    {
        $effects = array();
        foreach (MediaFields::effectChoices() as $value => $label) {
            $effects[] = array('value' => (string) $value, 'label' => $label);
        }

        return array(
            'restUrl'        => esc_url_raw(rest_url()),
            'nonce'          => wp_create_nonce('wp_rest'),
            'pro'            => defined('ROBO_GALLERY_TYR') && ROBO_GALLERY_TYR,
            // where the image window's locked fields lead (the menu's "Pro Version" link)
            'proUrl'         => esc_url_raw(ROBO_GALLERY_URL_UPDATEPRO),
            'canUpload'      => current_user_can('upload_files'),
            'maxUploadBytes' => (int) wp_max_upload_size(),
            'columnsMax'     => MediaFields::COLUMNS_MAX,
            'effects'        => $effects,
            'classicFields'  => self::hasClassicFields($galleryType),
            // the image's edit screen and its place in the media library
            'adminUrl'       => esc_url_raw(admin_url()),
            // which video links the gallery can play: robogrid plays more than the classic ones
            'galleryType'    => $galleryType,
            // "From a server folder": administrators by default (restapi/models/ServerFolder)
            'canBrowseServer' => ServerFolder::canBrowse(),
            // YouTube playlists are read whole with the key, only their 15 newest videos without it
            'youtubeApiKey'   => '' !== (string) get_option(ROBO_GALLERY_PREFIX . 'youtubeApiKey', ''),
            // where the key is set, for those who may (the settings page needs manage_options)
            'youtubeSettingsUrl' => current_user_can('manage_options')
                ? esc_url_raw(admin_url('edit.php?post_type=' . ROBO_GALLERY_TYPE_POST . '&page=' . SettingsPage::SLUG . '&tab=youtube'))
                : '',
        );
    }

    /**
     * The type's galleries use MediaFields::CLASSIC_FIELDS (column span, hover effect of an image).
     */
    private static function hasClassicFields(string $galleryType): bool
    {
        return GalleryOptions::SET_CLASSIC === GalleryOptions::setForType($galleryType);
    }

    /**
     * The edited gallery has a type with a list of images.
     */
    private static function isForType(): bool
    {
        return in_array(\rbsGalleryUtils::getTypeGallery(), self::TYPES, true);
    }

    /**
     * The settings panel's bundle, which holds the image manager, is built.
     */
    public static function isBuilt(): bool
    {
        return is_file(self::bundlePath());
    }

    private static function bundlePath(): string
    {
        return ROBO_GALLERY_APP_EXTENSIONS_PATH . self::BUNDLE;
    }
}
