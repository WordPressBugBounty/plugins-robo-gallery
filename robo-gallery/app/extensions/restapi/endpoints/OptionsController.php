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

namespace RoboGallery\app\extensions\restapi\endpoints;

use RoboGallery\app\GalleryOptions;

defined('WPINC') || exit;

/**
 * /robogallery/v1/{gallery_id}/options - read (GET) and save (PUT/POST, body
 * items: [{option_id, value}, ...]) a gallery's settings. Used by the robogrid
 * settings panel in the gallery editor. Storage and sanitizing live in
 * GalleryOptions; this class only maps requests and responses.
 *
 * @package RoboGallery\RestApi
 */
class OptionsController extends BaseController
{

    public function __construct()
    {
        $this->namespace = 'robogallery/v1';
        $this->rest_base = '(?P<gallery_id>[0-9]+)';
    }

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/options',
            array(
                'args'   => array(
                    'gallery_id' => $this->galleryIdArg(),
                ),
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_items'),
                    'permission_callback' => array($this, 'get_items_permissions_check'),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_items'),
                    'permission_callback' => array($this, 'update_items_permissions_check'),
                    'args'                => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
    }

    /**
     * All settings of the gallery (definition + value each).
     *
     * @param  WP_REST_Request $request Request data.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items($request)
    {
        $data = array();

        foreach (GalleryOptions::getWithValues($request['gallery_id']) as $option) {
            $option = $this->prepare_item_for_response($option, $request);
            $option = $this->prepare_response_for_collection($option);
            if ($this->is_setting_type_valid($option['type'])) {
                $data[] = $option;
            }
        }

        return rest_ensure_response($data);
    }

    /**
     * Saves the posted items; unknown option ids are skipped, an invalid value
     * fails the whole request. Responds with all settings, like get_items().
     *
     * @param  WP_REST_Request $request Request data.
     * @return WP_Error|WP_REST_Response
     */
    public function update_items($request)
    {
        if (!isset($request['items']) || !is_array($request['items']) || empty($request['items'])) {
            return new \WP_Error(
                'rest_options_options_empty',
                __('Empty options.', 'robo-gallery'),
                array('status' => 400)
            );
        }

        $definitions = GalleryOptions::getOptionsArray();
        $data        = array();

        foreach ($request['items'] as $item) {
            if (!isset($item['option_id']) || !isset($item['value']) || !array_key_exists($item['option_id'], $definitions)) {
                continue;
            }

            $value = GalleryOptions::sanitizeValue($definitions[$item['option_id']], $item['value']);
            if (is_wp_error($value)) {
                return $value;
            }

            $data[$item['option_id']] = $value;
        }

        GalleryOptions::save($request['gallery_id'], $data);

        return $this->get_items($request);
    }

    /**
     * Prepare a single setting object for response.
     *
     * @param array           $item Setting definition + value.
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response $response Response data.
     */
    public function prepare_item_for_response($item, $request)
    {
        $data     = $this->filter_options($item);
        $data     = $this->add_additional_fields_to_object($data, $request);
        $data     = $this->filter_response_by_context($data, empty($request['context']) ? 'view' : $request['context']);
        $response = rest_ensure_response($data);
        return $response;
    }

    /**
     * Reading the settings: whoever may edit the gallery.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|boolean
     */
    public function get_items_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_view', __('Sorry, you cannot list resources.', 'robo-gallery'));
    }

    /**
     * Saving the settings: whoever may edit the gallery.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|boolean
     */
    public function update_items_permissions_check($request)
    {
        return $this->checkCanEditGallery($request, 'robogallery_rest_cannot_edit', __('Sorry, you cannot edit this resource.', 'robo-gallery'));
    }

    /**
     * Filters out bad values from the options array/filter so we
     * only return known values via the API.
     *
     * @since 3.0.0
     * @param  array $options Settings.
     * @return array
     */
    public function filter_options($options)
    {
        $options = array_intersect_key(
            $options,
            array_flip(array_filter(array_keys($options), array($this, 'allowed_option_ids')))
        );

        return $options;
    }

    /**
     * Callback for allowed keys for each setting response.
     *
     * @param  string $key Key to check.
     * @return boolean
     */
    public function allowed_option_ids($key)
    {
        return in_array(
            $key,
            array(
                //'id',
                'default',
                'type',
                'value',

                'options',

                'group',

                'label',
                'description',
                'tip',
                'placeholder',
                'option_id',
            ),
            true
        );
    }

    /**
     * Setting types returned by the API. Values are sanitized by
     * GalleryOptions::sanitizeValue(): multiselect by its list, every other
     * type by the option's 'sanitize' rule.
     *
     * @param  string $type Type.
     * @return bool
     */
    public function is_setting_type_valid($type)
    {
        return in_array(
            $type,
            array(
                'text',
                'email',
                'number',
                'color',
                'password',
                'textarea',
                'select',
                'multiselect',
                'radio',
                'checkbox',
                'image_width',
                'thumbnail_cropping',
            ),
            true
        );
    }

    /**
     * Get the settings schema, conforming to JSON Schema.
     *
     * @since 3.0.0
     * @return array
     */
    public function get_item_schema()
    {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'setting',
            'type'       => 'object',
            'properties' => array(
                'id'          => array(
                    'description' => __('A unique identifier for the setting.', 'robo-gallery'),
                    'type'        => 'string',
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_title',
                    ),
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'label'       => array(
                    'description' => __('A human readable label for the setting used in interfaces.', 'robo-gallery'),
                    'type'        => 'string',
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'description' => array(
                    'description' => __('A human readable description for the setting used in interfaces.', 'robo-gallery'),
                    'type'        => 'string',
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'value'       => array(
                    'description' => __('Setting value.', 'robo-gallery'),
                    'type'        => 'mixed',
                    'context'     => array('view', 'edit'),
                ),
                'default'     => array(
                    'description' => __('Default value for the setting.', 'robo-gallery'),
                    'type'        => 'mixed',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'tip'         => array(
                    'description' => __('Additional help text shown to the user about the setting.', 'robo-gallery'),
                    'type'        => 'string',
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'placeholder' => array(
                    'description' => __('Placeholder text to be displayed in text inputs.', 'robo-gallery'),
                    'type'        => 'string',
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
                'type'        => array(
                    'description' => __('Type of setting.', 'robo-gallery'),
                    'type'        => 'string',
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'context'     => array('view', 'edit'),
                    'enum'        => array('text', 'email', 'number', 'color', 'password', 'textarea', 'select', 'multiselect', 'radio', 'image_width', 'checkbox', 'thumbnail_cropping'),
                    'readonly'    => true,
                ),
                'options'     => array(
                    'description' => __('Array of options (key value pairs) for inputs such as select, multiselect, and radio buttons.', 'robo-gallery'),
                    'type'        => 'object',
                    'context'     => array('view', 'edit'),
                    'readonly'    => true,
                ),
            ),
        );

        return $this->add_additional_fields_schema($schema);
    }
}
