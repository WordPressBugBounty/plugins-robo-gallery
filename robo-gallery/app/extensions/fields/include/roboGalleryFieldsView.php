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

class roboGalleryFieldsView{

	public function render($template, array $vars = array()){

		$templatePath = ROBO_GALLERY_FIELDS_TEMPLATE . $template . '.tpl.php';

		if (!file_exists($templatePath)) {
			throw new Exception('Could not find template. Template: ' . esc_html($template));
		}
		extract($vars);
		require $templatePath;
	}

	public function content($template, array $vars = array()){
		ob_start();
		$this->render($template, $vars);

		// ob_get_clean() also closes the buffer (ob_clean() left one open per call)
		return ob_get_clean();
	}
}
