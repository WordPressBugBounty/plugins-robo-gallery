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

if ( ! defined( 'WPINC' ) ) exit;

if ( ! function_exists( 'rbs_gallery_include' ) ) {
	/**
	 * Loads legacy plugin files (require_once); files that don't exist are skipped.
	 * Used across includes/ and by add-ons - keep the name and signature.
	 *
	 * @param string|string[] $files file name(s) relative to $path
	 * @param string          $path  directory with a trailing slash
	 */
	function rbs_gallery_include( $files, $path = '' ) {
		if ( empty( $files ) ) {
			return;
		}

		foreach ( (array) $files as $file ) {
			if ( file_exists( $path . $file ) ) {
				require_once $path . $file;
			}
		}
	}
}
