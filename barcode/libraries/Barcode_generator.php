<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Barcode_generator
{
	protected $_font_barcode;
	protected $_font_plain;


	// --------------------------------------------------------------------------


	/**
	 * Construct the Barcode library
	 */
	public function __construct()
	{
		$this->_font_barcode	.= __DIR__ . '/../resources/fonts/free3of9/free3of9.ttf';
		$this->_font_plain		.= __DIR__ . '/../resources/fonts/opensans/OpenSans-Regular.ttf';
	}


	// --------------------------------------------------------------------------


	/**
	 * Generates the barcode data
	 * @param  string  $string  The string to encode
	 * @param  integer $width   The width of the image
	 * @param  integer $height  The height of the image
	 * @return data
	 */
	private function _generate( $string, $width = NULL, $height = NULL )
	{
		//	Prep the string
		$string = strtoupper( $string );
		$string = preg_replace( '/[^A-Z0-9]/', '', $string );

		// --------------------------------------------------------------------------

		if ( empty( $string ) ) :

			$this->_set_error( 'String cannot be empty' );
			return FALSE;

		endif;

		// --------------------------------------------------------------------------

		//	Create an oversized image first. We're going to generate the barcode with
		//	the text centered then crop it to fit into an image of the requested size.

		$_width		= 2000;
		$_height	= 60;
		$_img		= imagecreate( $_width, $_height );

		//	Define the colours
		$background = imagecolorallocate( $_img, 255, 255, 255 );
		$black		= imagecolorallocate( $_img, 0, 0, 0 );

		// --------------------------------------------------------------------------

		//	Write the barcode text, centered
		$_fontsize	= 36;
		$_font		= $this->_font_barcode;

		$_box		= @imageTTFBbox( $_fontsize, 0, $_font, $string );
		$_textw		= abs($_box[4] - $_box[0]);
		$_texth		= abs($_box[5] - $_box[1]);
		$_xcord		= ($_width/2)-($_textw/2)-2;
		$_ycord		= $_texth;

		imagettftext( $_img, $_fontsize, 0, $_xcord, $_ycord, $black, $_font, $string );

		$_barcode_height	= $_texth;
		$_barcode_width		= $_textw;
		$_barcode_xcord		= $_xcord;

		// --------------------------------------------------------------------------

		//	Write the number text, also centered
		$_fontsize	= 14;
		$_font		= $this->_font_plain;

		$_box		= @imageTTFBbox( $_fontsize, 0, $_font, $string );
		$_textw		= abs($_box[4] - $_box[0]);
		$_texth		= abs($_box[5] - $_box[1]);
		$_xcord		= ($_width/2)-($_textw/2)-2;
		$_ycord		= $_barcode_height + $_texth;

		//	If the string contains a J or a Q then the positioning of the string is
		//	skewed, adjust accordingly.

		if ( strpos( $string, 'J' ) == FALSE && strpos( $string, 'Q' ) == FALSE ) :

			$_ycord += 5;

		endif;

		imagettftext( $_img, $_fontsize, 0, $_xcord, $_ycord, $black, $_font, $string );

		$_text_height	= $_texth;
		$_text_width	= $_textw;
		$_text_xcord	= $_xcord;

		// --------------------------------------------------------------------------

		//	Crop image to fit barcode; it seems there's a PHP bug however - image crop
		//	creates black lines when cropping: https://bugs.php.net/bug.php?id=67447

		$_options			= array();
		$_options['height']	= $_height;
		$_options['width']	= max( $_barcode_width, $_text_width );
		$_options['y']		= 0;
		$_options['x']		= min( $_barcode_xcord, $_text_xcord );

		$_img_cropped = imagecrop( $_img, $_options );

		imagedestroy( $_img );

		// --------------------------------------------------------------------------

		//	If a user defined width or height has been supplied then resize to those
		//	dimensions.

		if ( ! is_null( $width ) || ! is_null( $height ) ) :

			$width		= is_null( $width ) ? $_options['width']: $width;
			$height		= is_null( $height ) ? $_options['height']: $height;

			$_width		= min( $width, $_options['width'] );
			$_height	= min( $height, $_options['height'] );

			$_img_user	= imagecreate( $width, $height );
			$background	= imagecolorallocate( $_img_user, 255, 255, 255 ) ;

			//	Copy and resample the image
			imagecopyresampled( $_img_user, $_img_cropped, 0, 0, 0, 0, $_width, $_height, $_options['width'], $_options['height'] );

			return $_img_user;

		else :

			return $_img_cropped;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Saves a barcode to disk
	 * @param  string  $string The string to encode
	 * @param  string  $path   Where to save the image
	 * @param  integer $width  The width of the image
	 * @param  integer $height The height of the image
	 * @return boolean
	 */
	public function save( $string, $path, $width = NULL, $height = NULL )
	{
		$_img = $this->_generate( $string, $width, $height );

		if ( $_img ) :

			imagepng( $_img, $path );
			imagedestroy( $_img );

			return TRUE;

		else :

			return FALSE;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Sends a barcode to the browser as an image
	 * @param  string  $string The string to encode
	 * @param  integer $width  The width of the image
	 * @param  integer $height The height of the image
	 * @return void
	 */
	public function show( $string, $width = NULL, $height = NULL )
	{
		$_img = $this->_generate( $string, $width, $height );

		if ( $_img ) :

			header( 'Content-type: image/png' );
			imagepng( $_img );
			imagedestroy( $_img );
			exit(0);

		else :

			return FALSE;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns the encoded image as a base64 string
	 * @param  string  $string The string to encode
	 * @param  integer $width  The width of the image
	 * @param  integer $height The height of the image
	 * @return string
	 */
	public function base64( $string, $width = NULL, $height = NULL )
	{
		$_img = $this->_generate( $string, $width, $height );

		if ( $_img ) :

			ob_start();
			imagepng( $_img );
			$_contents =  ob_get_contents();
			ob_end_clean();

			return base64_encode( $_contents );

		else :

			return FALSE;

		endif;
	}


	// --------------------------------------------------------------------------


	/**
	 * Retrieves the error array
	 * @return array
	 */
	public function get_errors()
	{
		return $this->_errors;
	}


	// --------------------------------------------------------------------------


	/**
	 * Returns the last error
	 * @return string
	 */
	public function last_error()
	{
		return end( $this->_errors );
	}


	// --------------------------------------------------------------------------


	/**
	 * Adds an error message
	 * @param string $error The error to add
	 */
	private function _set_error( $error )
	{
		$this->_errors[] = $error;
	}
}

/* End of file Barcode_generator.php */
/* Location: ./modules-barcode/barcode/libraries/Barcode_generator.php */
