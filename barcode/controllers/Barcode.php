<?php

/**
 * This class provides a front end route for generating barcodes
 *
 * @package     Nails
 * @subpackage  module-barcode
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Common\Service\FileCache;
use Nails\Factory;
use App\Controller\Base;

class Barcode extends Base
{
    protected $cacheDir;
    protected $cacheHeadersSet;
    protected $cacheHeadersMaxAge;
    protected $cacheHeadersLastModified;
    protected $cacheHeadersExpires;
    protected $cacheHeadersFile;
    protected $cacheHeadersHit;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller, set defaults
     */
    public function __construct()
    {
        parent::__construct();

        /** @var FileCache $oFileCache */
        $oFileCache = Factory::service('FileCache');
        $this->cacheDir = $oFileCache->getDir();
    }

    // --------------------------------------------------------------------------

    /**
     * Serve a file from the cache, setting headers as we go then halt execution
     * @param  string  $cacheFile The cache file's filename
     * @param  boolean $hit       Whether this was a cache hit or not
     * @return void
     */
    protected function serveFromCache($cacheFile, $hit = true)
    {
        /**
         * Cache object exists, set the appropriate headers and return the
         * contents of the file.
         **/

        $_stats = stat($this->cacheDir . $cacheFile);

        //  Set cache headers
        $this->setCacheHeaders($_stats[9], $cacheFile, $hit);

        header('Content-Type: image/png', true);

        // --------------------------------------------------------------------------

        //  Send the contents of the file to the browser
        echo file_get_contents($this->cacheDir . $cacheFile);

        /**
         * Kill script, th, th, that's all folks.
         * Stop the output class from hijacking our headers and
         * setting an incorrect Content-Type
         **/

        exit(0);
    }

    // --------------------------------------------------------------------------

    /**
     * Set the correct cache headers
     * @param string  $lastModified The time the source file was last modified
     * @param string  $file         The filename
     * @param boolean $hit          Whether this was a cache hit or not
     */
    protected function setCacheHeaders($lastModified, $file, $hit)
    {
        //  Set some flags
        $this->cacheHeadersSet          = true;
        $this->cacheHeadersMaxAge       = 31536000; // 1 year
        $this->cacheHeadersLastModified = $lastModified;
        $this->cacheHeadersExpires      = time() + $this->cacheHeadersMaxAge;
        $this->cacheHeadersFile         = $file;
        $this->cacheHeadersHit          = $hit ? 'HIT' : 'MISS';

        // --------------------------------------------------------------------------

        header('Cache-Control: max-age=' . $this->cacheHeadersMaxAge . ', must-revalidate', true);
        header('Last-Modified: ' . date('r', $this->cacheHeadersLastModified), true);
        header('Expires: ' . date('r', $this->cacheHeadersExpires), true);
        header('ETag: "' . md5($this->cacheHeadersFile) . '"', true);
        header('X-CDN-CACHE: ' . $this->cacheHeadersHit, true);
    }

    // --------------------------------------------------------------------------

    /**
     * Unset cache headers set by setCacheHeaders()
     * @return void
     */
    protected function unsetCacheHeaders()
    {
        if (empty($this->cacheHeadersSet)) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Remove previously set headers
        header_remove('Cache-Control');
        header_remove('Last-Modified');
        header_remove('Expires');
        header_remove('ETag');
        header_remove('X-CDN-CACHE');

        // --------------------------------------------------------------------------

        //  Set new "do not cache" headers
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT', true);
        header('Cache-Control: no-store, no-cache, must-revalidate', true);
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache', true);
        header('X-CDN-CACHE: MISS', true);
    }

    // --------------------------------------------------------------------------

    /**
     * Serve the "not modified" headers, if appropriate
     * @param  string $file The file to server headers for
     * @return boolean
     */
    protected function serveNotModified($file)
    {
        $oInput = Factory::service('Input');
        if (function_exists('apache_request_headers')) {

            $headers = apache_request_headers();

        } elseif ($oInput->server('HTTP_IF_NONE_MATCH')) {

            $headers                  = array();
            $headers['If-None-Match'] = $oInput->server('HTTP_IF_NONE_MATCH');

        } elseif (isset($_SERVER)) {

            /**
             * Can we work the headers out for ourself?
             * Credit: http://www.php.net/manual/en/function.apache-request-headers.php#70810
             **/

            $headers = array();
            $rxHttp  = '/\AHTTP_/';
            foreach ($_SERVER as $key => $val) {

                if (preg_match($rxHttp, $key)) {

                    $arhKey    = preg_replace($rxHttp, '', $key);
                    $rxMatches = array();

                    /**
                     * Do some nasty string manipulations to restore the original letter case
                     * this should work in most cases
                     **/

                    $rxMatches = explode('_', $arhKey);

                    if (count($rxMatches) > 0 && strlen($arhKey) > 2) {

                        foreach ($rxMatches as $ak_key => $ak_val) {
                            $rxMatches[$ak_key] = ucfirst($ak_val);
                        }

                        $arhKey = implode('-', $rxMatches);
                    }

                    $headers[$arhKey] = $val;
                }
            }

        } else {

            //  Give up.
            return false;
        }

        if (isset($headers['If-None-Match']) && $headers['If-None-Match'] == '"' . md5($file) . '"') {

            header($oInput->server('SERVER_PROTOCOL') . ' 304 Not Modified', true, 304);
            return true;
        }

        // --------------------------------------------------------------------------

        return false;
    }

    // --------------------------------------------------------------------------

    public function index()
    {
        $oInput    = Factory::service('Input');
        $oUri      = Factory::service('Uri');
        $string    = $oUri->segment(2) ?: '';
        $width     = $oUri->segment(3) ?: null;
        $height    = $oUri->segment(4) ?: null;
        $cacheFile = 'BARCODE-' . $string. '-' . $width . 'x' . $height . '.png';

        // --------------------------------------------------------------------------

        /**
         * Check the request headers; avoid hitting the disk at all if possible. If
         * the Etag matches then send a Not-Modified header and terminate execution.
         */

        if ($this->serveNotModified($cacheFile)) {
            return;
        }

        // --------------------------------------------------------------------------

        /**
         * The browser does not have a local cache (or it's out of date) check the cache
         * to see if this image has been processed already; serve it up if it has.
         */

        if (file_exists($this->cacheDir . $cacheFile)) {

            $this->serveFromCache($cacheFile);

        } else {

            //  Generate and save to cache
            $oGenerator = Factory::service('Generator', 'nails/module-barcode');
            $result = $oGenerator->save($string, $this->cacheDir . $cacheFile, $width, $height);

            if ($result) {

                $this->serveFromCache($cacheFile);

            } else {

                header('Cache-Control: no-cache, must-revalidate', true);
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT', true);
                header('Content-Type: application/json', true);
                header($oInput->server('SERVER_PROTOCOL') . ' 400 Bad Request', true, 400);

                // --------------------------------------------------------------------------

                $out = array(
                    'status'  => 400,
                    'message' => 'Failed to generate barcode.',
                    'error'   => $oGenerator->lastError()
                );

                echo json_encode($out);

                // --------------------------------------------------------------------------

                /**
                 * Kill script, th, th, that's all folks.
                 * Stop the output class from hijacking our headers and
                 * setting an incorrect Content-Type
                 **/

                exit(0);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Map all requests to index()
     * @return void
     */
    public function _remap()
    {
        $this->index();
    }
}
