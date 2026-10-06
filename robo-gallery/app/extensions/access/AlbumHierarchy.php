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

namespace RoboGallery\app\extensions\access;

defined('WPINC') || exit;

/**
 * The galleries of an album (its child galleries of all levels) that the current
 * visitor may see - the one place that walks the album hierarchy for display:
 * legacy albums (frontend source), the REST album tree and children (robogrid).
 *
 * Loads level by level (one WP_Query per level, so the listing filters apply) and
 * descends only into visible galleries: a hidden album hides its whole subtree,
 * which is then not even loaded. Visible = GalleryAccessManager::canView() and
 * isListable() (direct-link-only galleries are opened by their own link only).
 *
 * The admin album dialog (includes/extensions/category) is not a display path:
 * it lists every gallery of a type to re-parent them.
 */
class AlbumHierarchy
{
    /**
     * Visible child galleries of all levels below the root, by parent.
     *
     * @param int   $rootId
     * @param array $orderby       WP_Query orderby, applied within each level
     * @param int   $maxDepth      levels below the root to load, 0 = all
     * @param bool  $publishedOnly only 'publish' (else the WP_Query default statuses,
     *                             e.g. private galleries for those who may read them -
     *                             canView() decides)
     * @return array<int, \WP_Post[]> parent ID => its visible children, in order
     */
    public static function visibleChildrenByParent(int $rootId, array $orderby, int $maxDepth = 0, bool $publishedOnly = false): array
    {
        $byParent = array();
        if ($rootId <= 0) {
            return $byParent;
        }

        $access  = new GalleryAccessManager();
        $seen    = array($rootId => true);
        $parents = array($rootId);
        $depth   = 0;

        while ($parents && (0 === $maxDepth || $depth < $maxDepth)) {
            $depth++;

            $args = array(
                'post_type'              => ROBO_GALLERY_TYPE_POST,
                'post_parent__in'        => $parents,
                'orderby'                => $orderby,
                'posts_per_page'         => -1,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
            );
            if ($publishedOnly) {
                $args['post_status'] = 'publish';
            }

            $query   = new \WP_Query($args);
            $parents = array();

            foreach ($query->posts as $post) {
                if (isset($seen[$post->ID])) {
                    continue; // a post_parent loop
                }
                $seen[$post->ID] = true;

                if (!self::isVisible($post, $access)) {
                    continue; // and its subtree: never queried as a parent
                }

                $byParent[(int) $post->post_parent][] = $post;
                $parents[] = (int) $post->ID;
            }
        }

        return $byParent;
    }

    /**
     * Legacy albums: every visible descendant, each followed by its own children
     * (menu order, then title, descending - the order albums always had).
     *
     * @param int $rootId
     * @return \WP_Post[]
     */
    public static function flatDescendants(int $rootId): array
    {
        $byParent = self::visibleChildrenByParent($rootId, array('menu_order' => 'DESC', 'title' => 'DESC'));

        $list = array();
        self::appendFlat($rootId, $byParent, $list);
        return $list;
    }

    /**
     * REST album tree (robogrid breadcrumbs / side menu): the root and its visible
     * published descendants down to $maxDepth levels; the nodes of the last level
     * get no children. Empty if the root is not a published gallery - the caller
     * has authorized the root itself (canView(), e.g. by token).
     *
     * @param int $rootId
     * @param int $maxDepth
     * @return array node: id, title, post_parent, children (nodes); empty without a root
     */
    public static function tree(int $rootId, int $maxDepth)
    {
        $root = get_post($rootId);
        if (!($root instanceof \WP_Post) || ROBO_GALLERY_TYPE_POST !== $root->post_type || 'publish' !== $root->post_status) {
            return array();
        }

        $byParent = self::visibleChildrenByParent($rootId, array('menu_order' => 'ASC'), $maxDepth, true);

        return self::treeNode($root, $byParent, $maxDepth, 0);
    }

    /**
     * Direct visible published child galleries, newest first.
     *
     * @param int $rootId
     * @return \WP_Post[]
     */
    public static function directChildren(int $rootId): array
    {
        $byParent = self::visibleChildrenByParent($rootId, array('date' => 'DESC'), 1, true);
        return isset($byParent[$rootId]) ? $byParent[$rootId] : array();
    }

    /**
     * A gallery that may be shown in an album for the current visitor.
     *
     * @param int|\WP_Post             $post
     * @param GalleryAccessManager|null $access
     * @return bool
     */
    public static function isVisible($post, ?GalleryAccessManager $access = null): bool
    {
        $post = get_post($post);
        if (!($post instanceof \WP_Post) || ROBO_GALLERY_TYPE_POST !== $post->post_type) {
            return false;
        }

        $access = $access ?: new GalleryAccessManager();
        return $access->isListable($post->ID) && $access->canView($post->ID);
    }

    private static function appendFlat(int $parentId, array $byParent, array &$list): void
    {
        if (empty($byParent[$parentId])) {
            return;
        }
        foreach ($byParent[$parentId] as $post) {
            $list[] = $post;
            self::appendFlat((int) $post->ID, $byParent, $list);
        }
    }

    private static function treeNode(\WP_Post $post, array $byParent, int $maxDepth, int $level): array
    {
        $node = array(
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'post_parent' => $post->post_parent,
            'children'    => array(),
        );

        if ($level < $maxDepth && !empty($byParent[$post->ID])) {
            foreach ($byParent[$post->ID] as $child) {
                $node['children'][] = self::treeNode($child, $byParent, $maxDepth, $level + 1);
            }
        }

        return $node;
    }
}
