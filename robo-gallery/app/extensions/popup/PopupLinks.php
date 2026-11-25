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

namespace RoboGallery\app\extensions\popup;

use RoboGallery\app\extensions\access\GalleryAccessManager;
use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\staticGallery\StaticGallery;

defined('WPINC') || exit;

/**
 * Links in the text that open a gallery in a popup: the block editor's "Robo Gallery" link format
 * (react_parts/block src/galleryLink.js: <a href="#rg12" class="robo-gallery-popup-link" data-robo-gallery="12">)
 * and any link to #rg<id> written by hand (a Button block, the classic editor). For each gallery linked in
 * a post's content its window is put once after the content (the shortcode with popup="window": the same
 * access checks and markup as GalleryPopup) and the links get data-robo-popup-open, so the grid script
 * (react_parts/grid src/popup/) opens it and gives the focus back to the link. Fusion Grid types only, as
 * GalleryPopup; a link to another gallery, or one the visitor may not view, stays a plain link.
 *
 * Not in feeds, REST answers and other static requests (the gallery would be printed in full there),
 * nor in excerpts (wp_trim_excerpt() runs the_content: the window's text would end up in the excerpt).
 */
class PopupLinks
{
    // the opening tag of a link
    const LINK_TAG = '~<a\s[^>]*>~i';

    public function register(): void
    {
        // after wpautop (10) and do_shortcode (11): the windows are added as they are
        add_filter('the_content', array($this, 'filterContent'), 20);
    }

    /**
     * @param string $content
     * @return string
     */
    public function filterContent($content)
    {
        if (!is_string($content) || (false === strpos($content, '#rg') && false === strpos($content, 'data-robo-gallery'))) {
            return $content;
        }

        if (is_admin() || is_feed() || doing_filter('get_the_excerpt') || StaticGallery::isStaticRequest() || !function_exists('robo_gallery_shortcode')) {
            return $content;
        }

        // gallery id => its window (array id, html) or null: once per content
        $windows = array();

        $content = preg_replace_callback(self::LINK_TAG, function ($match) use (&$windows) {
            $tag       = $match[0];
            $galleryId = self::galleryOf($tag);
            if (!$galleryId) {
                return $tag;
            }

            if (!array_key_exists($galleryId, $windows)) {
                $windows[$galleryId] = self::window($galleryId);
            }
            // a link to an album or image of the gallery (#rg12-c5-i40) opens by its hash: the gallery then
            // shows that part; the trigger's click would keep the hash out
            if (!$windows[$galleryId] || false !== stripos($tag, 'data-robo-popup-open') || preg_match('~\shref\s*=\s*["\']#rg\d+-[ci]~i', $tag)) {
                return $tag;
            }

            return substr($tag, 0, -1)
                . ' data-robo-popup-open="' . esc_attr($windows[$galleryId]['id']) . '" aria-haspopup="dialog">';
        }, $content);

        foreach ($windows as $window) {
            if ($window) {
                $content .= $window['html'];
            }
        }

        return $content;
    }

    /**
     * The gallery a link opens: data-robo-gallery of the link format, else an href that is a gallery's
     * link on this page (#rg12, #rg12-c5-i40 - react_parts/grid src/features/deepLink.js).
     *
     * @param string $tag the opening tag of a link
     */
    public static function galleryOf(string $tag): int
    {
        if (preg_match('~\sdata-robo-gallery\s*=\s*["\']?(\d+)~i', $tag, $match)) {
            return (int) $match[1];
        }

        if (preg_match('~\shref\s*=\s*["\']#rg(\d+)(?:-c\d+)?(?:-i\d+)?["\']~i', $tag, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    /**
     * The gallery's window, or null: not a Fusion Grid gallery, or the visitor may not view it (no window
     * with a password form in it - the shortcode would give the form instead of the gallery).
     *
     * @return array|null id: the window's element id, html: the window
     */
    private static function window(int $galleryId): ?array
    {
        if ($galleryId <= 0 || ROBO_GALLERY_TYPE_POST !== get_post_type($galleryId)) {
            return null;
        }

        if (!GalleryTypeList::isGridScriptType(\rbsGalleryUtils::getTypeGallery($galleryId))) {
            return null;
        }

        if (!(new GalleryAccessManager())->canView($galleryId)) {
            return null;
        }

        $html = (string) robo_gallery_shortcode(array('id' => $galleryId, 'popup' => GalleryPopup::WINDOW));

        // the id GalleryPopup::render() gave the window
        if (!preg_match('~\sid="(robo-gallery-popup-[A-Za-z0-9_-]+)"~', $html, $match)) {
            return null;
        }

        return array('id' => $match[1], 'html' => $html);
    }
}
