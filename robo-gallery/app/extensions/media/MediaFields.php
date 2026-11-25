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

namespace RoboGallery\app\extensions\media;

use RoboGallery\app\extensions\validation\CssColor;

defined('WPINC') || exit;

/**
 * Robo Gallery fields of an image in the media modal's attachment details:
 * tags, column span, link, link in a new tab, video link and hover effect
 * (rsg_gallery_* meta, read by includes/frontend/modules/class/source/type/).
 * They are hidden everywhere except the gallery editor, where the image manager
 * shows them again (imageManager/ImageManager::showImageFields()).
 * REST writes the same meta via restapi/fields/AttachmentFields.
 */
class MediaFields
{
    // order of the rows in the attachment details
    const FIELDS = array('line', 'col', 'type_link', 'video_link', 'effect', 'tags', 'link');

    // of them, what only the classic grids read (base-grid/grid/grid.v1.php): edited only there
    const CLASSIC_FIELDS = array('col', 'effect');

    const COLUMNS_MAX = 6;

    /*
     * The formatted title (rsg_gallery_title_html) and description (the description itself, HTML in
     * WordPress), edited in the image manager (react_parts/options details/formattedText.js FONT_SIZES):
     * their font sizes, relative to the size the gallery gives the text, and their longest length.
     */
    const TEXT_FONT_SIZES      = array('0.8em', '1.25em', '1.5em');
    const TITLE_HTML_MAX       = 4000;
    const DESCRIPTION_HTML_MAX = 20000;

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
                . '{z-index:1000;opacity:0.4;pointer-events:none;}'
                // the badge of the locked fields
                . '.robo-gallery-pro-label{display:inline-block;margin-left:4px;padding:0 6px;border-radius:3px;background:#dba617;color:#fff;font-size:11px;line-height:18px;vertical-align:middle;}';
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
                : '<h4>' . esc_html__('Robo Gallery', 'robo-gallery') . ' <span class="robo-gallery-pro-label">PRO</span></h4>',
        );

        $form_fields[self::key('tags')] = array(
            'label' => __('Tags'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'textarea',
            'value' => sanitize_textarea_field(self::meta($id, 'tags')),
        );

        $columns = array(0 => __('Auto', 'robo-gallery'));
        for ($i = 1; $i <= self::COLUMNS_MAX; $i++) {
            $columns[$i] = $i;
        }
        $col = (int) self::meta($id, 'col');
        $form_fields[self::key('col')] = array(
            'label' => __('Column'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
            'input' => 'html',
            'value' => $col,
            'html'  => self::select($id, 'col', $columns, $col),
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
        if (null !== $col && ('0' === $col || self::sanitizeColumn($col))) {
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
     * Hover effect of the image: one of effects(), '' (the gallery's own) otherwise.
     *
     * @param mixed $value
     */
    public static function sanitizeEffect($value): string
    {
        $effect = is_scalar($value) ? (string) $value : '';

        return array_key_exists($effect, self::effects()) ? $effect : '';
    }

    /**
     * Formatted title: the title's text with bold, italic, underline and spans of a font size
     * (TEXT_FONT_SIZES) or a color (CssColor) only. The Fusion Grid puts it into the page as
     * HTML, so nothing else gets through; '' for anything that is not such a string.
     *
     * @param mixed $value
     */
    public static function sanitizeTitleHtml($value): string
    {
        return self::sanitizeTextHtml($value, self::TITLE_HTML_MAX, false);
    }

    /**
     * The description as the Fusion Grid shows it formatted: what it may show of the description's
     * HTML - the title's formatting, also as <b> / <i>, and the lines (new lines and <br>); the other
     * tags go, their text stays. '' for a description without tags: the grid shows its text.
     */
    public static function descriptionHtml(string $description): string
    {
        if (!preg_match('/<[a-z\/]/i', $description)) {
            return '';
        }

        return self::sanitizeTextHtml(preg_replace('/\r?\n/', '<br>', $description), self::DESCRIPTION_HTML_MAX, true);
    }

    /**
     * The formatted title of an image when it still is its title: the title edited elsewhere
     * (the media library) makes it stale, and the gallery then shows the plain title.
     */
    public static function titleHtmlFor(int $id, string $title): string
    {
        $html = self::sanitizeTitleHtml(get_post_meta($id, 'rsg_gallery_title_html', true));

        return '' !== $html && self::htmlText($html) === self::plainText($title) ? $html : '';
    }

    /**
     * @param mixed $value
     * @param bool  $description also <br> and the <b> / <i> of HTML written elsewhere
     */
    private static function sanitizeTextHtml($value, int $max, bool $description): string
    {
        if (!is_string($value) || '' === trim($value) || strlen($value) > $max) {
            return '';
        }

        $allowed = array(
            'strong' => array(),
            'em'     => array(),
            'u'      => array(),
            'span'   => array('style' => true),
        );
        if ($description) {
            $allowed['br'] = array();
            $allowed['b']  = array();
            $allowed['i']  = array();
        }

        $html = wp_kses($value, $allowed);

        // kses keeps any style it knows; a span may hold only the size and the color
        $html = preg_replace_callback('/<span\b[^>]*>/i', function ($match) {
            return self::textSpan($match[0]);
        }, $html);

        return force_balance_tags(trim((string) $html));
    }

    /* the text of the formatted title */
    private static function htmlText(string $html): string
    {
        return self::plainText(wp_strip_all_tags($html));
    }

    /* the text as the visitor reads it: entities decoded, spaces collapsed */
    private static function plainText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /* a span of the formatted text: its style with only an allowed font size and a valid color */
    private static function textSpan(string $tag): string
    {
        $rules = array();

        if (preg_match('/\sstyle\s*=\s*"([^"]*)"/i', $tag, $style)) {
            foreach (explode(';', html_entity_decode($style[1], ENT_QUOTES, 'UTF-8')) as $declaration) {
                $parts = explode(':', $declaration, 2);
                if (2 !== count($parts)) {
                    continue;
                }
                $name  = strtolower(trim($parts[0]));
                $value = strtolower(trim($parts[1]));

                if ('font-size' === $name && in_array($value, self::TEXT_FONT_SIZES, true)) {
                    $rules['font-size'] = $value;
                } elseif ('color' === $name && CssColor::isValid($value)) {
                    $rules['color'] = $value;
                }
            }
        }

        if (!$rules) {
            return '<span>';
        }

        $css = array();
        foreach ($rules as $name => $value) {
            $css[] = $name . ': ' . $value;
        }

        return '<span style="' . esc_attr(implode('; ', $css)) . '">';
    }

    /**
     * The effect choices for the image manager (imageManager): value => label.
     */
    public static function effectChoices(): array
    {
        return self::effects();
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
        // the modal posts all its fields on any change: without its own item a stored value outside
        // the choices would become the first choice. saveFields() leaves such a value as it is.
        if (!array_key_exists($current, $choices)) {
            $choices[$current] = $current;
        }

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
