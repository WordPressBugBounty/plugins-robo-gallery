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

if ( !class_exists( 'RoboGallery_Blocks' ) ) {

	class RoboGallery_Blocks {

		public $prefix = 'block-robo-gallery-';

		function __construct() {
			// No enqueue_block_assets: dist/blocks.style.build.css is empty, and its
			// 'wp-editor' dependency loaded ~280 KB of editor CSS on every front-end page.
			// The gallery's own assets come from the shortcode render.
			add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets') );

			add_action( 'init', array( $this, 'php_block_init' ) );

			add_action( 'wp_ajax_robo_gallery_get_gallery_json', array($this, 'ajaxGetGalleryJson') );

			add_filter( 'block_categories_all', array( $this, 'addCategoryBlocks' ), 10, 2 );
		}

		function editor_assets(){

			wp_enqueue_script(
				$this->prefix.'block-js',
				plugins_url( '/dist/blocks.build.js', dirname( __FILE__ ) ),
				array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor'),
				ROBO_GALLERY_VERSION,
				true // Enqueue the script in the footer.
			);

			wp_localize_script( $this->prefix.'block-js', 'robogallery_block', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wp_rest' ), //wp_create_nonce( 'robogallery-nonce' ),
			));

			wp_enqueue_style(
				$this->prefix.'block-editor-css',
				plugins_url( 'dist/blocks.editor.build.css', dirname( __FILE__ ) ),
				array( 'wp-edit-blocks' ),
				ROBO_GALLERY_VERSION
			);
		}

		function php_block_init(){

			if ( !function_exists( 'register_block_type' ) ) {
				return;
			}

			register_block_type( 'robo/block-robo-gallery', array(
			//	'api_version' => 2,
			    'render_callback' => array( $this, 'renderBlock'),
			    'attributes'	  => array(
					'galleryid'	 => array(
						'type'		=> 'number',
						'default' 	=> 0,
					),
					'assetsincludeforced' => array(
						'type' 		=> 'boolean',
						'default' 	=> false
					),
				),
			) );

		}

		function isCorrectAttributes( $attributes ){

			if( !is_array($attributes) ) return false;
			if( !isset($attributes['galleryid']) ) return false;
			if( !$attributes['galleryid'] ) return false;

			$galleryid = (int)$attributes['galleryid'];
			// a gallery, whatever its type: an empty type is grid (rbsGalleryUtils::getTypeGallery())
			if( get_post_type( $galleryid ) !== ROBO_GALLERY_TYPE_POST ) return false ;
			return true ;
		}

		function getAttributes( $attributes ){
			$attr = array('id'=>$attributes['galleryid']);
			//$attr['assetsIncludeForced'] = true;
			return $attr;
		}

		function renderBlock( $attributes ) {

			if( !$this->isCorrectAttributes( $attributes ) ){
				return sprintf(
					'<div><strong>%s</strong>: %s</div>',
					'Robo Gallery',
					__("You didn't select any Robo Gallery item in editor. Please select one from the list or create new gallery",'robo-gallery')
				) ;
			}

			if( !class_exists('roboGallery') || !function_exists('robo_gallery_shortcode') ) return 'Robo Gallery:: Error 999';

			// same path as the shortcode, so the block gets the same access checks
			// (it used to render any gallery - private, token, draft - by ID)
			return robo_gallery_shortcode( $this->getAttributes( $attributes ) );

		}

		function ajaxGetGalleryJson() {

			$user = wp_get_current_user();
			if ( !user_can($user, 'edit_posts') ) {
				echo '{"code":"rest_no_route","message":"No route was found matching the URL and request method","data":{"status":403}}';
				status_header(403);
				header('HTTP/1.0 403 Forbidden');
    			exit;
			}

			$query = new WP_Query(
				array(
					'post_type' => ROBO_GALLERY_TYPE_POST,
					'post_status' => array( 'publish', 'private', 'future' ),
					'nopaging' =>  true
				)
			);

			$posts = $query->posts;

			$returnJson = array();

			if( is_array($posts) && count($posts)){
				foreach($posts as $post) {

					if( user_can( $user, 'edit_post', $post->ID) ){

						$returnJson[] = array(
							'id' => $post->ID,
							'title' => $post->post_title,
							'parent' => $post->post_parent,
						);
					}
				}
			}
			wp_send_json( $returnJson );
			wp_die();
		}


		function addCategoryBlocks( $block_categories, $editor_context ) {
			if ( ! empty( $editor_context->post ) ) {
				array_push(
					$block_categories,
					array(
						'slug'  => 'robo-category',
						'title' => __( 'Robo Gallery Category', 'robo-gallery' ),
						'icon'  => null,
					)
				);
			}
			return $block_categories;
		}
		 
		
	}
}

new RoboGallery_Blocks();



/*add_filter( 'block_categories', 'block_robo_gallery_add_category', 10, 2 );

function block_robo_gallery_add_category( $categories, $post ) {
	return array_merge(
		$categories,
		array(
			array(
				'slug' => 'robo-blocks',
				'title' => __( 'Robo Gallery Blocks', 'robo-gallery' ),
				'icon'  => 'wordpress',
			),
		)
	);
}*/
