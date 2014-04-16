<?php // $Id$

require_once(dirname(__FILE__).'/tfo-graphviz-method.php');

if(!function_exists('curl_init')) {
	// Extension didn't load, so we can't either
	return FALSE;
}

class TFO_Graphviz_Remote extends TFO_Graphviz_Method {
	var $tmp_file;
	var $img_path_base;
	var $img_url_base;
	var $file;
	var $remote_key;

	var $_debug = false;

	function TFO_Graphviz_Remote($dot, $atts, $img_path_base=null, $img_url_base=null) {
		$this->__construct($dot, $atts, $img_path_base, $img_url_base);
	}

	function __construct($dot, $atts, $img_path_base=null, $img_url_base=null) {
		parent::__construct($dot, $atts);
		$this->img_path_base = rtrim( $img_path_base, '/\\' );
		$this->img_url_base = rtrim( $img_url_base, '/\\' );

		if(!empty($atts['remote_key'])) $this->remote_key = $atts['remote_key'];
		else $this->remote_key = false;

		@define('TFO_WORDPRESS_METHOD_REMOTE_URL', 'http://graphviz.flirble.org/gv/wp.php');

		// For PHP 4
		if(version_compare( PHP_VERSION, 5, '<'))
			register_shutdown_function(array(&$this, '__destruct'));
	}

	function __destruct() {
		$this->unlink_tmp_files();
	}

	function hash_file() {
		$hash = md5($this->dot);
		return substr($hash, 0, 32);
	}

	function process_dot($imgfile, $mapfile) {
		if(empty($this->dot))
			return new WP_Error('blank', __('No graph provided', 'tfo-graphviz'));

		$post = array(
			'_dot' => $this->dot,
			'lang' => $this->lang,
			'output' => $this->output,
		);
		if($this->imap) $post['imap'] = 1;
		if($this->remote_key) {
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

		if($gv_error)
			return new WP_Error('blank', __('Can\'t fetch graph: '.$gv_error, 'tfo-graphviz'));

		// Extract details from the returned data
		// decode by splitting on \n\n
		$parts = explode("\n\n", $gv_data);
		unset($gv_data);

		if(strpos($parts[0], "Status: OK") === false) {
			return new WP_Error('blank', __('Can\'t fetch graph: Status != OK', 'tfo-graphviz'));
		}

		$imap = false;
		$image = false;
		$imagetype = false;

		foreach($parts as $part) {
		        $nl = strpos($part, "\n");
		        if($nl === false) continue;
		        $tags = explode(" ", substr($part, 0, $nl));
		        if($tags[0] != '#') continue;

		        if($tags[1] == 'IMAP') {
		                $imap = substr($part, $nl);
		        } else if($tags[1] == 'IMAGE') {
		                $image = substr($part, $nl);
		                $imagetype = $tags[2];
		        }
		}
		unset($parts);

		if($image && $imgfile)
			file_put_contents($imgfile, base64_decode($image));
		if($imap && $this->imap && $mapfile)
			file_put_contents($mapfile, base64_decode($imap));

		unset($image);
		unset($imap);

		if(!file_exists($imgfile) || ($this->imap && !file_exists($mapfile))) {
			return new WP_Error('graphviz_exec', __( 'Graphviz cannot generate graph', 'tfo-graphviz' ), "No output files generated.");
		}

		return true;
	}

	function unlink_tmp_files() {
		if ( $this->_debug )
			return;

		if ( !$this->tmp_file )
			return false;

		@unlink( $this->tmp_file );

		return true;
	}

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
		if($this->imap) {
			if(file_exists($mapfile)) $this->imap = file_get_contents($mapfile);
			else $this->imap = false;
		}
		return $this->url;
	}
}

// In WordPress, __() is used for gettext.  If not available, just return the string.
if ( !function_exists('__') ) { function __($a) { return $a; } }

// In WordPress, this class is used to pass errors between functions.  If not available, recreate in simplest possible form.
if ( !class_exists('WP_Error') ) :
class WP_Error {
	var $e;
	function WP_Error( $c, $m ) { $this->e = $m; }
	function get_error_message() { return $this->e; }
}
function is_wp_error($a) { return is_object($a) && is_a($a, 'WP_Error'); }
endif;

return TRUE;
