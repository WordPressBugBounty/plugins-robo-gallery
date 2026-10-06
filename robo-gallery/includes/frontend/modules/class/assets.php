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

class  roboGalleryModuleAssets{

	protected $id = null;
	protected $options_id = null;
	
	protected $jsFiles = array();
	protected $cssFiles = array();

	protected $files = array(
		'js' 	=> array(),
		'css' => array(),
	);

	protected $altVersion = false;

	protected $typeInclude = null; //  api, forced, 

	protected $modulePath 	= null;
	protected $moduleUrl 	= null;	

	public $core = null;
    protected $gallery = null;


	public function __construct( $core ){
        $this->core = $core;
        $this->gallery = $core->gallery;
        
        $this->id = $this->gallery->id;
        $this->options_id = $this->gallery->options_id;  

        $classInfo 			= new ReflectionClass($this);
		$this->modulePath 	= plugin_dir_path($classInfo->getFileName());
		$this->moduleUrl 	= plugin_dir_url($classInfo->getFileName());

       	$this->initAssets();
	}

	public function initAssets(){
		$this->initTypeInclude();						
		$this->initFiles();			
		//$this->core->doEvent('gallery.assets.init', $this);

		//	add_action( 'get_footer', array($this, 'addCssFiles') );
		//	add_action( 'get_footer', array($this, 'addJsFiles') );
		$this->addJsFiles();
		$this->addCssFiles();			
	}


	public function initTypeInclude(){

		$this->typeInclude = 'api';
		$jqueryVersion = get_option( ROBO_GALLERY_PREFIX.'jqueryVersion', 'robo' );

		if( $jqueryVersion !='build' ){
			$this->altVersion = true;
			if( $jqueryVersion =='forced' ){
				$this->typeInclude = 'forced';
			}

		}

 		if ( !empty($_GET['action']) && $_GET['action'] == 'elementor' ) { // fix for elementor editor 
			$this->typeInclude = 'forced';
		}
				
		if( 
			is_array($this->gallery->attr) && 
			isset($this->gallery->attr['assetsIncludeForced']) && 
			$this->gallery->attr['assetsIncludeForced'] 
		){ 
			$this->typeInclude = 'forced';	
		}

		//$this->core->doEvent('gallery.assets.init.type', $this->typeInclude);
	}

	// each module's assets class (base-grid, slider, simple, robogrid) lists its own files
	protected function initJsFilesListAlt(){
	}


	protected function initJsFilesList(){
	}

	protected function initCssFilesList(){
	}

	protected function initFiles(){
		if($this->altVersion) $this->initJsFilesListAlt();
		if(!$this->altVersion)  $this->initJsFilesList();
		$this->initCssFilesList();
		//$this->core->doEvent('gallery.assets.init.files', $this->files);
	}

	public function addCssFiles(){
		$this->initCustomAssets('css');
		$this->addCssFilesApi();
		$this->addCssFilesForced();
	}

	public function addJsFiles(){
		$this->initCustomAssets('js');
		$this->addJsFilesApi();
		$this->addJsFilesForced();
	}

	protected function checkFileParams($fileParams){
		if( !is_array($fileParams) ) return  false;
		if( !isset($fileParams['url']) ) return  false;
		if( !isset($fileParams['depend']) || !is_array($fileParams['depend']) ) return  false;
		return  true;
	}

	public function addCssFilesApi(){
		if($this->typeInclude!='api') return ;
		foreach ($this->files['css'] as $fileLabel => $fileParams){
			if( !$this->checkFileParams($fileParams) ) continue ;
			wp_enqueue_style( $fileLabel, $fileParams['url'], $fileParams['depend'], ROBO_GALLERY_VERSION );			
		}
	}

	public function addCssFilesForced(){
		if($this->typeInclude!='forced' ) return ;
		$scriptTags = '';
		foreach ($this->files['css'] as $fileLabel => $fileParams){
			if( !$this->checkFileParams($fileParams) ) continue ;
			// "Forced include" (Settings -> Compatibility) is for themes that never call
			// wp_head()/wp_footer(): enqueued files would not be printed there at all
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
			$scriptTags .= '<link id="'.esc_attr($fileLabel).'" rel="stylesheet" type="text/css" href="'.esc_url($fileParams['url']).'">';
		}
		$this->core->setContent( $scriptTags, 'End' );
	}



	public function addJsFilesApi(){
		if($this->typeInclude!='api') return ;

		foreach ($this->files['js'] as $fileLabel => $fileParams){
			if( !$this->checkFileParams($fileParams) ) continue ;			
			
			wp_enqueue_script( $fileLabel, $fileParams['url'], $fileParams['depend'], ROBO_GALLERY_VERSION, true);
		}
	}

	public function addJsFilesForced(){
		if($this->typeInclude!='forced' ) return ;
		$scriptTags = '';
		foreach ($this->files['js'] as $fileLabel => $fileParams){
			if( !$this->checkFileParams($fileParams) ) continue ;
			// "Forced include": see addCssFilesForced()
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			$scriptTags .= ' <script type="text/javascript" src="'.esc_url($fileParams['url']).'"></script>';
		}
		$this->core->setContent( $scriptTags, 'End' );
	}


 	function initCustomAssets( $type = 'css' ) {

 		// The same check as on save (Settings -> Custom JS\CSS): values saved before it
 		// existed may hold empty lines - site_url('') loaded the home page as CSS / JS.
 		$customOptionFiles = get_option( ROBO_GALLERY_PREFIX.$type.'Files', '' );
 		$customOptionFiles = ( new \RoboGallery\app\extensions\settings\SettingsPage() )->sanitizeFileList( is_string( $customOptionFiles ) ? $customOptionFiles : '' );
 		$customOptionFiles = '' === $customOptionFiles ? array() : explode( "\n", $customOptionFiles );

 		$customFiles = array();
 		foreach ( $customOptionFiles as $i => $path ){
 			$customFiles['robo-gallery-'.$type.'-custom-file'.$i] = array(
 				'url' => site_url( $path ),
 				'depend' => array()
 			);
 		}

 		if( !is_array($customFiles) || !count($customFiles) ) return ;

 		$this->files[$type] = array_merge($this->files[$type], $customFiles); 		
 	}

}
