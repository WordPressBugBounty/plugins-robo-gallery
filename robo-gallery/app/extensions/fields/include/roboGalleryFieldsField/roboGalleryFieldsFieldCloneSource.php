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

use RoboGallery\app\extensions\cloneSource\CloneSource;

/**
 * The "Clone Gallery" source (config/metabox/clone_source.php): a select of
 * the galleries CloneSource offers. A cb_sanitize gets only the value, the
 * check needs the gallery too, so the field does it itself.
 */
class roboGalleryFieldsFieldCloneSource extends roboGalleryFieldsField{

	// the choices (a query) only when the field is shown, not on save
	public function getData($value = null){
		$data = parent::getData($value);

		$data['options']['values'] = array( '0' => __('none') ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core string, translated by WordPress
			+ CloneSource::choices( (int) $this->postId, (int) $data['value'] );

		return $data;
	}

	// none, the stored source or a gallery the field offers
	protected function normalize($value){
		return CloneSource::sanitize( $value, (int) $this->postId );
	}
}
