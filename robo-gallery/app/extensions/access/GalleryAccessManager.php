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

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\PluginConstants;

/**
 * Who may see a gallery. WordPress decides first (post status, post password);
 * the gallery's own options can only narrow that:
 *  - accessDirectLinkOnly: kept out of listings, opens by its own link;
 *  - accessRequireToken (with direct link only): the link must carry the token.
 *
 * Every place that renders a gallery or its data goes through canView().
 * Stateless: callers may create it freely.
 */
class GalleryAccessManager
{
    private GalleryToken $token;

    public function __construct(?GalleryToken $token = null)
    {
        $this->token = $token ?: new GalleryToken();
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'checkCurrentGallery']);
    }

    /* "Direct link only": hidden from listings, opens only by its own link */
    public function isDirectLinkOnly($post_id): bool
    {
        $options = GalleryOptions::getStored($post_id);
        return !empty($options[PluginConstants::ACCESS_DIRECT_LINK_ONLY]);
    }

    /* "Require token": the link must carry the gallery token (only with direct link only) */
    public function requiresToken($post_id): bool
    {
        $options = GalleryOptions::getStored($post_id);
        return !empty($options[PluginConstants::ACCESS_REQUIRE_TOKEN]);
    }

    /**
     * Users who can edit others' galleries see direct-link-only galleries in
     * listings too (search, REST collections, album menus).
     *
     * @return bool
     */
    public static function canSeeAllInListings(): bool
    {
        $postTypeObject = get_post_type_object(ROBO_GALLERY_TYPE_POST);
        return $postTypeObject && current_user_can($postTypeObject->cap->edit_others_posts);
    }

    /**
     * template_redirect: a token gallery's own page without its token is a 403.
     * Status and the WP post password are left to WordPress here; the gallery
     * output itself is gated by canView() in the shortcode / the_content filter,
     * so images never render without the password even if the theme skips the
     * password form.
     */
    public function checkCurrentGallery(): void
    {
        global $post;
        if (! $post || get_post_type($post) !== ROBO_GALLERY_TYPE_POST || ! is_singular(ROBO_GALLERY_TYPE_POST)) {
            return;
        }

        if (! $this->pluginAllowsView($post->ID)) {
            // same answer whatever the reason (no token / wrong token / none stored)
            wp_die(esc_html__('Access denied.', 'robo-gallery'), 'Error 403', ['response' => 403]);
        }
    }

    /**
     * The token from the current page URL, only if it opens this token
     * gallery; '' otherwise. Used to hand the token to the robogrid script for
     * its REST request. Never returns the stored token itself, so a page opened
     * by an editor via the plain URL (and possibly page-cached) doesn't expose it.
     *
     * @param int $post_id
     * @return string
     */
    public function getValidUrlToken($post_id): string
    {
        if (!$this->isDirectLinkOnly($post_id) || !$this->requiresToken($post_id)) {
            return '';
        }

        $given = $this->getUrlToken();
        return '' !== $given && $this->token->matches($post_id, $given) ? $given : '';
    }

    /**
     * Read-only access decision for a gallery, independent of the page it is
     * rendered on (shortcode or block on another page, REST). Never wp_die()s.
     *
     * @param int         $post_id
     * @param string|null $token   Token to check instead of the one in the page URL
     *                             (REST requests carry it as a request param).
     * @return bool True if the current visitor may view the gallery.
     */
    public function canView($post_id, $token = null): bool
    {
        $post = get_post($post_id);
        if (!$this->isStatusReadable($post)) {
            return false;
        }

        // editors skip the password, as core REST does (can_access_password_content)
        if (post_password_required($post) && !$this->canBypassAccess($post->ID)) {
            return false;
        }

        return $this->pluginAllowsView($post->ID, $token);
    }

    /**
     * True when the only thing keeping the visitor out is the WordPress post
     * password - i.e. showing the password form makes sense. Used where a
     * gallery is rendered outside its own page (shortcode, block).
     *
     * @param int         $post_id
     * @param string|null $token
     * @return bool
     */
    public function needsPasswordForm($post_id, $token = null): bool
    {
        $post = get_post($post_id);
        if (!$this->isStatusReadable($post) || $this->canBypassAccess($post->ID)) {
            return false;
        }

        return post_password_required($post) && $this->pluginAllowsView($post->ID, $token);
    }

    /**
     * Whether the gallery may appear in listings (album children, menus, search,
     * REST collections) for the current visitor. Every direct-link-only gallery
     * (with or without token) stays out of listings; editors still see it.
     *
     * @param int $post_id
     * @return bool
     */
    public function isListable($post_id): bool
    {
        return !$this->isDirectLinkOnly($post_id) || $this->canBypassAccess($post_id);
    }

    /**
     * WordPress side of the decision: an existing gallery that is published, or
     * that the current user may read (drafts, scheduled, WP "Private" status -
     * read_post covers the authors/editors who may see those).
     *
     * @param \WP_Post|null $post
     * @return bool
     */
    private function isStatusReadable($post): bool
    {
        if (!($post instanceof \WP_Post) || ROBO_GALLERY_TYPE_POST !== $post->post_type) {
            return false;
        }

        return 'publish' === $post->post_status || current_user_can('read_post', $post->ID);
    }

    /**
     * Plugin side of the decision (direct link only / token). The post password
     * is checked by canView() itself, so a password gallery passes here.
     *
     * @param int         $post_id
     * @param string|null $token
     * @return bool
     */
    private function pluginAllowsView($post_id, $token = null): bool
    {
        if (!$this->isDirectLinkOnly($post_id) || $this->canBypassAccess($post_id)) {
            return true;
        }

        if ($this->requiresToken($post_id)) {
            $given = null === $token ? $this->getUrlToken() : (string) $token;
            return $this->token->matches($post_id, $given);
        }

        // direct link only, no token: opens by its link, only kept out of listings
        return true;
    }

    private function canBypassAccess($post_id): bool
    {
        return current_user_can('edit_post', $post_id);
    }

    private function getUrlToken(): string
    {
        return sanitize_text_field((string) get_query_var(PluginConstants::QUERY_VAR_TOKEN));
    }
}
