<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
* Name:			Barcode
*
* Description:	Generates barcodes
*
*/

/**
 * OVERLOADING NAILS' BARCODE MODULE
 *
 * Note the name of this class; done like this to allow apps to extend this class.
 * Read full explanation at the bottom of this file.
 *
 **/

class NAILS_Barcode extends NAILS_Controller
{
	protected $_cache_dir;
	protected $_cache_headers_set;
	protected $_cache_headers_max_age;
	protected $_cache_headers_last_modified;
	protected $_cache_headers_expires;
	protected $_cache_headers_file;
	protected $_cache_headers_hit;


	// --------------------------------------------------------------------------


	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------------------------------------

		$this->_cache_dir = DEPLOY_CACHE_DIR;
	}


	// --------------------------------------------------------------------------


	protected function _serve_from_cache( $cache_file, $hit = TRUE )
	{
		/**
		 * Cache object exists, set the appropriate headers and return the
		 * contents of the file.
		 **/

		$_stats = stat( $this->_cache_dir . $cache_file );

		//	Set cache headers
		$this->_set_cache_headers( $_stats[9], $cache_file, $hit );

		header( 'Content-Type: image/png', TRUE );

		// --------------------------------------------------------------------------

		//	Send the contents of the file to the browser
		echo file_get_contents( $this->_cache_dir . $cache_file );

		/**
		 * Kill script, th, th, that's all folks.
		 * Stop the output class from hijacking our headers and
		 * setting an incorrect Content-Type
		 **/

		exit(0);
	}


	// --------------------------------------------------------------------------


	protected function _set_cache_headers( $last_modified, $file, $hit )
	{
		//	Set some flags
		$this->_cache_headers_set			= TRUE;
		$this->_cache_headers_max_age		= 31536000; // 1 year
		$this->_cache_headers_last_modified	= $last_modified;
		$this->_cache_headers_expires		= time() + $this->_cache_headers_max_age;
		$this->_cache_headers_file			= $file;
		$this->_cache_headers_hit			= $hit ? 'HIT' : 'MISS';

		// --------------------------------------------------------------------------

		header( 'Cache-Control: max-age=' . $this->_cache_headers_max_age . ', must-revalidate', TRUE );
		header( 'Last-Modified: ' . date( 'r', $this->_cache_headers_last_modified ), TRUE );
		header( 'Expires: ' . date( 'r', $this->_cache_headers_expires ), TRUE );
		header( 'ETag: "' . md5( $this->_cache_headers_file ) . '"', TRUE );
		header( 'X-CDN-CACHE: ' . $this->_cache_headers_hit, TRUE );
	}


	// --------------------------------------------------------------------------


	protected function _unset_cache_headers()
	{
		if ( empty( $this->_cache_headers_set ) ) :

			return FALSE;

		endif;

		// --------------------------------------------------------------------------

		//	Remove previously set headers
		header_remove( 'Cache-Control' );
		header_remove( 'Last-Modified' );
		header_remove( 'Expires' );
		header_remove( 'ETag' );
		header_remove( 'X-CDN-CACHE' );

		// --------------------------------------------------------------------------

		//	Set new "do not cache" headers
		header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT', TRUE );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT', TRUE );
		header( 'Cache-Control: no-store, no-cache, must-revalidate', TRUE );
		header( 'Cache-Control: post-check=0, pre-check=0', FALSE );
		header( 'Pragma: no-cache', TRUE );
		header( 'X-CDN-CACHE: MISS', TRUE );
	}


	// --------------------------------------------------------------------------


	protected function _serve_not_modified( $file )
	{
		if ( function_exists( 'apache_request_headers' ) ) :

			$_headers = apache_request_headers();

		elseif ( $this->input->server( 'HTTP_IF_NONE_MATCH' ) ) :

			$_headers					= array();
			$_headers['If-None-Match']	= $this->input->server( 'HTTP_IF_NONE_MATCH' );

		elseif( isset( $_SERVER ) ) :

			/**
			 * Can we work the headers out for ourself?
			 * Credit: http://www.php.net/manual/en/function.apache-request-headers.php#70810
			 **/

			$_headers	= array();
			$rx_http	= '/\AHTTP_/';
			foreach ( $_SERVER as $key => $val ) :

				if ( preg_match( $rx_http, $key ) ) :

					$arh_key	= preg_replace($rx_http, '', $key);
					$rx_matches	= array();

					/**
					 * Do some nasty string manipulations to restore the original letter case
					 * this should work in most cases
					 **/

					$rx_matches = explode('_', $arh_key);

					if ( count( $rx_matches ) > 0 && strlen( $arh_key ) > 2 ) :

						foreach ( $rx_matches as $ak_key => $ak_val ) :

							$rx_matches[$ak_key] = ucfirst( $ak_val );

						endforeach;

						$arh_key = implode( '-', $rx_matches );

					endif;

					$_headers[$arh_key] = $val;

				endif;

			endforeach;

		else :

			//	Give up.
			return FALSE;

		endif;

		if ( isset( $_headers['If-None-Match'] ) && $_headers['If-None-Match'] == '"' . md5( $file ) . '"' ) :

			header( $this->input->server( 'SERVER_PROTOCOL' ) . ' 304 Not Modified', TRUE, 304 );
			return TRUE;

		endif;

		// --------------------------------------------------------------------------

		return FALSE;
	}


	// --------------------------------------------------------------------------

	public function index()
	{
		$_string		= $this->uri->segment( 2 ) ? $this->uri->segment( 2 ) : '';
		$_width			= $this->uri->segment( 3 ) ? $this->uri->segment( 3 ) : NULL;
		$_height		= $this->uri->segment( 4 ) ? $this->uri->segment( 4 ) : NULL;
		$_cache_file	= 'BARCODE-' . $_string. '-' . $_width . 'x' . $_height . '.png';

		// --------------------------------------------------------------------------

		//	Check the request headers; avoid hitting the disk at all if possible. If the Etag
		//	matches then send a Not-Modified header and terminate execution.

		if ( $this->_serve_not_modified( $_cache_file ) ) :

			return;

		endif;

		// --------------------------------------------------------------------------

		//	The browser does not have a local cache (or it's out of date) check the
		//	cache to see if this image has been processed already; serve it up if
		//	it has.

		if ( file_exists( $this->_cache_dir . $_cache_file ) ) :

			$this->_serve_from_cache( $_cache_file );

		else :

			//	Generate and save to cache
			$this->load->library( 'barcode/barcode_generator' );
			$_result = $this->barcode_generator->save( $_string, $this->_cache_dir . $_cache_file, $_width, $_height );

			if ( $_result ) :

				$this->_serve_from_cache( $_cache_file );

			else :

				header( 'Cache-Control: no-cache, must-revalidate', TRUE );
				header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT', TRUE );
				header( 'Content-type: application/json', TRUE );
				header( $this->input->server( 'SERVER_PROTOCOL' ) . ' 400 Bad Request', TRUE, 400 );

				// --------------------------------------------------------------------------

				$_out = array(

					'status'	=> 400,
					'message'	=> 'Failed to generate barcode.',
					'error'		=> $this->barcode_generator->last_error()

				);

				echo json_encode( $_out );

				// --------------------------------------------------------------------------

				//	Kill script, th, th, that's all folks.
				//	Stop the output class from hijacking our headers and
				//	setting an incorrect Content-Type

				exit(0);

			endif;

		endif;
	}


	// --------------------------------------------------------------------------


	public function _remap()
	{
		$this->index();
	}
}


// --------------------------------------------------------------------------


/**
 * OVERLOADING NAILS' EMAIL MODULES
 *
 * The following block of code makes it simple to extend one of the core Nails
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION_CLASSNAME
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if ( ! defined( 'NAILS_ALLOW_EXTENSION_BARCODE' ) ) :

	class Barcode extends NAILS_Barcode
	{
	}

endif;


/* End of file barcode.php */
/* Location: ./modules-barcode/barcode/controllers/barcode.php */
