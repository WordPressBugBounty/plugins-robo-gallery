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

namespace RoboGallery\app\extensions\validation;

use RoboGallery\app\PluginConstants;

if (!defined('WPINC')) {
    exit;
}

/**
 * Marks the plugin's meta keys as protected.
 *
 * Core's edit_post() always runs add_meta() (the "Custom Fields" handler), even
 * for post types without custom-fields support, so anyone who can edit a gallery
 * or an attachment could otherwise write any non-underscore meta raw, bypassing
 * the plugin's own sanitizing (CMB2 / fields framework) and reaching unescaped
 * output. Protected keys are skipped by that handler, the add-meta AJAX action
 * and XML-RPC custom fields.
 *
 * Direct get/add/update/delete_post_meta() calls ignore this flag, so the
 * plugin's own saving is not affected. Keys registered with an auth_callback
 * (attachment meta in restapi/fields/AttachmentFields.php) keep working in REST.
 */
class ProtectedMeta
{
    public static function init()
    {
        add_filter('is_protected_meta', array(__CLASS__, 'isProtected'), 10, 3);
    }

    public static function isProtected($protected, $meta_key, $meta_type)
    {
        if ($protected || 'post' !== $meta_type || !is_string($meta_key)) {
            return $protected;
        }

        if (0 === strpos($meta_key, ROBO_GALLERY_PREFIX)) {
            return true;
        }

        return in_array($meta_key, array(PluginConstants::OPTIONS_KEY, 'gallery_views_count'), true);
    }
}
