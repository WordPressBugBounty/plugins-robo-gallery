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

defined('WPINC') || exit;

// client proofing is in the key (robogallerykey, proofing/); this plugin keeps only what it asks the key
// (Proofing): loaded here, not autoloaded, so the key finds it with class_exists(..., false)
include_once __DIR__ . '/ClientTokens.php';
include_once __DIR__ . '/Proofing.php';
