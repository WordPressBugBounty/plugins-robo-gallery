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

defined('WPINC') || exit;

/**
 * A CSS color taken from gallery settings or attachment meta, checked before
 * it is written into CSS/SCSS text or a style attribute. esc_attr() is not
 * enough there: it keeps ";", "{" and "}", so a "color" could close the
 * declaration and add rules of its own.
 *
 * Accepted (the whole value, nothing around it):
 * - #rgb, #rgba, #rrggbb, #rrggbbaa
 * - rgb()/rgba()/hsl()/hsla() with 3 or 4 numeric components, comma or space
 *   syntax, "/ alpha", percentages, hue units (deg, rad, grad, turn)
 * - a keyword: color names, transparent, currentColor, inherit...
 * Everything else (url(), var(), expressions, several values) is rejected.
 */
final class CssColor
{
    const MAX_LENGTH = 64;

    const PATTERN = '/^(?:'
        . '#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})'
        . '|(?:rgba?|hsla?)\(\s*(?:[-+]?(?:\d+\.?\d*|\.\d+)(?:%|deg|grad|rad|turn)?\s*[,\/]?\s*){3,4}\)'
        . '|[a-z]+'
        . ')$/i';

    public static function isValid($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $value = trim($value);

        return '' !== $value && strlen($value) <= self::MAX_LENGTH && 1 === preg_match(self::PATTERN, $value);
    }

    /**
     * @param mixed  $value
     * @param string $fallback returned for an invalid value (itself not checked)
     * @return string the trimmed color, or $fallback
     */
    public static function sanitize($value, string $fallback = ''): string
    {
        return self::isValid($value) ? trim($value) : $fallback;
    }
}
