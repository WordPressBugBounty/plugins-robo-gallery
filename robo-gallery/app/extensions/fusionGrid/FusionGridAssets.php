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

namespace RoboGallery\app\extensions\fusionGrid;

use RoboGallery\app\extensions\settings\SettingsPage;

defined('WPINC') || exit;

/**
 * The files of a Fusion Grid gallery: the grid script (react_parts/grid, built into assets/), its CSS and
 * the site's own files of Settings -> Compatibility. The same handles and ways as the classic galleries'
 * (includes/frontend/modules/class/assets.php):
 * - enqueued (wp_enqueue_*), by default;
 * - "Forced include" (Settings -> Compatibility, the Elementor editor, the shortcode's assetsIncludeForced):
 *   tags after the gallery, each once per page - for themes that never call wp_head() / wp_footer().
 */
class FusionGridAssets
{
    const SCRIPT_HANDLE = 'robo-gallery-robo-grid-script';
    const STYLE_HANDLE  = 'robo-gallery-simple';

    /* the forced tags already printed in this request */
    private static $printed = array();

    /**
     * @param array $attr the shortcode's attributes
     * @return string the forced tags to print after the gallery ('' when the files are enqueued)
     */
    public static function add(array $attr): string
    {
        $files = self::files();

        if (!self::forced($attr)) {
            foreach ($files['js'] as $handle => $url) {
                wp_enqueue_script($handle, $url, array(), ROBO_GALLERY_VERSION, true);
            }
            foreach ($files['css'] as $handle => $url) {
                wp_enqueue_style($handle, $url, array(), ROBO_GALLERY_VERSION);
            }
            return '';
        }

        $tags = '';
        foreach ($files['js'] as $handle => $url) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            $tags .= self::once('js-' . $handle, ' <script type="text/javascript" src="' . esc_url($url) . '"></script>');
        }
        foreach ($files['css'] as $handle => $url) {
            // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
            $tags .= self::once('css-' . $handle, '<link id="' . esc_attr($handle) . '" rel="stylesheet" type="text/css" href="' . esc_url($url) . '">');
        }
        return $tags;
    }

    /** the URL of a file of assets/ (the grid script, the hover preview of the settings) */
    public static function url(string $file): string
    {
        return ROBO_GALLERY_URL . 'app/extensions/fusionGrid/assets/' . $file;
    }

    /** the path of a file of assets/: the bundles exist only once react_parts/grid is built */
    public static function path(string $file): string
    {
        return ROBO_GALLERY_PATH . 'app/extensions/fusionGrid/assets/' . $file;
    }

    /* handle => URL; the gallery's own first, then the site's (as the classic galleries) */
    private static function files(): array
    {
        return array(
            'js'  => array(self::SCRIPT_HANDLE => self::url('main.js')) + self::customFiles('js'),
            'css' => array(self::STYLE_HANDLE => self::url('simple.css')) + self::customFiles('css'),
        );
    }

    private static function forced(array $attr): bool
    {
        if ('forced' === get_option(ROBO_GALLERY_PREFIX . 'jqueryVersion', 'robo')) {
            return true;
        }
        // the Elementor editor
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['action']) && 'elementor' === $_GET['action']) {
            return true;
        }
        return !empty($attr['assetsIncludeForced']);
    }

    /* Settings -> Compatibility: the site's CSS / JS files, checked as on save */
    private static function customFiles(string $type): array
    {
        $list = get_option(ROBO_GALLERY_PREFIX . $type . 'Files', '');
        $list = (new SettingsPage())->sanitizeFileList(is_string($list) ? $list : '');

        $files = array();
        foreach ('' === $list ? array() : explode("\n", $list) as $i => $path) {
            $files['robo-gallery-' . $type . '-custom-file' . $i] = site_url($path);
        }
        return $files;
    }

    private static function once(string $key, string $tag): string
    {
        if (isset(self::$printed[$key])) {
            return '';
        }
        self::$printed[$key] = true;
        return $tag;
    }
}
