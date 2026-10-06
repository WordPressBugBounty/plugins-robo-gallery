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

if (! defined('WPINC')) {
    die; // Exit if accessed directly
}

use RoboGallery\app\PluginConstants;

/**
 * Share links of "require token" galleries. Both forms carry only the token,
 * the gallery is looked up by it:
 *   - pretty permalinks: /<gallery base>/share/t/<token>/
 *   - any permalinks:    /?rg_token=<token>
 * A gallery's own URL with ?rg_token= appended works too (the token is then
 * only checked, not used for the lookup). Editors get the share link as the
 * permalink of such galleries.
 */
class ShareLinks
{
    private GalleryToken $token;
    private GalleryAccessManager $access;

    public function __construct(GalleryToken $token, GalleryAccessManager $access)
    {
        $this->token  = $token;
        $this->access = $access;
    }

    public function register(): void
    {
        // after the gallery post type (init, 10): its rewrite slug is the share base
        add_action('init', [$this, 'registerRewriteRule'], 20);

        // token-only requests (share link, /?rg_token=) -> the gallery they belong to
        add_filter('request', [$this, 'resolveShareRequest']);
        add_filter('redirect_canonical', [$this, 'keepShareUrl']);

        add_filter('post_type_link', [$this, 'filterPermalink'], 10, 2);
    }

    /**
     * Changing this rule? Bump activation\Install::REWRITE_VERSION so the
     * stored rules get flushed on sites that update.
     */
    public function registerRewriteRule(): void
    {
        // also makes rg_token a public query var, so plain ?rg_token= works
        add_rewrite_tag('%' . PluginConstants::QUERY_VAR_TOKEN . '%', '([A-Za-z0-9]+)');

        add_rewrite_rule(
            '^' . self::getGalleryBase() . '/' . PluginConstants::SHARE_PATH . '/([A-Za-z0-9]{16,64})/?$',
            'index.php?' . PluginConstants::QUERY_VAR_TOKEN . '=$matches[1]',
            'top'
        );
    }

    /**
     * The gallery post type's rewrite slug ("gallery").
     *
     * @return string
     */
    public static function getGalleryBase(): string
    {
        $postTypeObject = get_post_type_object(ROBO_GALLERY_TYPE_POST);
        $slug = $postTypeObject && is_array($postTypeObject->rewrite) && !empty($postTypeObject->rewrite['slug'])
            ? $postTypeObject->rewrite['slug']
            : 'gallery';

        return trim($slug, '/');
    }

    /**
     * The shareable link for a token, in the form the site's permalinks support.
     *
     * @param string $token
     * @return string
     */
    public static function getShareUrl(string $token): string
    {
        if (get_option('permalink_structure')) {
            return home_url(user_trailingslashit(self::getGalleryBase() . '/' . PluginConstants::SHARE_PATH . '/' . rawurlencode($token)));
        }

        return add_query_arg(PluginConstants::QUERY_VAR_TOKEN, rawurlencode($token), home_url('/'));
    }

    /**
     * "request" filter: a token-only request (share rule or /?rg_token=) is
     * turned into the gallery it belongs to; an unknown token is a 404.
     * GalleryAccessManager then verifies the token against the gallery as usual.
     *
     * @param array $query_vars
     * @return array
     */
    public function resolveShareRequest($query_vars)
    {
        if (!is_array($query_vars) || empty($query_vars[PluginConstants::QUERY_VAR_TOKEN])) {
            return $query_vars;
        }

        // Only a token-only request is a share link. Anything else (a gallery or
        // post URL with ?rg_token= appended, a REST request carrying rg_token
        // via rest_route, ...) is left alone.
        $others = $query_vars;
        unset($others[PluginConstants::QUERY_VAR_TOKEN]);
        if (!empty($others)) {
            return $query_vars;
        }

        $token      = sanitize_text_field((string) $query_vars[PluginConstants::QUERY_VAR_TOKEN]);
        $gallery_id = $this->token->findGallery($token);

        if (!$gallery_id) {
            return array('error' => '404');
        }

        return array(
            'post_type'                      => ROBO_GALLERY_TYPE_POST,
            'p'                              => $gallery_id,
            PluginConstants::QUERY_VAR_TOKEN => $token,
        );
    }

    /**
     * Keep the share URL as it is: canonical redirect would send the resolved
     * gallery to its normal permalink and drop the token.
     *
     * @param string|false $redirect_url
     * @return string|false
     */
    public function keepShareUrl($redirect_url)
    {
        return get_query_var(PluginConstants::QUERY_VAR_TOKEN) ? false : $redirect_url;
    }

    /**
     * post_type_link: users who may edit a token gallery see its share link
     * as the permalink (admin "View" links, REST link field); everyone else
     * gets the normal permalink.
     *
     * @param string   $permalink
     * @param \WP_Post $post
     * @return string
     */
    public function filterPermalink($permalink, $post): string
    {
        if (get_post_type($post) !== ROBO_GALLERY_TYPE_POST) {
            return $permalink;
        }

        $id = (int) $post->ID;
        if ($id <= 0 || ! current_user_can('edit_post', $id)) {
            return $permalink;
        }

        if (! $this->access->isDirectLinkOnly($id) || ! $this->access->requiresToken($id)) {
            return $permalink;
        }

        $token = $this->token->get($id);
        return '' === $token ? $permalink : self::getShareUrl($token);
    }
}
