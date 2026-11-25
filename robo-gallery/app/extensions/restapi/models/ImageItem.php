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

defined('WPINC') || exit;

/**
 * One gallery image as the image manager (react_parts/options/src/imageManager) sees it: the
 * attachment's texts, sizes, preview and the rsg_gallery_* meta, or
 * { id, missing: true } when the attachment was deleted from the library.
 */
class ImageItem
{
    // the per-image meta of media/MediaFields (rsg_gallery_<name>), sent as meta.<name>
    const META = array('tags', 'link', 'type_link', 'video_link', 'col', 'effect');

    /**
     * @param int[] $ids
     * @return array[] in the order of $ids
     */
    public static function fromIds(array $ids): array
    {
        if ($ids) {
            // one query for the posts and one for their meta instead of two per image
            _prime_post_caches($ids, false, true);
        }

        return array_map(array(__CLASS__, 'fromId'), $ids);
    }

    public static function fromId(int $id): array
    {
        $post = $id > 0 ? get_post($id) : null;
        if (!$post || 'attachment' !== $post->post_type) {
            return array('id' => $id, 'missing' => true);
        }

        $mime    = (string) get_post_mime_type($post);
        $preview = wp_get_attachment_image_src($id, 'medium');
        $data    = wp_get_attachment_metadata($id);
        $file    = get_attached_file($id);

        $meta = array();
        foreach (self::META as $name) {
            $meta[$name] = get_post_meta($id, ROBO_GALLERY_PREFIX . 'gallery_' . $name, true);
        }
        $meta['type_link'] = (int) $meta['type_link'];
        $meta['col']       = (int) $meta['col'];

        return array(
            'id'          => $id,
            'missing'     => false,
            'mime'        => $mime,
            'isImage'     => 0 === strpos($mime, 'image/'),
            'title'       => $post->post_title,
            'caption'     => $post->post_excerpt,
            'description' => $post->post_content,
            'alt'         => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            // the name only: the server path stays on the server
            'filename'    => $file ? wp_basename($file) : '',
            'date'        => (string) get_post_time('c', true, $post),
            'width'       => is_array($data) && isset($data['width']) ? (int) $data['width'] : 0,
            'height'      => is_array($data) && isset($data['height']) ? (int) $data['height'] : 0,
            'preview'     => $preview ? $preview[0] : '',
            'url'         => (string) wp_get_attachment_url($id),
            'canEdit'     => current_user_can('edit_post', $id),
            'meta'        => $meta,
        );
    }
}
