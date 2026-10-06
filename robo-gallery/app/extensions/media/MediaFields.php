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

namespace RoboGallery\app\extensions\media;

defined('WPINC') || exit;

/**
 * Robo Gallery fields of an image in the media modal's attachment details:
 * tags, column span, link, link in a new tab, video link and hover effect
 * (rsg_gallery_* meta, read by includes/frontend/modules/class/source/type/).
 * They are hidden everywhere except the gallery editor's Images field, which
 * shows them again (app/extensions/fields/asset/fields/gallery/style.css).
 * REST writes the same meta via restapi/fields/AttachmentFields.
 */
class MediaFields
{
    // order of the rows in the attachment details
    const FIELDS = array('line', 'col', 'type_link', 'video_link', 'effect', 'tags', 'link');

    const COLUMNS_MAX = 6;

    public function register(): void
    {
        add_action('admin_head', array($this, 'printStyles'));
        add_filter('attachment_fields_to_edit', array($this, 'addFields'), 10, 2);
        add_filter('attachment_fields_to_save', array($this, 'saveFields'), 10, 2);
    }

    public function printStyles(): void
    {
        $rows = array();
        foreach (self::FIELDS as $name) {
            $rows[] = '.compat-attachment-fields tr.compat-field-' . self::key($name);
        }
        $css = implode(',', $rows) . '{display:none;}';

        // the links are a Pro feature: shown dimmed and not editable in the free version
        if (!ROBO_GALLERY_TYR) {
            $css .= '.compat-attachment-fields tr.compat-field-' . self::key('type_link') . ','
                . '.compat-attachment-fields tr.compat-field-' . self::key('link')
                . '{z-index:1000;opacity:0.4;pointer-events:none;}';
        }

        // selectors built from the plugin's own field names (no quotes or tags)
        echo '<style>' . esc_html($css) . '</style>';
    }

    /**
     * @param array    $form_fields
     * @param \WP_Post $post
     * @return array
     */
    public function addFields($form_fields, $post)
    {
        $id = (int) $post->ID;

        $form_fields[self::key('line')] = array(
            'label' => '',
            'input' => 'html',
            'html'  => ROBO_GALLERY_TYR
                ? '<h4>' . esc_html__('Robo Gallery', 'robo-gallery') . '</h4>'
                : '<br/><br/><span>Robo Gallery <br> Available in PRO </span>',
        );

        $form_fields[self::key('tags')] = array(
            'label' => __('Tags'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'textarea',
            'value' => sanitize_textarea_field(self::meta($id, 'tags')),
        );

        $columns = array();
        for ($i = 1; $i <= self::COLUMNS_MAX; $i++) {
            $columns[$i] = $i;
        }
        $col = (int) self::meta($id, 'col');
        $form_fields[self::key('col')] = array(
            'label' => __('Column'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'html',
            'value' => $col,
            'html'  => self::select($id, 'col', $columns, $col ? $col : 1),
        );

        $form_fields[self::key('link')] = array(
            'label' => __('Link'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'text',
            'value' => sanitize_url(self::meta($id, 'link')),
        );

        $typeLink = (int) self::meta($id, 'type_link');
        $form_fields[self::key('type_link')] = array(
            'label' => __('Blank Link'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'html',
            'value' => $typeLink,
            'html'  => self::select($id, 'type_link', array(1 => __('On'), 0 => __('Off')), $typeLink ? 1 : 0), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
        );

        $form_fields[self::key('video_link')] = array(
            'label' => __('Video'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'text',
            'value' => sanitize_url(self::meta($id, 'video_link')),
        );

        $effect = sanitize_text_field(self::meta($id, 'effect'));
        $form_fields[self::key('effect')] = array(
            'label' => __('Effect'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'html',
            'value' => $effect,
            'html'  => self::select($id, 'effect', self::effects(), $effect),
        );

        return $form_fields;
    }

    /**
     * Only the submitted fields are saved; a value outside the choices of its
     * select leaves the stored one untouched.
     *
     * @param array $post
     * @param array $attachment
     * @return array
     */
    public function saveFields($post, $attachment)
    {
        $id = (int) $post['ID'];

        $tags = self::posted($attachment, 'tags');
        if (null !== $tags) {
            update_post_meta($id, self::key('tags'), sanitize_text_field($tags));
        }

        foreach (array('video_link', 'link') as $name) {
            $url = self::posted($attachment, $name);
            if (null !== $url) {
                update_post_meta($id, self::key($name), self::sanitizeUrl($url));
            }
        }

        $typeLink = self::posted($attachment, 'type_link');
        if (null !== $typeLink) {
            update_post_meta($id, self::key('type_link'), self::sanitizeTypeLink($typeLink));
        }

        $col = self::posted($attachment, 'col');
        if (null !== $col && self::sanitizeColumn($col)) {
            update_post_meta($id, self::key('col'), self::sanitizeColumn($col));
        }

        $effect = self::posted($attachment, 'effect');
        if (null !== $effect && array_key_exists($effect, self::effects())) {
            update_post_meta($id, self::key('effect'), $effect);
        }

        return $post;
    }

    /*
     * Value rules of the meta, shared with REST (restapi/fields/AttachmentFields),
     * so both ways of editing an image store the same thing.
     */

    /**
     * Link / video link: an http(s) (or other allowed protocol) URL, never
     * javascript: and the like. Raw - esc_url() would store "&" as "&#038;";
     * the frontend escapes on output.
     *
     * @param mixed $value
     */
    public static function sanitizeUrl($value): string
    {
        return is_scalar($value) ? esc_url_raw((string) $value) : '';
    }

    /**
     * Open the link in a new tab: 1 or 0.
     *
     * @param mixed $value
     */
    public static function sanitizeTypeLink($value): int
    {
        return is_scalar($value) && (int) $value ? 1 : 0;
    }

    /**
     * Columns the image spans: 1..COLUMNS_MAX, 0 when out of range (the grid
     * then gives the image no span of its own).
     *
     * @param mixed $value
     */
    public static function sanitizeColumn($value): int
    {
        $col = is_scalar($value) ? (int) $value : 0;

        return $col >= 1 && $col <= self::COLUMNS_MAX ? $col : 0;
    }

    /**
     * @return string|null null when the field was not submitted (or is not a string)
     */
    private static function posted(array $attachment, string $name)
    {
        $key = self::key($name);

        return isset($attachment[$key]) && is_scalar($attachment[$key]) ? (string) $attachment[$key] : null;
    }

    /**
     * Hover effects the gallery script supports ('' = the gallery's own setting).
     *
     * @return array value => label
     */
    private static function effects()
    {
        return array(
            'push-up'              => __('push-up', 'robo-gallery'),
            'push-down'            => __('push-down', 'robo-gallery'),
            'push-up-100%'         => __('push-up-100%', 'robo-gallery'),
            'push-down-100%'       => __('push-down-100%', 'robo-gallery'),
            'reveal-top'           => __('reveal-top', 'robo-gallery'),
            'reveal-bottom'        => __('reveal-bottom', 'robo-gallery'),
            'reveal-top-100%'      => __('reveal-top-100%', 'robo-gallery'),
            'reveal-bottom-100%'   => __('reveal-bottom-100%', 'robo-gallery'),
            'direction-aware'      => __('direction-aware', 'robo-gallery'),
            'direction-aware-fade' => __('direction-aware-fade', 'robo-gallery'),
            'direction-right'      => __('direction-right', 'robo-gallery'),
            'direction-left'       => __('direction-left', 'robo-gallery'),
            'direction-top'        => __('direction-top', 'robo-gallery'),
            'direction-bottom'     => __('direction-bottom', 'robo-gallery'),
            'fade'                 => __('fade', 'robo-gallery'),
            ''                     => __('inherit', 'robo-gallery'),
        );
    }

    /**
     * <select> posted as attachments[<id>][rsg_gallery_<name>] - the name the
     * media modal saves compat fields under.
     */
    private static function select(int $id, string $name, array $choices, $current): string
    {
        $field = 'attachments[' . $id . '][' . self::key($name) . ']';

        $html = '<select name="' . esc_attr($field) . '" id="' . esc_attr($field) . '">';
        foreach ($choices as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '"' . selected((string) $current, (string) $value, false) . '>'
                . esc_html($label) . '</option>';
        }

        return $html . '</select>';
    }

    private static function meta(int $id, string $name)
    {
        return get_post_meta($id, self::key($name), true);
    }

    private static function key(string $name): string
    {
        return ROBO_GALLERY_PREFIX . 'gallery_' . $name;
    }
}
