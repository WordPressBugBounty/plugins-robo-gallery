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

namespace RoboGallery\app\extensions\search;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\extensions\restapi\models\GalleryModel;

defined('WPINC') || exit;

/**
 * Search & Filter of the Fusion Grid (the options of the group "search", GalleryOptions): the grid script
 * loads the images of a gallery page by page, so the search and the filters work on this index of all its
 * items, sent with robofields (filterIndex) only when the root gallery has the search or a filter on.
 *
 *  - items:    item id => { t: title and caption, d: description (plain text, cut), g: tags, k: image|video|link };
 *  - children: sub-gallery id => its image ids, in its own order (only with the filter "categories"): the
 *              filter shows them in place of the cards; their items are in items too.
 *
 * Only what the visitor may see already: the gallery passed canView(), its sub-galleries are the visible ones
 * (GalleryModel::get_gallery_children()), the texts are the ones /wp/v2/media gives for each image.
 */
class SearchIndex
{
    const GROUP = 'search';

    const FILTER_TAGS = 'tags';

    const FILTER_CATEGORIES = 'categories';

    const FILTER_TYPE = 'type';

    const KIND_IMAGE = 'image';

    const KIND_VIDEO = 'video';

    const KIND_LINK = 'link';

    // the search needs the start of a long description, not all of it: the index stays small
    const DESCRIPTION_MAX = 300;

    // the option of each filter; its value is one of MODES
    const FILTER_OPTIONS = array(
        self::FILTER_TAGS       => 'filterTags',
        self::FILTER_CATEGORIES => 'filterCategories',
        self::FILTER_TYPE       => 'filterType',
    );

    const MODE_OFF = 'off';

    const MODES = array(self::MODE_OFF, 'buttons', 'dropdown', 'search');

    // Pro: the search field and the filters by tags and by type; the filter by sub-gallery stays free
    const PRO_FILTER_OPTIONS = array('filterTags', 'filterType');

    /**
     * The options as the gallery uses them: without the key (ROBO_GALLERY_TYR) the Pro parts are off, whatever
     * is stored (a theme saved with the key, a value sent past the panel's locked fields).
     */
    public static function withoutLocked(array $options): array
    {
        if (defined('ROBO_GALLERY_TYR') && ROBO_GALLERY_TYR) {
            return $options;
        }
        $options['search'] = false;
        foreach (self::PRO_FILTER_OPTIONS as $name) {
            $options[$name] = self::MODE_OFF;
        }
        return $options;
    }

    /**
     * The filters the options turn on (a mode other than off).
     *
     * @return string[]
     */
    public static function filters(array $options): array
    {
        $filters = array();
        foreach (self::FILTER_OPTIONS as $filter => $name) {
            $mode = isset($options[$name]) ? $options[$name] : self::MODE_OFF;
            if (self::MODE_OFF !== $mode && in_array($mode, self::MODES, true)) {
                $filters[] = $filter;
            }
        }
        return $filters;
    }

    /**
     * The index is needed: the search field or a filter is on (the tags in the search field need the field).
     */
    public static function isOn(array $options): bool
    {
        return !empty($options['search']) || array() !== self::filters($options);
    }

    /**
     * @param int[]        $imageIds the gallery's images, when its items are images
     * @param array[]|null $items    the gallery's posts / videos as /wp/v2/media items (robofields.items)
     * @param array[]      $children the visible sub-galleries (GalleryModel::get_gallery_children())
     * @param array        $options  the root gallery's options (the filters)
     * @return array { items: object, children: object }
     */
    public static function build(array $imageIds, ?array $items, array $children, array $options): array
    {
        $index = array();

        if (null !== $items) {
            foreach ($items as $item) {
                $index[(string) $item['id']] = self::fromItem($item);
            }
        } else {
            $index = self::fromImages($imageIds);
        }

        $childImages = array();
        if (in_array(self::FILTER_CATEGORIES, self::filters($options), true)) {
            foreach ($children as $child) {
                $childId  = (int) $child['id'];
                $orderby  = self::orderOf($childId);
                $ids      = GalleryModel::get_gallery_images($childId, $orderby);
                $missing  = array_values(array_filter($ids, function ($id) use ($index) {
                    return !isset($index[(string) $id]);
                }));
                $index   += self::fromImages($missing);
                $childImages[(string) $childId] = $ids;
            }
        }

        // objects, also when empty: the script reads them by id
        return array(
            'items'    => (object) $index,
            'children' => (object) $childImages,
        );
    }

    /**
     * @param int[] $ids
     * @return array id => entry
     */
    private static function fromImages(array $ids): array
    {
        $index = array();
        if (array() === $ids) {
            return $index;
        }

        // the posts and their meta in two queries, not two per image
        _prime_post_caches($ids, false, true);

        foreach ($ids as $id) {
            $post = get_post($id);
            if (!($post instanceof \WP_Post) || 'attachment' !== $post->post_type) {
                continue;
            }

            $video = (string) get_post_meta($id, 'rsg_gallery_video_link', true);
            $link  = (string) get_post_meta($id, 'rsg_gallery_link', true);

            $index[(string) $id] = self::entry(
                $post->post_title . ' ' . $post->post_excerpt,
                $post->post_content,
                (string) get_post_meta($id, 'rsg_gallery_tags', true),
                '' !== $video ? self::KIND_VIDEO : ('' !== $link ? self::KIND_LINK : self::KIND_IMAGE)
            );
        }

        return $index;
    }

    /**
     * A post / video as robofields.items has it (posts\PostItem, youtube\VideoItem).
     */
    private static function fromItem(array $item): array
    {
        $fields = isset($item['robofields']) ? $item['robofields'] : array();
        $meta   = isset($item['meta']) ? $item['meta'] : array();
        $text   = function ($array, $key) {
            return isset($array[$key]) && is_scalar($array[$key]) ? (string) $array[$key] : '';
        };

        $kind = self::KIND_IMAGE;
        if ('' !== $text($meta, 'rsg_gallery_video_link')) {
            $kind = self::KIND_VIDEO;
        } elseif ('' !== $text($meta, 'rsg_gallery_link')) {
            $kind = self::KIND_LINK;
        }

        // the caption of these items is their description again
        return self::entry($text($fields, 'title'), $text($fields, 'description'), $text($meta, 'rsg_gallery_tags'), $kind);
    }

    private static function entry(string $title, string $description, string $tags, string $kind): array
    {
        $description = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($description)));
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $description = mb_substr($description, 0, self::DESCRIPTION_MAX);
        }

        return array(
            't' => trim(wp_strip_all_tags($title)),
            'd' => $description,
            // stored as "one, two" (the image manager's field); the cards split them the same way
            'g' => array_values(array_filter(array_map('trim', explode(',', $tags)), 'strlen')),
            'k' => $kind,
        );
    }

    /**
     * The order a sub-gallery shows its images in when it is opened (robofields orderby).
     */
    private static function orderOf(int $galleryId): string
    {
        $options = GalleryOptions::getEffective($galleryId);
        return isset($options['orderby']) ? sanitize_text_field($options['orderby']) : 'order';
    }
}
