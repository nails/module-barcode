<?php

/**
 * This class provides an interface for generating Barcodes
 *
 * @package     Nails
 * @subpackage  module-barcode
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Barcode\Library;

class Generator
{
    use \Nails\Common\Traits\ErrorHandling;

    // --------------------------------------------------------------------------

    protected $sFontPathBarcode;
    protected $sFontPathPlain;

    // --------------------------------------------------------------------------

    /**
     * Construct the Barcode library
     */
    public function __construct()
    {
        $this->sFontPathBarcode = __DIR__ . '/../../assets/fonts/free3of9/free3of9.ttf';
        $this->sFontPathPlain   = __DIR__ . '/../../assets/fonts/opensans/OpenSans-Regular.ttf';
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the barcode data
     * @param  string  $sString  The string to encode
     * @param  integer $iWidth   The width of the image
     * @param  integer $iHeight  The height of the image
     * @return data
     */
    private function generateBarcode($sString, $iWidth = null, $iHeight = null)
    {
        //  Prep the string
        $sString = strtoupper($sString);
        $sString = preg_replace('/[^A-Z0-9]/', '', $sString);

        // --------------------------------------------------------------------------

        if (empty($sString)) {

            $this->_set_error('String cannot be empty');
            return false;
        }

        // --------------------------------------------------------------------------

        /**
         * Create an oversized image first. We're going to generate the barcode with
         * the text centered then crop it to fit into an image of the requested size.
         */

        $_width  = 2000;
        $_height = 60;
        $rImg    = imagecreate($_width, $_height);

        //  Define the colours
        $rColorWhite = imagecolorallocate($rImg, 255, 255, 255);
        $rColorBlack = imagecolorallocate($rImg, 0, 0, 0);

        // --------------------------------------------------------------------------

        //  Write the barcode text, centered
        $iFontSize  = 36;
        $sFontPath  = $this->sFontPathBarcode;

        $aTextBox    = @imageTTFBbox($iFontSize, 0, $sFontPath, $sString);
        $iTextWidth  = abs($aTextBox[4] - $aTextBox[0]);
        $iTextHeight = abs($aTextBox[5] - $aTextBox[1]);
        $nTextX      = ($_width/2)-($iTextWidth/2)-2;
        $nTextY      = $iTextHeight;

        imagettftext($rImg, $iFontSize, 0, $nTextX, $nTextY, $rColorBlack, $sFontPath, $sString);

        $nBarcodeHeight = $iTextHeight;
        $nBarcodeWidth  = $iTextWidth;
        $nBarcodeTextX  = $nTextX;

        // --------------------------------------------------------------------------

        //  Write the number text, also centered
        $iFontSize = 14;
        $sFontPath = $this->sFontPathPlain;

        $aTextBox    = @imageTTFBbox($iFontSize, 0, $sFontPath, $sString);
        $iTextWidth  = abs($aTextBox[4] - $aTextBox[0]);
        $iTextHeight = abs($aTextBox[5] - $aTextBox[1]);
        $nTextX      = ($_width/2)-($iTextWidth/2)-2;
        $nTextY      = $nBarcodeHeight + $iTextHeight;

        /**
         * If the string contains a J or a Q then the positioning of the string
         * is skewed, adjust accordingly.
         */

        if (strpos($sString, 'J') == false && strpos($sString, 'Q') == false) {

            $nTextY += 5;
        }

        imagettftext($rImg, $iFontSize, 0, $nTextX, $nTextY, $rColorBlack, $sFontPath, $sString);

        // --------------------------------------------------------------------------

        /**
         * Crop image to fit barcode; it seems there's a PHP bug however - image crop
         * creates black lines when cropping: https://bugs.php.net/bug.php?id=67447
         */

        $aOptions           = array();
        $aOptions['height'] = $_height;
        $aOptions['width']  = max($nBarcodeWidth, $iTextWidth);
        $aOptions['y']      = 0;
        $aOptions['x']      = min($nBarcodeTextX, $nTextX);

        $rImgCropped = imagecrop($rImg, $aOptions);

        imagedestroy($rImg);

        // --------------------------------------------------------------------------

        /**
         * If a user defined width or height has been supplied then resize to those
         * dimensions.
         */

        if (!is_null($iWidth) || !is_null($iHeight)) {

            $iWidth      = is_null($iWidth) ? $aOptions['width']: $iWidth;
            $iHeight     = is_null($iHeight) ? $aOptions['height']: $iHeight;
            $_width      = min($iWidth, $aOptions['width']);
            $_height     = min($iHeight, $aOptions['height']);
            $rImgUser    = imagecreate($iWidth, $iHeight);
            $rColorWhite = imagecolorallocate($rImgUser, 255, 255, 255) ;

            imagecopyresampled(
                $rImgUser,
                $rImgCropped,
                0,
                0,
                0,
                0,
                $_width,
                $_height,
                $aOptions['width'],
                $aOptions['height']
            );

            return $rImgUser;

        } else {

            return $rImgCropped;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Saves a barcode to disk
     * @param  string  $sString The string to encode
     * @param  string  $sPath   Where to save the image
     * @param  integer $iWidth  The width of the image
     * @param  integer $iHeight The height of the image
     * @return boolean
     */
    public function save($sString, $sPath, $iWidth = null, $iHeight = null)
    {
        $rImg = $this->generateBarcode($sString, $iWidth, $iHeight);

        if ($rImg) {

            imagepng($rImg, $sPath);
            imagedestroy($rImg);

            return true;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sends a barcode to the browser as an image
     * @param  string  $sString The string to encode
     * @param  integer $iWidth  The width of the image
     * @param  integer $iHeight The height of the image
     * @return void
     */
    public function show($sString, $iWidth = null, $iHeight = null)
    {
        $rImg = $this->generateBarcode($sString, $iWidth, $iHeight);

        if ($rImg) {

            header('Content-type: image/png');
            imagepng($rImg);
            imagedestroy($rImg);
            exit(0);

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the encoded image as a base64 string
     * @param  string  $sString The string to encode
     * @param  integer $iWidth  The width of the image
     * @param  integer $iHeight The height of the image
     * @return string
     */
    public function base64($sString, $iWidth = null, $iHeight = null)
    {
        $rImg = $this->generateBarcode($sString, $iWidth, $iHeight);

        if ($rImg) {

            ob_start();
            imagepng($rImg);
            $sContents = ob_get_contents();
            ob_end_clean();

            return base64_encode($sContents);

        } else {

            return false;
        }
    }
}
