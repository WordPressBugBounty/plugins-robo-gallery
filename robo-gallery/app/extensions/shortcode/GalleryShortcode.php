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

namespace RoboGallery\app\extensions\shortcode;

use RoboGallery\app\extensions\access\GalleryAccessManager;
use RoboGallery\app\extensions\fusionGrid\FusionGrid;
use RoboGallery\app\extensions\galleryType\GalleryTypeList;
use RoboGallery\app\extensions\stats\ViewCounter;

defined('WPINC') || exit;

/**
 * The [robo-gallery id=] shortcode, the one way a gallery is put on a page (the block, the Elementor widget
 * and the popup links render through it too), and the gallery on its own page after the content.
 * WordPress calls the global functions of init.php (robo_gallery_shortcode(), roboGalleryTag()): their
 * names are what others use - function_exists() / a direct call, remove_filter() of a theme.
 *
 * A gallery is shown only when the visitor may see it (GalleryAccessManager::canView()), its view is counted
 * (ViewCounter), then it goes to its output: Fusion Grid (fusionGrid\FusionGrid) or the classic types
 * (roboGallery, includes/frontend/).
 */
class GalleryShortcode
{
    const TAG = 'robo-gallery';

    public function register(): void
    {
        add_filter('the_content', 'roboGalleryTag');
        add_shortcode(self::TAG, 'robo_gallery_shortcode');
    }

    /**
     * @param mixed $attr the shortcode's attributes ('' without any)
     */
    public static function render($attr): string
    {
        if (!is_array($attr) || !isset($attr['id'])) {
            return '';
        }

        $attr['id'] = (int) $attr['id'];
        if (!$attr['id']) {
            return '';
        }

        /* The same access rules as the gallery's own page (canView(): WP status and
           password, direct-link / token mode), so embedding the shortcode elsewhere
           can't bypass them. */
        $accessManager = new GalleryAccessManager();
        if (!$accessManager->canView($attr['id'])) {
            // drafts, WP-private, other post types, token galleries: render nothing;
            // only a missing WP post password gets the password form
            return $accessManager->needsPasswordForm($attr['id']) ? get_the_password_form($attr['id']) : '';
        }

        $type = \rbsGalleryUtils::getTypeGallery($attr['id']);
        if (GalleryTypeList::isLocked($type)) {
            return '';
        }

        // who is counted and how: stats/ViewCounter.php
        (new ViewCounter())->count($attr['id']);

        if (GalleryTypeList::isGridScriptType($type)) {
            return (new FusionGrid($attr['id'], $type, $attr))->render();
        }

        return (string) (new \roboGallery($attr))->getGallery();
    }

    /**
     * A gallery's own page shows the gallery after its (usually empty) content.
     *
     * @param string $content
     * @return string
     */
    public static function appendToContent($content)
    {
        // excerpts (search results, archives, meta descriptions) run the_content too:
        // no full gallery render, assets and view count for them
        if (doing_filter('get_the_excerpt') || post_password_required()) {
            return $content;
        }
        if (ROBO_GALLERY_TYPE_POST != get_post_type() || !is_main_query()) {
            return $content;
        }
        return $content . do_shortcode('[' . self::TAG . ' id=' . (int) get_the_ID() . ']');
    }
}
