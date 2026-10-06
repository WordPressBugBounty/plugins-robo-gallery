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
 * A CSS size unit from gallery settings, checked before it is written into CSS.
 */
class CssUnits
{
    const ALLOWED = array('em', 'rem', '%', 'px', 'vw');

    /**
     * @param mixed $val
     * @return string an allowed unit, '%' for anything else
     */
    public static function getCorrectSizeUnits($val)
    {
        $correctVal = is_string($val) ? strtolower(trim($val)) : '';

        return in_array($correctVal, self::ALLOWED, true) ? $correctVal : '%';
    }
}
