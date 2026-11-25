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

namespace RoboGallery\app\extensions\postType;

defined('WPINC') || exit;

use RoboGallery\app\extensions\restapi\endpoints\GalleryPostsController;

/**
 * The gallery post type (ROBO_GALLERY_TYPE_POST). Its name, the slug "gallery" and
 * the REST base "robogallery" are stored in posts, links and rewrite rules - keep them.
 * Rewrite rules are flushed only by activation\Install (bump its REWRITE_VERSION
 * when 'rewrite' changes).
 */
class GalleryPostType
{
    public function register(): void
    {
        // the default priority, after language\TextDomain (the labels are translated)
        add_action('init', array($this, 'registerPostType'));
    }

    public function registerPostType(): void
    {
        register_post_type(ROBO_GALLERY_TYPE_POST, array(
            'labels'                => $this->getLabels(),
            'description'           => __('Description. text ', 'robo-gallery'),
            'rewrite'               => array('slug' => 'gallery', 'with_front' => true),
            'public'                => true,
            'has_archive'           => false,
            'hierarchical'          => true,
            // no 'page-attributes': the core box would post the parent and order of the page load and undo
            // a change made meanwhile in the album hierarchy window (react_parts/options). The old
            // "Categories Manager" setting (rbs_opt_categoryShow) that added it is gone; its option stays.
            'supports'              => array('title', 'comments', 'author'),
            'menu_icon'             => path_join(ROBO_GALLERY_URL, 'images/admin/robo_gallery_icon_32.png'),
            'show_in_menu'          => true,
            'menu_position'         => 10,
            'show_in_rest'          => true,
            'rest_base'             => 'robogallery',
            // same routes/format as core, plus the plugin's direct-link/token read rules
            'rest_controller_class' => GalleryPostsController::class,
        ));
    }

    private function getLabels(): array
    {
        return array(
            'name'               => 'Robo Gallery',
            'singular_name'      => __('Robo Gallery', 'robo-gallery'),
            'all_items'          => __('Manage Galleries', 'robo-gallery'),
            'add_new'            => __('Add Gallery / Images', 'robo-gallery'),
            'add_new_item'       => __('Add New Robo Gallery', 'robo-gallery'),
            'edit_item'          => __('Edit Gallery', 'robo-gallery'),
            'view_item'          => __('View Robo Gallery', 'robo-gallery'),
            'search_items'       => __('Search Robo Galleries', 'robo-gallery'),
            'parent_item_colon'  => __('Parent Robo Galleries:', 'robo-gallery'),
            'not_found'          => __('No galleries found.', 'robo-gallery'),
            'not_found_in_trash' => __('No galleries found in Trash.', 'robo-gallery'),
            'menu_name'          => _x('Robo Gallery', 'admin menu', 'robo-gallery'),
            'name_admin_bar'     => _x('Robo Gallery', 'add new on admin bar', 'robo-gallery'),
        );
    }
}
