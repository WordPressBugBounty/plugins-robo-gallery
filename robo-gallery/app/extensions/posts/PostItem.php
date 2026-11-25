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

use RoboGallery\app\extensions\restapi\models\GridItem;

defined('WPINC') || exit;

/**
 * A post of a posts gallery in the forms its readers need: the Fusion Grid
 * script (forGrid(): the shape of a /wp/v2/media item, so the grid draws it as
 * one of its images that links to the post), the settings panel (forEditor())
 * and the static gallery (forStatic()).
 */
class PostItem
{
    // the picture of a post without an image (images/), and its size
    const PLACEHOLDER = 'post_placeholder.svg';
    const PLACEHOLDER_WIDTH  = 1200;
    const PLACEHOLDER_HEIGHT = 800;

    /**
     * The item as the grid reads a /wp/v2/media item (react_parts/grid
     * schemas/image.js, counterUtils.js prepareImagesData()). Its id is the
     * post's: posts and attachments share the ids, so none is also an image's.
     *
     * @param array $item    of PostsSource::items()
     * @param array $options values of PostsSource::options()
     */
    public static function forGrid(array $item, array $options): array
    {
        $post    = $item['post'];
        $title   = self::title($post);
        $excerpt = self::excerpt($post, (int) ($options['postsExcerptLength'] ?? 20));

        $sizes = self::gridSizes((int) $item['image']);

        return array(
            'id'            => (int) $post->ID,
            'robofields'    => array(
                'title'       => $title,
                'description' => $excerpt,
                'caption'     => $excerpt,
                'alt'         => self::alt((int) $item['image'], $title),
            ),
            'media_details' => GridItem::mediaDetails($sizes),
            // the per-image fields of the grid: the link makes the item open the post
            'meta'          => array(
                'rsg_gallery_tags'       => implode(', ', self::termNames($post)),
                'rsg_gallery_link'       => (string) get_permalink($post),
                'rsg_gallery_type_link'  => isset($options['postsLinkTarget']) && 'blank' === $options['postsLinkTarget'] ? 1 : 0,
                'rsg_gallery_video_link' => '',
                'rsg_gallery_col'        => 0,
            ),
        );
    }

    /**
     * A post as the settings panel lists it.
     *
     * @param array $item of PostsSource::items()
     */
    public static function forEditor(array $item): array
    {
        $post  = $item['post'];
        $thumb = $item['image'] ? wp_get_attachment_image_src((int) $item['image'], 'thumbnail') : false;

        return array(
            'id'          => (int) $post->ID,
            'title'       => self::title($post),
            'type'        => $post->post_type,
            'date'        => mysql_to_rfc3339($post->post_date),
            'link'        => (string) get_permalink($post),
            'image'       => (int) $item['image'],
            'imageSource' => (string) $item['imageSource'],
            'thumb'       => $thumb ? (string) $thumb[0] : '',
        );
    }

    /**
     * A post for StaticGallery::render().
     *
     * @param array $item of PostsSource::items()
     */
    public static function forStatic(array $item): array
    {
        return array(
            'galleryType' => 'posts',
            'id'          => (int) $item['image'],
            'link'        => (string) get_permalink($item['post']),
            'title'       => self::title($item['post']),
        );
    }

    /**
     * The title as text (the grid prints it as text: no entities, no tags).
     */
    public static function title(\WP_Post $post): string
    {
        return self::text(get_the_title($post));
    }

    /**
     * The excerpt as text: the post's own, else the start of its content.
     * Not get_the_excerpt(): themes add "read more" links to it.
     */
    private static function excerpt(\WP_Post $post, int $words): string
    {
        if ($words < 1) {
            return '';
        }

        $text = '' !== trim($post->post_excerpt)
            ? $post->post_excerpt
            : excerpt_remove_blocks(strip_shortcodes($post->post_content));

        return self::text(wp_trim_words(wp_strip_all_tags($text), $words, '…'));
    }

    private static function text(string $html): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    private static function alt(int $imageId, string $title): string
    {
        $alt = $imageId ? trim((string) get_post_meta($imageId, '_wp_attachment_image_alt', true)) : '';
        return '' !== $alt ? $alt : $title;
    }

    /**
     * Names of the post's terms in its hierarchical taxonomies (categories):
     * the tags of the grid's menu.
     *
     * @return string[]
     */
    private static function termNames(\WP_Post $post): array
    {
        $names = array();
        foreach (PostsSource::taxonomies($post->post_type) as $name => $taxonomy) {
            if (!$taxonomy->hierarchical) {
                continue;
            }
            $terms = get_the_terms($post, $name);
            if (!is_array($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                // the grid splits the tags by ", "
                $names[] = str_replace(',', ' ', self::text($term->name));
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * @return array size name => file, width, height, source_url (the placeholder without an image)
     */
    private static function gridSizes(int $imageId): array
    {
        $sizes = GridItem::attachmentSizes($imageId);
        if ($sizes) {
            return $sizes;
        }

        $placeholder = GridItem::size(ROBO_GALLERY_URL . 'images/' . self::PLACEHOLDER, self::PLACEHOLDER_WIDTH, self::PLACEHOLDER_HEIGHT);
        return array('medium' => $placeholder, 'large' => $placeholder, 'full' => $placeholder);
    }
}
