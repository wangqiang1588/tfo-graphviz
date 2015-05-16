<?php
/**
 * tfo-graphviz-remote.php
 *
 * @package default
 */


// $Id$

require_once dirname(__FILE__).'/tfo-graphviz-method.php';

// Not supported, yet.
$tfo_include_error = "Not supported";
return FALSE;

if (!function_exists('curl_init')) {
	// Extension didn't load, so we can't either
	$tfo_include_error = "Requires 'curl' module";
	return FALSE;
}

class TFO_Graphviz_Remote extends TFO_Graphviz_Method {
	var $tmp_file;
	var $img_path_base;
	var $img_url_base;
	var $file;
	var $remote_key;

	var $_debug = false;


	/**
	 * Constructor for Graphviz.
	 *
	 *
	 * @param string  $dot           Type of Graphviz source.
	 * @param hash    $atts          List of attributes for Graphviz generation.
	 * @param string  $img_path_base (optional) Directory path for image generation (optional)
	 * @param string  $img_url_base  (optional) URL that points to $img_path_base (optional)
	 */
	function TFO_Graphviz_Remote($dot, $atts, $img_path_base=null, $img_url_base=null) {
		$this->__construct($dot, $atts, $img_path_base, $img_url_base);
	}


	/**
	 * Constructor implementation.
	 *
	 *
	 * @param string  $dot           Type of Graphviz source.
	 * @param hash    $atts          List of attributes for Graphviz generation.
	 * @param string  $img_path_base (optional) Directory path for image generation (optional)
	 * @param string  $img_url_base  (optional) URL that points to $img_path_base (optional)
	 */
	function __construct($dot, $atts, $img_path_base=null, $img_url_base=null) {
		parent::__construct($dot, $atts);
		$this->img_path_base = rtrim( $img_path_base, '/\\' );
		$this->img_url_base = rtrim( $img_url_base, '/\\' );

		if (!empty($atts['remote_key'])) $this->remote_key = $atts['remote_key'];
		else $this->remote_key = false;

		@define('TFO_WORDPRESS_METHOD_REMOTE_URL', 'http://graphviz.flirble.org/gv/wp.php');

		// For PHP 4
		if (version_compare( PHP_VERSION, 5, '<'))
			register_shutdown_function(array(&$this, '__destruct'));
	}


	/**
	 * Destructor.
	 */
	function __destruct() {
		$this->unlink_tmp_files();
	}


	/**
	 * Processes the Graphviz file.
	 *
	 * @param string  $imgfile Name of the file in which to store the generated image.
	 * @param string  $mapfile Name of the file in which to store any generated image map.
	 * @return bool True on success, False otherwise.
	 */
	function process_dot($imgfile, $mapfile) {
		if (empty($this->dot))
			return new WP_Error('blank', __('No graph provided', 'tfo-graphviz'));

		$post = array(
			'_dot' => $this->dot,
			'lang' => $this->lang,
			'output' => $this->output,
		);
		if ($this->imap) $post['imap'] = 1;
		if ($this->remote_key) {
			$post['_key'] = $this->remote_key;
			$post['_site'] = home_url();
		}

		// Generate HTTP request to process the DOT
		$curl = curl_init(TFO_WORDPRESS_METHOD_REMOTE_URL);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$gv_data = curl_exec($curl);
		$gv_error = curl_error($curl);
		curl_close($curl);

		if ($gv_error)
			return new WP_Error('blank', __('Can\'t fetch graph: '.$gv_error, 'tfo-graphviz'));

		// Extract details from the returned data
		// decode by splitting on \n\n
		$parts = explode("\n\n", $gv_data);
		unset($gv_data);

		if (strpos($parts[0], "Status: OK") === false) {
			return new WP_Error('blank', __('Can\'t fetch graph: Status != OK', 'tfo-graphviz'));
		}

		$imap = false;
		$image = false;
		$imagetype = false;

		foreach ($parts as $part) {
			$nl = strpos($part, "\n");
			if ($nl === false) continue;
			$tags = explode(" ", substr($part, 0, $nl));
			if ($tags[0] != '#') continue;

			if ($tags[1] == 'IMAP') {
				$imap = substr($part, $nl);
			} else if ($tags[1] == 'IMAGE') {
				$image = substr($part, $nl);
				$imagetype = $tags[2];
			}
		}
		unset($parts);

		if ($image && $imgfile)
			file_put_contents($imgfile, base64_decode($image));
		if ($imap && $this->imap && $mapfile)
			file_put_contents($mapfile, base64_decode($imap));

		unset($image);
		unset($imap);

		if (!file_exists($imgfile) || ($this->imap && !file_exists($mapfile))) {
			return new WP_Error('graphviz_exec', __( 'Graphviz cannot generate graph', 'tfo-graphviz' ), "No output files generated.");
		}

		return true;
	}


	/**
	 * Cleans up any temporary files.
	 *
	 * @return bool True on success, False otherwise.
	 */
	function unlink_tmp_files() {
		if ( $this->_debug )
			return;

		if ( !$this->tmp_file )
			return false;

		@unlink( $this->tmp_file );

		return true;
	}


	/**
	 * Calculates the URL for the resulting image; this processes the DOT file if necessary.
	 *
	 * @return bool True on success, False otherwise.
	 */
	function url() {
		if ( !$this->img_path_base || !$this->img_url_base ) {
			$this->error = new WP_Error( 'img_url_base', __( 'Invalid path or URL' ) );
			return $this->error;
		}

		$hash = $this->hash_file();

		$imgfile = "$this->img_path_base/$hash.$this->output";
		$mapfile = "$this->img_path_base/$hash.map";
		if (is_super_admin() || !file_exists($imgfile) || ($this->imap && !file_exists($mapfile))) {
			$ret = $this->process_dot($imgfile, $mapfile);
			if ( is_wp_error( $file ) ) {
				$this->error =& $ret;
				return $this->error;
			}
		}

		$this->file = $imgfile;
		$this->url = "$this->img_url_base/$hash.$this->output";
		if ($this->imap) {
			if (file_exists($mapfile)) $this->imap = file_get_contents($mapfile);
			else $this->imap = false;
		}
		return $this->url;
	}


}


return TRUE;
