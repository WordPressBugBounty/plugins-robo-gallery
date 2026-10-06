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

namespace RoboGallery\app\extensions\restapi\models;

use RoboGallery\app\extensions\access\AlbumHierarchy;

defined('WPINC') || exit;

/**
 * REST API Gallery model class.
 *
 * @package RoboGallery\RestApi
 */
class GalleryModel
{

    /**
     * Image (attachment) IDs of a gallery, from its rsg_galleryImages meta.
     *
     * @param int    $gallery_id
     * @param string $orderby see order_images()
     * @param int    $limit   0 = all
     * @return int[]
     */
    public static function get_gallery_images($gallery_id, $orderby = '', $limit = 0)
    {
        $images_field_name = ROBO_GALLERY_PREFIX . 'galleryImages';

        $response = array();

        if (! $gallery_id) {
            return $response;
        }

        $imageIds = get_post_meta($gallery_id, $images_field_name, true);

        if ($orderby) {
            $imageIds = self::order_images($imageIds, $orderby);
        }

        if (empty($imageIds) || ! is_array($imageIds) || ! count($imageIds)) {
            return $response;
        }
        $response = array_map(function ($item) {return (int) $item;}, $imageIds);

        /* limit imgs */
        if ($limit && $limit < count($response)) {
            $response = array_slice($response, 0, $limit);
        }

        // return array of image ids
        return $response;
    }

    /**
     *   Sorting images ids by param orderby
     *   *imageIds - int[]  image ids
     *   *orderby - string order | orderU | title| titleU | date | dateU | random
     */
    public static function order_images($imageIds, $orderby)
    {

        if (! is_array($imageIds)) {
            return [];
        }

        if (count($imageIds) < 2 || ! $orderby) {
            return $imageIds;
        }

        if ($orderby === 'order') {
            return $imageIds;
        }

        if ($orderby === 'orderU') {
            return array_reverse($imageIds);
        }

        if ($orderby === 'random') {
            shuffle($imageIds);
            return $imageIds;
        }

        $args = array('numberposts' => -1, 'include' => $imageIds, 'post_type' => 'attachment');

        $imgs = get_posts($args);

        if ($orderby === 'title') {
            usort($imgs, function ($item1, $item2) {
                return strcasecmp($item1->post_title, $item2->post_title);
            });
        }

        if ($orderby === 'titleU') {
            usort($imgs, function ($item1, $item2) {
                return strcasecmp($item1->post_title, $item2->post_title) * -1;
            });
        }

        if ($orderby === 'date') {
            usort($imgs, function ($item1, $item2) {
                if ($item1->post_date == $item2->post_date) {
                    return 0;
                }
                if ($item1->post_date > $item2->post_date) {
                    return 1;
                }
                return -1;
            });
        }

        if ($orderby === 'dateU') {
            usort($imgs, function ($item1, $item2) {
                if ($item1->post_date == $item2->post_date) {
                    return 0;
                }
                if ($item1->post_date > $item2->post_date) {
                    return -1;
                }
                return 1;
            });
        }

        return array_map(function ($item) {return $item->ID;}, $imgs);
    }

    /**
     * The album's child galleries the visitor may see, with a cover image and an
     * image count each. Without the key plugin only the root album has children here.
     *
     * @param int $gallery_id
     * @param int $root_gallery_id
     * @return array[]
     */
    public static function get_gallery_children($gallery_id, $root_gallery_id)
    {
        $response = array();

        if (! $gallery_id) {
            return $response;
        }

        if (class_exists('upz\\robogallery_key\\app\\restapi\\GalleryFieldsPro')) {
            $children = \upz\robogallery_key\app\restapi\GalleryFieldsPro::get_gallery_children($gallery_id, $root_gallery_id);
            if (! is_array($children)) {
                return $response;
            }
            // Pro builds the list with get_children() too, so filter its result here
            return array_values(array_filter($children, function ($child) {
                return isset($child['id']) && self::is_child_gallery_visible($child['id']);
            }));
        }

        if ($gallery_id !== $root_gallery_id) {
            return $response;
        }

        foreach (AlbumHierarchy::directChildren((int) $gallery_id) as $v) {
            $imgs = self::get_gallery_images($v->ID);

            $response[] = array(
                'id'             => $v->ID,
                'date'           => $v->post_date,
                'date_gmt'       => $v->post_date_gmt,
                'title'          => $v->post_title,
                'slug'           => $v->post_name,
                'cover'          => count($imgs) ? array($imgs[0]) : array(),
                'elements_count' => count($imgs),
            );
        }
        return $response;
    }

    /**
     * The album tree below the root (breadcrumbs / side menu of robogrid): 2 levels,
     * 20 with the key plugin. Loads only the root's visible descendants
     * (AlbumHierarchy), not every gallery of the site.
     *
     * The caller must have checked canView() for the root: it is returned even
     * when it is a link-only gallery (descendants still follow isListable()).
     *
     * @param int $root_gallery_id
     * @return array node: id, title, post_parent, children
     */
    public static function get_gallery_hierarchical_children($root_gallery_id = 0)
    {
        if (! is_numeric($root_gallery_id) || $root_gallery_id <= 0) {
            return [];
        }

        $maxDepth = class_exists('upz\\robogallery_key\\app\\restapi\\GalleryFieldsPro') ? 20 : 2;

        return AlbumHierarchy::tree((int) $root_gallery_id, $maxDepth);
    }

    /**
     * Filters the key plugin's children list: a published gallery the visitor may
     * see in an album (AlbumHierarchy::isVisible()).
     *
     * @param int|\WP_Post $post
     * @return bool
     */
    private static function is_child_gallery_visible($post)
    {
        $post = get_post($post);
        if (! $post || 'publish' !== $post->post_status) {
            return false;
        }

        return AlbumHierarchy::isVisible($post);
    }

}
