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

defined('WPINC') || exit;

// editor metaboxes only: saving runs on post.php, Quick Edit on admin-ajax (both admin)
if (!is_admin()) {
	return;
}

define('ROBO_GALLERY_FIELDS_PATH', 			dirname(__FILE__) . '/');

define('ROBO_GALLERY_FIELDS_PATH_CONFIG', 	ROBO_GALLERY_FIELDS_PATH . 'config/');
define('ROBO_GALLERY_FIELDS_SUB_FIELDS', 	ROBO_GALLERY_FIELDS_PATH_CONFIG . 'metabox/sub-fields/');

define('ROBO_GALLERY_FIELDS_PATH_FIELD', 	ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsField/');

define('ROBO_GALLERY_FIELDS_TEMPLATE', 		ROBO_GALLERY_FIELDS_PATH . 'template/');

define('ROBO_GALLERY_FIELDS_URL', 			plugin_dir_url(__FILE__));

define('ROBO_GALLERY_FIELDS_BODY_CLASS', 	'roboGalleryFields');

require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFields.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsHelper.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsConfig.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsConfig/roboGalleryFieldsConfigReaderInterface.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsConfig/roboGalleryFieldsConfigReader.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsMetaBoxClass.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsFieldFactory.php';
require_once ROBO_GALLERY_FIELDS_PATH . 'include/roboGalleryFieldsView.php';

roboGalleryFields::getInstance()->init();