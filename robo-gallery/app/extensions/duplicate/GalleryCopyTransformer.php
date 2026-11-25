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

namespace RoboGallery\app\extensions\duplicate;

use RoboGallery\app\extensions\access\ExcludeListManager;
use rbDuplicatePost\Contexts\TransformerContext;
use rbDuplicatePost\Contracts\PostAfterCopyTransformer;
use rbDuplicatePost\Copiers\PostMetaCopier;

defined('WPINC') || exit;

/**
 * The step of Duplicate Post RB that completes the copy of a gallery. Created only
 * by its filter (GalleryDuplicate), so the interface is there when this is loaded.
 */
class GalleryCopyTransformer implements PostAfterCopyTransformer
{
    /**
     * A gallery is its meta (images, type, settings), and the plugin's "all post meta"
     * option is off by default: a copy always gets it. Meta is what
     * GalleryDuplicate::filterExcludedMetaKeys() leaves.
     *
     * @param TransformerContext $context
     */
    public function transform(TransformerContext $context)
    {
        if (!self::supports($context)) {
            return;
        }

        $options = $context->get_options();
        if (empty($options['copyAllPostMeta']['value'])) {
            PostMetaCopier::copy_post_all_meta($context->to_operation_context());
        }

        $sourceId = (int) $context->get_source()->get_post_id();
        $newId    = (int) $context->get_target()->get_post_id();

        do_action('robo_gallery_clone_gallery', $newId, get_post($sourceId));

        // Meta is copied with add_post_meta(), which skips the update_post_metadata
        // hook that normally puts direct-link-only galleries on the exclude list,
        // so such a copy would otherwise show up in listings/search/sitemap.
        (new ExcludeListManager())->syncGallery($newId);

        update_post_meta($newId, GalleryDuplicate::ORIGINAL_META_KEY, $sourceId);
    }

    /**
     * Galleries of this site: on another site of a network the images in a gallery's
     * meta name other attachments.
     *
     * @param TransformerContext $context
     * @return bool
     */
    public static function supports(TransformerContext $context): bool
    {
        return $context->get_source()->get_blog_id() === $context->get_target()->get_blog_id()
            && ROBO_GALLERY_TYPE_POST === $context->get_source()->get_post_type();
    }
}
