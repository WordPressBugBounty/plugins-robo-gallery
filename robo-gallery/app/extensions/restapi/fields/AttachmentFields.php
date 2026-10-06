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

namespace RoboGallery\app\extensions\restapi\fields;

use RoboGallery\app\extensions\media\MediaFields;

defined('WPINC') || exit;

/**
 * Attachment data in /wp/v2/media: the rsg_* meta edited per image and the
 * robofields texts the galleries show for an image.
 */
class AttachmentFields
{
    public function register()
    {
        $this->registerMeta();
        $this->registerRoboFields();
    }

    /**
     * Tags, link, video link, link type, column span - writable by whoever may
     * edit the attachment. Same value rules as the media modal (MediaFields):
     * the links are real URLs, never javascript: and the like.
     */
    private function registerMeta()
    {
        $meta = array(
            'rsg_gallery_tags'       => array('string', 'Tags', 'sanitize_text_field'),
            'rsg_gallery_link'       => array('string', 'Link', array(MediaFields::class, 'sanitizeUrl')),
            'rsg_gallery_video_link' => array('string', 'Video Link', array(MediaFields::class, 'sanitizeUrl')),
            'rsg_gallery_type_link'  => array('integer', 'Type Link', array(MediaFields::class, 'sanitizeTypeLink')),
            'rsg_gallery_col'        => array('integer', 'Column', array(MediaFields::class, 'sanitizeColumn')),
        );

        foreach ($meta as $meta_key => $args) {
            list($type, $description, $sanitize) = $args;

            register_post_meta('attachment', $meta_key, array(
                'type'              => $type,
                'description'       => $description,
                'single'            => true,
                'show_in_rest'      => true,
                'auth_callback'     => function ($allowed, $meta_key, $object_id) {
                    return current_user_can('edit_post', $object_id);
                },
                'sanitize_callback' => function ($meta_value) use ($sanitize) {
                    return call_user_func($sanitize, $meta_value);
                },
            ));
        }
    }

    private function registerRoboFields()
    {
        register_rest_field('attachment', 'robofields', array(
            'get_callback' => function ($attach) {

                $response = array(
                    'title'       => null,
                    'description' => null,
                    'alt'         => null,
                    'caption'     => null,
                );

                if (!isset($attach['id']) || !$attach['id']) {
                    return $response;
                }

                $attachment_id = (int) $attach['id'];

                if (isset($attach['title']) && isset($attach['title']['raw'])) {
                    $response['title'] = $attach['title']['raw'];
                } else {
                    $response['title'] = get_post_field('post_title', $attachment_id, 'raw');
                }

                // the attachment's own description (get_the_content() was used here,
                // which takes no post ID and read the global post instead)
                $response['description'] = get_post_field('post_content', $attachment_id, 'raw');
                $response['alt']         = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                $response['caption']     = wp_get_attachment_caption($attachment_id);

                return $response;
            },
        ));
    }
}
