<?php
/* 
*      Robo Gallery     
*      Version: 5.1.0 - 50521
*      By Robosoft
*
*      Contact: https://robogallery.co/ 
*      Created: 2025
*      Licensed under the GPLv3 license - http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace upz\robogallery_v2;

defined('WPINC') || exit;

/**
 * REST API Gallery model class.
 *
 * @package RoboGallery\RestApi
 */
class ROBOGALLERY_REST_Gallery_Model
{

    /**
     *
     *
     *
     */
    public static function get_gallery_images($gallery_id, $orderby = '', $limit = 0)
    {
        $images_field_name = 'rsg_galleryImages';

        $response = array();

        if (! $gallery_id) {
            return $response;
        }

        $imageIds = get_post_meta($gallery_id, $images_field_name, true);

        if ($orderby) {
            $imageIds = self::orderign_images($imageIds, $orderby);
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
    public static function orderign_images($imageIds, $orderby)
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
     *
     *
     *
     */
    public static function sanitize_images_ids($imageIds)
    {
        if (! is_array($imageIds)) {
            return array();
        }

        $imageIds = array_map(function ($v) {return (int) $v;}, $imageIds);
        $imageIds = array_filter($imageIds, function ($v) {return $v > 0;});
        $imageIds = array_values($imageIds);
        return $imageIds;
    }

    /**
     *
     *
     *
     */
    public static function get_gallery_children($gallery_id, $root_gallery_id)
    {
        $response = array();

        if (! $gallery_id) {
            return $response;
        }

        if (class_exists('upz\\robogallery_key\\app\\restapi\\GalleryFieldsPro')) {
            return \upz\robogallery_key\app\restapi\GalleryFieldsPro::get_gallery_children($gallery_id, $root_gallery_id);
        }

        if ($gallery_id !== $root_gallery_id) {
            return $response;
        }

        $children = get_children($gallery_id);
        if (! count($children)) {
            return $response;
        }

        foreach ($children as $k => $v) {
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
 * return children tree.
 *
 * @param int    $root_gallery_id   gallery ID.
 * @return array                    array
 */
    public static function get_gallery_hierarchical_children($root_gallery_id = 0)
    {

        if (! is_numeric($root_gallery_id) || $root_gallery_id < 0) {
            return [];
        }
        $args = [
            'post_type'      => ROBO_GALLERY_TYPE_POST,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ];

        $all_posts = get_posts($args);

        if (empty($all_posts)) {
            return [];
        }

        $parent_exists = false;
        foreach ($all_posts as $post) {
            if ($post->ID == $root_gallery_id) {
                $parent_exists = true;
                break;
            }
        }

        if (! $parent_exists) {
            return [];
        }

        $posts_by_id = [];
        foreach ($all_posts as $post) {
            $posts_by_id[$post->ID] = [
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'post_parent'    => $post->post_parent,
                'children' => [],
            ];

        }

        foreach ($posts_by_id as $id => &$post) {
            $parent_id = $post['post_parent'];

            if ($parent_id && isset($posts_by_id[$parent_id])) {
                $posts_by_id[$parent_id]['children'][] = &$posts_by_id[$id];
            }
        }

        $m_l = class_exists('upz\\robogallery_key\\app\\restapi\\GalleryFieldsPro') ? 20 : 2;

        function build_tree($node_id, $posts_by_id,  $m_l, $l=0)
        {
            if ($l >  $m_l) {
                return null;
            }

            if (! isset($posts_by_id[$node_id])) {
                return null;
            }

            $node = $posts_by_id[$node_id];

            if ($l < $m_l) {
                foreach ($node['children'] as &$child) {
                    $child_id = $child['id'];
                    $child    =  build_tree($child_id, $posts_by_id,  $m_l, $l + 1);
                }
            } else {
                $node['children'] = [];
            }

            return $node;
        }

        return build_tree($root_gallery_id, $posts_by_id,  $m_l);
    }

}
