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

use RoboGallery\app\GalleryOptions;
use RoboGallery\app\extensions\restapi\models\GalleryModel;
use RoboGallery\app\extensions\access\GalleryAccessManager;

defined('WPINC') || exit;

/**
 * robofields on galleries (/wp/v2/robogallery/{id}): everything the robogrid
 * frontend needs to render a gallery - images, settings, album children/tree.
 */
class GalleryFields
{
    /**
     * Settings the robogrid frontend receives (key order = response order).
     * Everything else in GalleryOptions stays admin-only (options controller).
     */
    const PUBLIC_OPTIONS = array(
        'layout', 'columns', 'spacing', 'shadow', 'layoutAdjustment', 'targetRowHeight',
        'albumIcon', 'albumIconColor', 'albumHideCoverImage',
        'hoverEffect', 'titleSource', 'descriptionSource', 'hoverColor', 'hoverInvert', 'hoverHighlight',
        'hoverTitleColor', 'hoverTitleBackgroundColor', 'hoverDescriptionColor', 'hoverDescriptionBackgroundColor', 'hoverBackgroundColor',
        'loadingColor', 'loadingSize',
        'breadcrumbs', 'topMenuMode', 'sideMenu', 'rootGalleryInMenu', 'rootGalleryLabel', 'navigationInterfaceColor', 'infiniteScroll',
        'lightboxButtons', 'lightboxTitleSource', 'lightboxDescriptionSource',
        'polaroidMode', 'polaroidTextColor', 'polaroidBackgroundColor', 'polaroidTitleSource', 'polaroidDescriptionSource',
        'labelButtonLoadMore', 'labelButtonUp', 'labelButtonMenu', 'labelSidebarMenuTitle',
        'polaroidDescriptionSize',
    );

    public function register()
    {
        $this->add_gallery_fields();
    }

    private static function getFieldsPrefix()
    {
        return 'robofields';
    }

    public function add_gallery_fields()
    {

        register_rest_field(ROBO_GALLERY_TYPE_POST, self::getFieldsPrefix(), [

            'get_callback'    => function ($object, $field_name, $request) {

                $response = [];

                if (! isset($object['id']) || ! $object['id']) {
                    return $response;
                }

                $id = (int) $object['id'];

                // private/token/password-protected galleries must not leak their
                // images or settings through the REST API to visitors who
                // couldn't view them on the gallery's own page.
                // A REST request has no gallery URL, so the robogrid script passes
                // the visitor's token (from its config, see robogrid/layout.php) as rg_token.
                $rgToken       = $request->get_param('rg_token');
                $rgToken       = is_string($rgToken) ? sanitize_text_field($rgToken) : null;
                $accessManager = new GalleryAccessManager();
                if (! $accessManager->canView($id, $rgToken)) {
                    return $response;
                }

                // check root_gallery param
                $root_gallery_id = (int) $request->get_param('root_gallery');
                if (! $root_gallery_id) {
                    return $response;
                }
                
                if( $object['id'] == $root_gallery_id) {
                    $response['hierarchical_children'] = GalleryModel::get_gallery_hierarchical_children( $root_gallery_id);
                } else {
                    $response['hierarchical_children'] = array();
                } 
                
                $response['children'] = GalleryModel::get_gallery_children($id, $root_gallery_id);
                    

                $options      = GalleryOptions::getStored($id);
                $optionConfig = GalleryOptions::getOptionConfig();

                $orderby = isset($options['orderby']) ? sanitize_text_field($options['orderby']) : 'order';

                $response['title']    = get_post_field('post_title', $id, 'raw');

                $response['images']   = GalleryModel::get_gallery_images($id, $orderby);
                $response['orderby']  = $orderby;

                foreach (self::PUBLIC_OPTIONS as $name) {
                    $response[$name] = self::publicValue($optionConfig[$name], $options, $name);
                }

                /* access */
                $response['accessDirectLinkOnly'] = $accessManager->isDirectLinkOnly($id);
                $response['accessRequireToken']   = $accessManager->requiresToken($id);

                $response['pagination']    = isset($options['pagination']) ? $options['pagination'] : 'disable';
                $response['imagesPerPage'] = isset($options['imagesPerPage']) ? (int) $options['imagesPerPage'] : 10;

                return $response;
            },
            // read-only (no update_callback); the keys are listed in get_callback above
            'schema'          => [
                'description' => 'Robo Gallery data for the robogrid frontend: images, settings, album children and tree.',
                'type'        => 'object',
                'readonly'    => true,
            ],
        ]);
    }

    /**
     * A stored setting as the frontend gets it: cast by its definition
     * (integer / boolean / multiselect array / string), or the default when unset.
     *
     * @param array  $option  definition from GalleryOptions
     * @param array  $options stored settings
     * @param string $name
     * @return mixed
     */
    private static function publicValue(array $option, array $options, $name)
    {
        if (!isset($options[$name])) {
            return $option['default'];
        }

        $value    = $options[$name];
        $sanitize = isset($option['sanitize']) ? $option['sanitize'] : null;

        if ('integer' === $sanitize) {
            return (int) $value;
        }
        if ('boolean' === $sanitize) {
            return (bool) $value;
        }
        if ('color' === $sanitize) {
            return GalleryOptions::sanitizeColor($value, $option);
        }
        if ('multiselect' === $option['type']) {
            return $value;
        }

        return sanitize_text_field($value);
    }

}
