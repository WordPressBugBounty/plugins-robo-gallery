<?php
if ( ! defined( 'WPINC' ) )  die;

// the bundled copy of scssphp lives under its own namespace (ScssPhpRBE\...), see init.php
require_once ROBO_GALLERY_VENDOR_PATH.'scss/scssphp-1.12.1/scss.inc.php';
use ScssPhpRBE\ScssPhp\Compiler;

if( function_exists('robogallery_init_scss_compile_current') ) return ;

function robogallery_init_scss_compile_current(){
	return new Compiler() ;	
}
