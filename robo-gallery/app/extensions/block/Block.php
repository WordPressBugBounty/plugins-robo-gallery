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

namespace RoboGallery\app\extensions\block;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\extensions\fusionGrid\FusionGridAssets;
use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\imageManager\ImageManager;
use RoboGallery\app\extensions\restapi\models\OptionsPanelConfig;

defined('WPINC') || exit;

/**
 * The "Robo Gallery" block (robo/block-robo-gallery): a dynamic block, rendered by the [robo-gallery]
 * shortcode, so it has the same access checks. block.json here describes it (the React app
 * react_parts/block reads it too); the editor script and style are build/ (react_parts/block/build-js.bat).
 * Without the build the block still renders on the site, the editor just has no block UI.
 *
 * Stored posts: <!-- wp:robo/block-robo-gallery {"galleryid":12} /-->. Older blocks saved the ID as a
 * string ("12") and the editor state (forseReload, loaded) as attributes: the string is still read, the
 * rest is dropped when the post is saved again. popup ("" = the gallery on the page, "button", "link")
 * and popupText show a button / link that opens a Fusion Grid gallery in a popup; the defaults are
 * not stored, so old blocks show the gallery.
 *
 * In the editor the block shows a Fusion Grid gallery (GalleryTypeList::GRID_SCRIPT_TYPES) as the site
 * does - the grid script mounted in the editor's canvas; the other types keep the placeholder - and opens
 * the gallery's settings panel (react_parts/options) in a window. window.roboGalleryBlock (editorConfig())
 * gives the files of both, loaded only when they are needed.
 */
class Block
{
    const NAME = 'robo/block-robo-gallery';

    public function register(): void
    {
        add_action('init', array($this, 'registerBlock'));
        add_filter('block_type_metadata', array($this, 'withoutMissingAssets'));
        add_action('enqueue_block_editor_assets', array($this, 'editorConfig'));
    }

    public function registerBlock(): void
    {
        // WordPress 5.7 has no register_block_type(<dir>), only the metadata function
        if (!function_exists('register_block_type_from_metadata')) {
            return;
        }

        register_block_type_from_metadata(__DIR__, array(
            'render_callback' => array($this, 'render'),
        ));

        if (self::isBuilt()) {
            wp_set_script_translations(
                generate_block_asset_handle(self::NAME, 'editorScript'),
                'robo-gallery',
                ROBO_GALLERY_PATH . 'languages'
            );
        }
    }

    /**
     * block_type_metadata: without build/ the editor script and style are left out, else WordPress
     * reports the missing asset file.
     *
     * @param array $metadata
     * @return array
     */
    public function withoutMissingAssets($metadata)
    {
        if (is_array($metadata) && isset($metadata['name']) && self::NAME === $metadata['name'] && !self::isBuilt()) {
            unset($metadata['editorScript'], $metadata['editorStyle']);
        }

        return $metadata;
    }

    /**
     * enqueue_block_editor_assets: window.roboGalleryBlock before the block's script - the Fusion Grid types
     * (the preview and the "Robo Gallery" link of the text are for them), the grid script and its config for
     * the preview, the settings panel's files for "Edit options" (each null without its bundle).
     */
    public function editorConfig(): void
    {
        if (!self::isBuilt()) {
            return;
        }

        $options = null;
        if (ImageManager::isBuilt()) {
            $panelUrl = ROBO_GALLERY_URL . OptionsPanelConfig::PANEL_DIR;
            $options  = array(
                'script'       => add_query_arg('ver', ROBO_GALLERY_VERSION, $panelUrl . 'static/js/main.js'),
                // the lazy chunks of the bundle (react_parts/options src/publicPath.js)
                'publicPath'   => $panelUrl,
                'hoverPreview' => add_query_arg('ver', ROBO_GALLERY_VERSION, FusionGridAssets::url('hover-preview.js')),
                'fontAwesome'  => add_query_arg('ver', ROBO_GALLERY_VERSION, ROBO_GALLERY_URL . 'css/gallery.font.css'),
            );

            // the Content tab of Fusion Grid is the image manager: the media modal, user settings, the legacy window
            ImageManager::enqueueDependencies();
        }

        $config = array(
            'gridTypes' => GalleryTypeList::GRID_SCRIPT_TYPES,
            'preview'   => self::previewConfig(),
            'options'   => $options,
        );

        // JSON_HEX_*: nothing in a URL can end the inline script
        wp_add_inline_script(
            generate_block_asset_handle(self::NAME, 'editorScript'),
            'window.roboGalleryBlock = ' . wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
    }

    /**
     * The preview of a Fusion Grid gallery: its script (react_parts/grid) mounts a container in the canvas
     * with window.robogallery_config_id_<id> - the values fusionGrid\FusionGrid::config() prints, the same for
     * every gallery here (the loading bar's defaults, no access token: the editor's user is logged in).
     * The rest comes over REST (robofields), with the same access checks as on the site.
     */
    private static function previewConfig(): ?array
    {
        if (!is_file(FusionGridAssets::path('main.js'))) {
            return null;
        }

        $optionConfig = GalleryOptions::getOptionConfig();

        return array(
            'script' => add_query_arg('ver', ROBO_GALLERY_VERSION, FusionGridAssets::url('main.js')),
            'config' => array(
                'restUrl'       => esc_url_raw(get_rest_url()),
                'wp_rest'       => wp_create_nonce('wp_rest'),
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'loadingColor'  => GalleryOptions::sanitizeColor('', $optionConfig['loadingColor']),
                'loadingSize'   => (int) $optionConfig['loadingSize']['default'],
                'errorImageUrl' => esc_url_raw(ROBO_GALLERY_URL . 'images/'),
                'accessToken'   => '',
                'debug'         => false,
            ),
        );
    }

    private static function isBuilt(): bool
    {
        return is_file(__DIR__ . '/build/index.js') && is_file(__DIR__ . '/build/index.asset.php');
    }

    /**
     * @param array $attributes
     * @return string
     */
    public function render($attributes): string
    {
        $galleryId = isset($attributes['galleryid']) && is_numeric($attributes['galleryid'])
            ? (int) $attributes['galleryid']
            : 0;

        // a gallery, whatever its type (an empty type is grid)
        if ($galleryId <= 0 || ROBO_GALLERY_TYPE_POST !== get_post_type($galleryId)) {
            // a hint for who can fix the post; visitors get nothing
            if (!current_user_can('edit_posts')) {
                return '';
            }

            return sprintf(
                '<div><strong>%s</strong>: %s</div>',
                'Robo Gallery',
                esc_html__("You didn't select any Robo Gallery item in editor. Please select one from the list or create new gallery", 'robo-gallery')
            );
        }

        if (!function_exists('robo_gallery_shortcode')) {
            return '';
        }

        $attr = array('id' => $galleryId);

        // a button / link that opens the gallery in a popup (extensions/popup/GalleryPopup.php checks the values)
        if (isset($attributes['popup']) && is_string($attributes['popup']) && '' !== $attributes['popup']) {
            $attr['popup']      = $attributes['popup'];
            $attr['popup_text'] = isset($attributes['popupText']) && is_string($attributes['popupText']) ? $attributes['popupText'] : '';
        }

        // the shortcode's path: the same access checks (GalleryAccessManager::canView()) and output
        return (string) robo_gallery_shortcode($attr);
    }
}
