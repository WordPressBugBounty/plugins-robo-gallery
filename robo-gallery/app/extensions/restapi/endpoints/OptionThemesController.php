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

namespace RoboGallery\app\extensions\restapi\endpoints;

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\extensions\optionThemes\GalleryTheme;
use RoboGallery\app\extensions\optionThemes\OptionThemes;

defined('WPINC') || exit;

/**
 * Option themes in the gallery settings (extensions/optionThemes). Every answer
 * is the state for the settings of that gallery: { current, canManage,
 * themes: [{ id, name, description, builtin, galleries }] }.
 *  - GET /{gallery_id}/option-theme;
 *  - PUT /{gallery_id}/option-theme { theme }: the gallery follows the theme,
 *    '' stops it (the gallery keeps the look as its own settings);
 *  - POST /{gallery_id}/option-themes { name }: a new theme of the site from what
 *    the gallery shows, followed by the gallery from now on;
 *  - PUT /{gallery_id}/option-themes/{theme_id} { name } renames a theme of the
 *    site, { fromGallery: true } gives it what the gallery shows (and the gallery
 *    follows it with no changes of its own);
 *  - DELETE /{gallery_id}/option-themes/{theme_id}: the galleries that follow it
 *    keep their look as their own settings.
 * The gallery: who may edit it, a Fusion Grid one (OptionThemes::SETS). It sees
 * and uses only the themes of its content type (OptionThemes::forSet()); a theme
 * it saves is for that type. The themes of the site (they reach other users'
 * galleries): also OptionThemes::canManage(); built-in themes do not change.
 */
class OptionThemesController extends BaseController
{
    public function register_routes()
    {
        $galleryArgs = array('gallery_id' => $this->galleryIdArg());

        register_rest_route($this->namespace, '/(?P<gallery_id>[0-9]+)/option-theme', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_state'),
                'permission_callback' => array($this, 'can_edit_gallery'),
            ),
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'set_current'),
                'permission_callback' => array($this, 'can_edit_gallery'),
                'args'                => array(
                    'theme' => array('type' => 'string', 'required' => true),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/(?P<gallery_id>[0-9]+)/option-themes', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'create_theme'),
                'permission_callback' => array($this, 'can_manage_themes'),
                'args'                => array(
                    'name' => array('type' => 'string', 'required' => true),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/(?P<gallery_id>[0-9]+)/option-themes/(?P<theme_id>[a-z0-9-]+)', array(
            'args' => $galleryArgs,
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update_theme'),
                'permission_callback' => array($this, 'can_manage_themes'),
                'args'                => array(
                    'name'        => array('type' => 'string'),
                    'fromGallery' => array('type' => 'boolean', 'default' => false),
                ),
            ),
            array(
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_theme'),
                'permission_callback' => array($this, 'can_manage_themes'),
            ),
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|true
     */
    public function can_edit_gallery($request)
    {
        $check = $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this resource.', 'robo-gallery'));
        if (true !== $check) {
            return $check;
        }

        // the themes hold Fusion Grid options: the slider (its own option set) and the classic types have none
        if (!OptionThemes::hasThemes(GalleryOptions::setOf((int) $request['gallery_id']))) {
            return new \WP_Error(
                'robogallery_rest_option_themes_type',
                __('This gallery type has no option themes.', 'robo-gallery'),
                array('status' => 400)
            );
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_Error|true
     */
    public function can_manage_themes($request)
    {
        $check = $this->can_edit_gallery($request);
        if (true !== $check) {
            return $check;
        }
        if (!OptionThemes::canManage()) {
            return new \WP_Error(
                'robogallery_rest_cannot_manage_themes',
                __('Sorry, you cannot manage the option themes.', 'robo-gallery'),
                array('status' => rest_authorization_required_code())
            );
        }

        return true;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_state($request)
    {
        return rest_ensure_response($this->state((int) $request['gallery_id']));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function set_current($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $themeId   = (string) $request['theme'];

        if ('' === $themeId) {
            if ('' !== GalleryTheme::themeId($galleryId)) {
                GalleryTheme::unlink($galleryId);
            }
            return rest_ensure_response($this->state($galleryId));
        }

        if (!OptionThemes::getForSet($themeId, GalleryOptions::setOf($galleryId))) {
            return OptionThemes::notFound();
        }
        GalleryTheme::link($galleryId, $themeId);

        return rest_ensure_response($this->state($galleryId));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_theme($request)
    {
        $galleryId = (int) $request['gallery_id'];

        $theme = OptionThemes::create($request['name'], GalleryOptions::getEffective($galleryId), GalleryOptions::setOf($galleryId));
        if (is_wp_error($theme)) {
            return $theme;
        }
        GalleryTheme::link($galleryId, $theme['id']);

        $response = rest_ensure_response($this->state($galleryId));
        $response->set_status(201);

        return $response;
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_theme($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $themeId   = (string) $request['theme_id'];

        $theme = OptionThemes::getForSet($themeId, GalleryOptions::setOf($galleryId));
        if (!$theme) {
            return OptionThemes::notFound();
        }
        if ($theme['builtin']) {
            return $this->builtinError();
        }

        $changes = array();
        if (null !== $request['name']) {
            $changes['name'] = $request['name'];
        }
        if ($request['fromGallery']) {
            $changes['values'] = GalleryOptions::getEffective($galleryId);
        }

        $result = OptionThemes::update($themeId, $changes);
        if (is_wp_error($result)) {
            return $result;
        }

        // its changes are the theme's now
        if ($request['fromGallery'] && GalleryTheme::themeId($galleryId) === $themeId) {
            GalleryTheme::clearOverrides($galleryId);
        }

        return rest_ensure_response($this->state($galleryId));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_theme($request)
    {
        $galleryId = (int) $request['gallery_id'];
        $themeId   = (string) $request['theme_id'];

        $theme = OptionThemes::getForSet($themeId, GalleryOptions::setOf($galleryId));
        if (!$theme) {
            return OptionThemes::notFound();
        }
        if ($theme['builtin']) {
            return $this->builtinError();
        }

        // while the theme is still there: each keeps what it shows
        foreach (GalleryTheme::galleriesUsing($themeId) as $id) {
            GalleryTheme::unlink($id);
        }
        OptionThemes::delete($themeId);

        return rest_ensure_response($this->state($galleryId));
    }

    private function builtinError(): \WP_Error
    {
        return new \WP_Error(
            'robogallery_option_theme_builtin',
            __('A built-in theme cannot be changed. Save the settings as a new theme instead.', 'robo-gallery'),
            array('status' => 400)
        );
    }

    private function state(int $galleryId): array
    {
        $counts = GalleryTheme::galleryCounts();
        $themes = array();
        foreach (OptionThemes::forSet(GalleryOptions::setOf($galleryId)) as $id => $theme) {
            $themes[] = array(
                'id'          => $id,
                'name'        => $theme['name'],
                'description' => $theme['description'],
                'builtin'     => $theme['builtin'],
                'galleries'   => isset($counts[$id]) ? $counts[$id] : 0,
            );
        }

        return array(
            'current'   => GalleryTheme::theme($galleryId) ? GalleryTheme::themeId($galleryId) : '',
            'canManage' => OptionThemes::canManage(),
            'themes'    => $themes,
        );
    }
}
