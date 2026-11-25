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

namespace RoboGallery\app\extensions\posts;

use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * The posts of a posts gallery and the image of each post. The only reader and
 * writer of two meta of the gallery:
 *  - rsg_galleryPosts: the hand-picked list (source "manual"), an array of ID
 *    strings in the order shown - the same format as rsg_galleryImages;
 *  - rsg_postsImages: post id => attachment id, the image chosen for a post in
 *    this gallery; used by both sources and kept when a post leaves the list.
 *
 * A post without a chosen image gets the first of: its featured image, the
 * first image of its content (an image or gallery block, [gallery ids]), the
 * first image attached to it.
 *
 * Only published posts without a password of a public post type are shown:
 * the gallery is public, and so are its REST data.
 */
class PostsSource
{
    const POSTS_META  = ROBO_GALLERY_PREFIX . 'galleryPosts';
    const IMAGES_META = ROBO_GALLERY_PREFIX . 'postsImages';

    // where the image of a post came from (the editor shows it)
    const IMAGE_CUSTOM   = 'custom';
    const IMAGE_FEATURED = 'featured';
    const IMAGE_CONTENT  = 'content';
    const IMAGE_ATTACHED = 'attached';

    /**
     * Post types a gallery can show: the public ones, without media and the
     * galleries themselves. Filter robo_gallery_posts_post_types.
     *
     * @return string[]
     */
    public static function postTypes(): array
    {
        $types = array_values(array_diff(
            get_post_types(array('public' => true)),
            array('attachment', ROBO_GALLERY_TYPE_POST)
        ));

        $types = apply_filters('robo_gallery_posts_post_types', $types);

        return is_array($types) ? array_values(array_filter($types, 'post_type_exists')) : array();
    }

    /**
     * Taxonomies whose terms can filter the posts of a type (the post formats are left out).
     *
     * @return \WP_Taxonomy[] name => taxonomy
     */
    public static function taxonomies(string $postType): array
    {
        $result = array();
        foreach (get_object_taxonomies($postType, 'objects') as $name => $taxonomy) {
            if ($taxonomy->public && $taxonomy->show_ui && 'post_format' !== $name) {
                $result[$name] = $taxonomy;
            }
        }
        return $result;
    }

    /**
     * @return int[] the hand-picked posts, in order
     */
    public static function getManual(int $galleryId): array
    {
        return self::idList(get_post_meta($galleryId, self::POSTS_META, true), PostsOptions::MAX_POSTS);
    }

    /**
     * @param int[] $ids
     */
    public static function saveManual(int $galleryId, array $ids): void
    {
        $ids = self::idList($ids, PostsOptions::MAX_POSTS);
        update_post_meta($galleryId, self::POSTS_META, array_map('strval', $ids));
    }

    /**
     * @return int[] post id => attachment id
     */
    public static function getImages(int $galleryId): array
    {
        $stored = get_post_meta($galleryId, self::IMAGES_META, true);
        $images = array();
        if (!is_array($stored)) {
            return $images;
        }
        foreach ($stored as $postId => $imageId) {
            if ((int) $postId > 0 && (int) $imageId > 0) {
                $images[(int) $postId] = (int) $imageId;
            }
        }
        return $images;
    }

    /**
     * Sets or clears (image 0) the image of one post.
     */
    public static function setImage(int $galleryId, int $postId, int $imageId): void
    {
        $images = self::getImages($galleryId);
        if ($imageId > 0) {
            $images[$postId] = $imageId;
        } else {
            unset($images[$postId]);
        }
        update_post_meta($galleryId, self::IMAGES_META, $images);
    }

    /**
     * The values of the posts options of the gallery (stored, else the defaults).
     *
     * @return array option_id => value
     */
    public static function options(int $galleryId): array
    {
        $values = array();
        foreach (GalleryOptions::getWithValues($galleryId, GalleryOptions::SET_POSTS) as $optionId => $option) {
            if ('posts' === $option['group']) {
                $values[$optionId] = $option['value'];
            }
        }
        return $values;
    }

    /**
     * The posts the gallery shows, each with its image.
     *
     * @param int $limit 0 = all
     * @return array[] post (WP_Post), image (attachment id, 0 = none), imageSource (IMAGE_*, '' = none)
     */
    public static function items(int $galleryId, int $limit = 0): array
    {
        $options = self::options($galleryId);
        $posts   = self::query($galleryId, $options);
        if ($limit && count($posts) > $limit) {
            $posts = array_slice($posts, 0, $limit);
        }

        $images = self::resolveImages($posts, self::getImages($galleryId));
        $skip   = isset($options['postsNoImage']) && 'skip' === $options['postsNoImage'];

        $items = array();
        foreach ($posts as $post) {
            $image = isset($images[$post->ID]) ? $images[$post->ID] : array(0, '');
            if (!$image[0] && $skip) {
                continue;
            }
            $items[] = array('post' => $post, 'image' => $image[0], 'imageSource' => $image[1]);
        }
        return $items;
    }

    /**
     * @param array $options values of options()
     * @return \WP_Post[]
     */
    public static function query(int $galleryId, array $options): array
    {
        $args = array(
            'post_status'         => 'publish',
            'has_password'        => false,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        );

        if (isset($options['postsSource']) && 'manual' === $options['postsSource']) {
            $ids = self::getManual($galleryId);
            if (!$ids) {
                return array();
            }
            $args += array(
                'post_type'      => self::postTypes(),
                'post__in'       => $ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count($ids),
            );
        } else {
            $postType = isset($options['postsPostType']) ? (string) $options['postsPostType'] : 'post';
            if (!in_array($postType, self::postTypes(), true)) {
                return array();
            }
            $args += array(
                'post_type'      => $postType,
                'posts_per_page' => max(1, min(PostsOptions::MAX_POSTS, (int) ($options['postsCount'] ?? 12))),
                'orderby'        => isset($options['postsOrderBy']) ? (string) $options['postsOrderBy'] : 'date',
                'order'          => isset($options['postsOrder']) && 'ASC' === $options['postsOrder'] ? 'ASC' : 'DESC',
            );

            $taxQuery = self::taxQuery($postType, isset($options['postsTerms']) && is_array($options['postsTerms']) ? $options['postsTerms'] : array());
            if ($taxQuery) {
                $args['tax_query'] = $taxQuery;
            }
        }

        $query = new \WP_Query($args);
        return array_values(array_filter($query->posts, function ($post) {
            return $post instanceof \WP_Post;
        }));
    }

    /**
     * Posts with any of the terms. Terms of taxonomies the type does not have
     * (the type was changed after they were chosen) are ignored.
     *
     * @param int[] $termIds
     * @return array the tax_query, empty for no filter
     */
    private static function taxQuery(string $postType, array $termIds): array
    {
        $taxonomies = self::taxonomies($postType);
        $byTaxonomy = array();
        foreach (self::idList($termIds, PostsOptions::MAX_TERMS) as $termId) {
            $term = get_term($termId);
            if ($term instanceof \WP_Term && isset($taxonomies[$term->taxonomy])) {
                $byTaxonomy[$term->taxonomy][] = $termId;
            }
        }

        if (!$byTaxonomy) {
            return array();
        }

        $taxQuery = array('relation' => 'OR');
        foreach ($byTaxonomy as $taxonomy => $ids) {
            $taxQuery[] = array(
                'taxonomy'         => $taxonomy,
                'field'            => 'term_id',
                'terms'            => $ids,
                'include_children' => true,
            );
        }
        return $taxQuery;
    }

    /**
     * The image of each post: the chosen one, else found in the post.
     *
     * @param \WP_Post[] $posts
     * @param int[]      $custom post id => attachment id
     * @return array post id => [attachment id, IMAGE_*]
     */
    public static function resolveImages(array $posts, array $custom): array
    {
        $result  = array();
        $missing = array();

        // one query for the chosen and featured attachments and their meta instead of a few per post
        $known = array();
        foreach ($posts as $post) {
            $known[] = isset($custom[$post->ID]) ? $custom[$post->ID] : 0;
            $known[] = (int) get_post_thumbnail_id($post);
        }
        $known = array_filter($known);
        if ($known) {
            _prime_post_caches(array_unique($known), false, true);
        }

        foreach ($posts as $post) {
            if (isset($custom[$post->ID]) && wp_attachment_is_image($custom[$post->ID])) {
                $result[$post->ID] = array($custom[$post->ID], self::IMAGE_CUSTOM);
                continue;
            }

            $thumbId = (int) get_post_thumbnail_id($post);
            if ($thumbId && wp_attachment_is_image($thumbId)) {
                $result[$post->ID] = array($thumbId, self::IMAGE_FEATURED);
                continue;
            }

            $contentId = self::contentImage((string) $post->post_content);
            if ($contentId) {
                $result[$post->ID] = array($contentId, self::IMAGE_CONTENT);
                continue;
            }

            $missing[] = $post->ID;
        }

        foreach (self::attachedImages($missing) as $postId => $imageId) {
            $result[$postId] = array($imageId, self::IMAGE_ATTACHED);
        }

        return $result;
    }

    /**
     * The first image of the content: an image of the media library put in by an
     * editor (class wp-image-<id>, also in gallery blocks) or of a [gallery ids=""].
     */
    private static function contentImage(string $content): int
    {
        if ('' === $content) {
            return 0;
        }

        $candidates = array();
        if (preg_match_all('/\bwp-image-(\d+)\b/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $candidates[$match[1]] = (int) $match[0];
            }
        }
        if (preg_match_all('/\[gallery\b[^\]]*\bids\s*=\s*["\']?\s*([\d,\s]+)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $candidates[$match[1]] = (int) strtok($match[0], ', ');
            }
        }

        // in the order they appear
        ksort($candidates);
        foreach ($candidates as $id) {
            if ($id > 0 && wp_attachment_is_image($id)) {
                return $id;
            }
        }
        return 0;
    }

    /**
     * The first image attached to each post (uploaded to it), in one query.
     *
     * @param int[] $postIds
     * @return int[] post id => attachment id
     */
    private static function attachedImages(array $postIds): array
    {
        if (!$postIds) {
            return array();
        }

        $attachments = get_posts(array(
            'post_type'       => 'attachment',
            'post_status'     => 'inherit',
            'post_mime_type'  => 'image',
            'post_parent__in' => $postIds,
            'orderby'         => array('menu_order' => 'ASC', 'ID' => 'ASC'),
            'numberposts'     => count($postIds) * 20,
            'fields'          => 'id=>parent',
        ));

        $result = array();
        foreach ($attachments as $attachment) {
            $parent = (int) $attachment->post_parent;
            if (!isset($result[$parent])) {
                $result[$parent] = (int) $attachment->ID;
            }
        }
        return $result;
    }

    /**
     * Positive unique ids, at most $max.
     *
     * @param mixed $value an array of ids (strings or numbers)
     * @return int[]
     */
    public static function idList($value, int $max): array
    {
        if (!is_array($value)) {
            return array();
        }

        $ids = array();
        foreach ($value as $id) {
            $id = is_scalar($id) ? (int) $id : 0;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_slice(array_values($ids), 0, $max);
    }
}
