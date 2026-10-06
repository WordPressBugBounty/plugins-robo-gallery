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

namespace RoboGallery\app\extensions\galleryType;

defined('WPINC') || exit;

/**
 * The admin screens of the gallery post type (list, editor and the pages in
 * its menu, except Statistics): a body class for js/themes.select.js and the
 * editor's "Robo Gallery updated." messages.
 */
class GalleryScreens
{
    const BODY_CLASS = ROBO_GALLERY_NAMESPACE . 'theme_listing';

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_filter('admin_body_class', array($this, 'addBodyClass'));
        add_filter('post_updated_messages', array($this, 'addUpdatedMessages'));
    }

    private function isGalleryScreen(): bool
    {
        global $typenow;

        return ROBO_GALLERY_TYPE_POST === $typenow
            && !(isset($_GET['page']) && 'robo-gallery-stats' === $_GET['page']);
    }

    /**
     * @param string $classes
     * @return string
     */
    public function addBodyClass($classes)
    {
        return $this->isGalleryScreen() ? $classes . ' ' . self::BODY_CLASS : $classes;
    }

    /**
     * @param array $messages
     * @return array
     */
    public function addUpdatedMessages($messages)
    {
        $post = get_post();
        if (!$post || !$this->isGalleryScreen()) {
            return $messages;
        }

        $messages[ROBO_GALLERY_TYPE_POST] = array(
            0  => '', // Unused. Messages start at index 1.
            1  => __('Robo Gallery updated.', 'robo-gallery'),
            2  => __('Custom field updated.', 'robo-gallery'),
            3  => __('Custom field deleted.', 'robo-gallery'),
            4  => __('Robo Gallery updated.', 'robo-gallery'),
            5  => isset($_GET['revision'])
                /* translators: %s: date and time of the revision */
                ? sprintf(__('Robo Gallery restored to revision from %s', 'robo-gallery'), wp_post_revision_title((int) $_GET['revision'], false))
                : false,
            6  => __('Robo Gallery published.', 'robo-gallery'),
            7  => __('Robo Gallery saved.', 'robo-gallery'),
            8  => __('Robo Gallery submitted.', 'robo-gallery'),
            9  => sprintf(
                /* translators: %s: date the gallery is scheduled for */
                __('Robo Gallery scheduled for: <strong>%1$s</strong>.', 'robo-gallery'),
                date_i18n(__('M j, Y @ G:i'), strtotime($post->post_date)) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            ),
            10 => __('Robo Gallery draft updated.', 'robo-gallery'),
        );

        return $messages;
    }
}
