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

namespace RoboGallery\app\extensions\restapi\models;

use RoboGallery\app\GalleryUtils;

defined('WPINC') || exit;

/**
 * The album hierarchy of the galleries of one type, as the removed "Album
 * Hierarchy" box arranged it: the published galleries of
 * the edited gallery's type (and the edited gallery itself, whatever its
 * status), nested by post_parent and ordered by menu_order. Albums list their
 * children in this order (access\AlbumHierarchy).
 *
 * Saving takes the whole tree as the client arranged it:
 *  - it must hold exactly the galleries of the loaded tree, each once, at most
 *    maxDepth() levels deep (a gallery already deeper may stay, only not go
 *    deeper); the revision of the loaded tree must still be the current one
 *    (another tab, a new gallery meanwhile);
 *  - menu_order is numbered through the whole tree, as the old box did;
 *  - only what changed is written. A gallery shown at the top because its
 *    parent is not in the tree (a draft, another type, a gallery hidden from
 *    this user's listings) keeps that parent while it stays at the top;
 *  - a gallery is moved only by a user who may edit it and the new parent
 *    (its current parent stays allowed); the others are left as they are and
 *    reported, as the old box left them.
 */
class AlbumTree
{
    // the old box allowed the same (Pro shows up to 20 nested levels)
    const MAX_DEPTH = 25;

    // without the Pro key: the levels its albums show (GalleryModel::get_gallery_hierarchical_children())
    const FREE_MAX_DEPTH = 2;

    private int $galleryId;

    private string $type;

    /** @var \WP_Post[]|null by id, in the order of the tree */
    private ?array $posts = null;

    /** @var int[]|null by id: the parent in the tree, 0 at the top */
    private ?array $shown = null;

    public function __construct(int $galleryId)
    {
        $this->galleryId = $galleryId;
        $this->type      = GalleryUtils::getTypeGallery($galleryId);
    }

    /**
     * @return array { revision, type, maxDepth, tree: nodes { id, title, status, slug, editUrl, canEdit, current, children } }
     */
    public function describe(): array
    {
        $children = array();
        foreach ($this->posts() as $id => $post) {
            $children[$this->shownParents()[$id]][] = $post;
        }

        return array(
            'revision' => $this->revision(),
            'type'     => $this->type,
            'maxDepth' => $this->maxDepth(),
            'tree'     => $this->nodes(0, $children),
        );
    }

    public function revision(): string
    {
        $state = array();
        foreach ($this->posts() as $post) {
            $state[] = array((int) $post->ID, (int) $post->post_parent, (int) $post->menu_order);
        }
        usort($state, function ($a, $b) {
            return $a[0] - $b[0];
        });

        return md5(wp_json_encode($state));
    }

    /**
     * Whether $tree is a valid arrangement of the loaded galleries.
     *
     * @param mixed $tree nodes { id, children }
     * @return bool
     */
    public function isValid($tree): bool
    {
        $seen = array();
        if (!$this->collect($tree, 0, $seen)) {
            return false;
        }

        return count($seen) === count($this->posts());
    }

    /**
     * Applies a tree that isValid() accepted.
     *
     * @param array $tree
     * @return int[] galleries left as they were: the user may not move them there, or saving failed
     */
    public function save(array $tree): array
    {
        $order   = 0;
        $skipped = array();
        $this->apply($tree, 0, $order, $skipped);

        $this->posts = null;
        $this->shown = null;

        return $skipped;
    }

    /**
     * @return \WP_Post[] by id
     */
    private function posts(): array
    {
        if (null !== $this->posts) {
            return $this->posts;
        }

        $posts = get_posts(array(
            'post_type'        => ROBO_GALLERY_TYPE_POST,
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'orderby'          => array('menu_order' => 'ASC', 'ID' => 'ASC'),
            'meta_query'       => array(
                array(
                    'key'   => GalleryUtils::TYPE_FIELD,
                    'value' => $this->type,
                ),
            ),
        ));

        $this->posts = array();
        foreach ($posts as $post) {
            $this->posts[(int) $post->ID] = $post;
        }

        // a draft can be placed too: it shows up in its album once published
        if (!isset($this->posts[$this->galleryId])) {
            $current = get_post($this->galleryId);
            if ($current instanceof \WP_Post && ROBO_GALLERY_TYPE_POST === $current->post_type) {
                $this->posts[$this->galleryId] = $current;
            }
        }

        return $this->posts;
    }

    /**
     * The parent of each gallery in the tree: the stored one, or the top when that
     * one is not in the tree. A loop of parents (not made by WordPress, which
     * refuses them, but possible in old data) is put at the top too, so every
     * gallery of the tree is shown once.
     *
     * @return int[] by id
     */
    private function shownParents(): array
    {
        if (null !== $this->shown) {
            return $this->shown;
        }

        $posts = $this->posts();
        $shown = array();
        foreach ($posts as $id => $post) {
            $parent     = (int) $post->post_parent;
            $shown[$id] = $parent && $parent !== $id && isset($posts[$parent]) ? $parent : 0;
        }

        foreach (array_keys($shown) as $id) {
            $path = array();
            for ($at = $id; $shown[$at] && !isset($path[$at]); $at = $shown[$at]) {
                $path[$at] = true;
            }
            if ($shown[$at]) {
                $shown[$at] = 0;
            }
        }

        return $this->shown = $shown;
    }

    private function nodes(int $parentId, array $children): array
    {
        if (empty($children[$parentId])) {
            return array();
        }

        $nodes = array();
        foreach ($children[$parentId] as $post) {
            $id      = (int) $post->ID;
            $canEdit = current_user_can('edit_post', $id);
            $nodes[] = array(
                'id'       => $id,
                'title'    => $post->post_title,
                'status'   => $post->post_status,
                // the address of a gallery the user does not manage is not theirs to see (direct link only)
                'slug'     => $canEdit ? $post->post_name : '',
                'editUrl'  => $canEdit ? (string) get_edit_post_link($id, 'raw') : '',
                'canEdit'  => $canEdit,
                'current'  => $id === $this->galleryId,
                'children' => $this->nodes($id, $children),
            );
        }

        return $nodes;
    }

    /* the levels below the top a gallery may be moved to */
    private function maxDepth(): int
    {
        return ROBO_GALLERY_TYR ? self::MAX_DEPTH : self::FREE_MAX_DEPTH;
    }

    /* a gallery already deeper (saved with the Pro key, or before the limit) may stay there, not go deeper */
    private function fitsDepth(int $id, int $depth): bool
    {
        return $depth <= $this->maxDepth() || $depth <= $this->shownDepth($id);
    }

    private function shownDepth(int $id): int
    {
        $shown = $this->shownParents();
        $depth = 0;
        for ($at = $shown[$id]; $at; $at = $shown[$at]) {
            $depth++;
        }

        return $depth;
    }

    private function collect($nodes, int $depth, array &$seen): bool
    {
        if (!is_array($nodes)) {
            return false;
        }

        $posts = $this->posts();
        foreach ($nodes as $node) {
            if (!is_array($node) || !isset($node['id']) || !is_numeric($node['id'])) {
                return false;
            }
            $id = (int) $node['id'];
            if (!isset($posts[$id]) || isset($seen[$id]) || !$this->fitsDepth($id, $depth)) {
                return false;
            }
            $seen[$id] = true;

            if (isset($node['children']) && !$this->collect($node['children'], $depth + 1, $seen)) {
                return false;
            }
        }

        return true;
    }

    /* parents come before their children, so a child is never put under its own descendant */
    private function apply(array $nodes, int $parentId, int &$order, array &$skipped): void
    {
        $posts = $this->posts();
        foreach ($nodes as $node) {
            $order++;
            $id   = (int) $node['id'];
            $post = $posts[$id];

            $moved  = $parentId !== $this->shownParents()[$id];
            $parent = $moved ? $parentId : (int) $post->post_parent;

            if ($parent !== (int) $post->post_parent || $order !== (int) $post->menu_order) {
                if (!$this->canMoveTo($post, $parent)) {
                    $skipped[] = $id;
                } else {
                    $result = wp_update_post(array(
                        'ID'          => $id,
                        'post_parent' => $parent,
                        'menu_order'  => $order,
                    ), true);
                    if (is_wp_error($result) || !$result) {
                        $skipped[] = $id;
                    }
                }
            }

            if (!empty($node['children'])) {
                $this->apply($node['children'], $id, $order, $skipped);
            }
        }
    }

    /* a gallery in an album changes what the album lists, so the album must be editable too */
    private function canMoveTo(\WP_Post $post, int $parentId): bool
    {
        if (!current_user_can('edit_post', $post->ID)) {
            return false;
        }

        return 0 === $parentId || (int) $post->post_parent === $parentId || current_user_can('edit_post', $parentId);
    }
}
