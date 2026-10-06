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

class  roboGalleryModuleJsOptions{
	private $id 		= null;
	private $options_id = null;

	protected $options 	= array();	

	protected $core = null;
    protected $gallery = null;

	public function __construct( $core ){
	        $this->core 	= $core;
	        $this->gallery 	= $core->gallery;

	        $this->id 		= $this->gallery->id;
	        $this->options_id 	= $this->gallery->options_id;

	       	$this->initJsOptions();
	}


	private function initJsOptions(){
		$this->setValue( 'version', ROBO_GALLERY_VERSION);
		$this->setValue( 'id', $this->id);
		$this->setValue( 'class', 'id'.$this->id);
		$this->setValue( 'roboGalleryDelay', 1000 );
		$this->setValue( 'mainContainer', '#robo_gallery_main_block_'.$this->gallery->galleryId );
	}


	static function setNestedArrayValue(&$array, $path, &$value, $delimiter = '/') {
	    $pathParts = explode($delimiter, $path);
	    $current = &$array;
	    foreach($pathParts as $key){
	    	if( !is_array($current) ) $current = array();
	        $current = &$current[$key];
	    }
	    $backup = $current;
	    $current = $value;
	    return $backup;
	}


	public function setValue( $valName, $value ){
		if( strpos($valName, '/')!==false ){
			self::setNestedArrayValue( $this->options, $valName, $value);
			return ;
		}

		if( isset($this->options[$valName]) ){
			if( is_array($this->options[$valName]) ){
				if( is_array($value) ) $this->options[$valName] = $this->options[$valName] + $value;
					else $this->options[$valName][] = $value;
			}
			return ;
		}
		$this->options[$valName] = $value;
	}

	


	public function setOption( $valName ){
		$value = $this->core->getMeta($valName);
		if($value===null){
			return ;		
		}
		$this->setValue($valName , $value);
	}




	/**
	 * Printed inside an inline <script>: JSON_HEX_TAG / JSON_HEX_AMP keep a label
	 * like "<!--<script" (settings text) from changing how the HTML parser reads
	 * the script block. JSON_NUMERIC_CHECK stays: the scripts expect numbers for
	 * the numeric settings stored as strings.
	 */
	public function getOptionList(){
		$json = json_encode( $this->options, JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_AMP );
		return false === $json ? '{}' : $json;
	}

}