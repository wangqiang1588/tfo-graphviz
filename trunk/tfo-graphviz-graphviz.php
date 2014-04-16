<?php // $Id$

/*
Must define the following constants:
TFO_GRAPHVIZ_GRAPHVIZ_PATH
*/

require_once(dirname(__FILE__).'/tfo-graphviz-method.php');

class TFO_Graphviz_Graphviz extends TFO_Graphviz_Method {
	var $tmp_file;
	var $img_path_base;
	var $img_url_base;
	var $file;

	var $_debug = false;

	function TFO_Graphviz_Graphviz($dot, $atts, $img_path_base=null, $img_url_base=null) {
		$this->__construct($dot, $atts, $img_path_base, $img_url_base);
	}

	function __construct($dot, $atts, $img_path_base=null, $img_url_base=null) {
		parent::__construct($dot, $atts);
		$this->img_path_base = rtrim( $img_path_base, '/\\' );
		$this->img_url_base = rtrim( $img_url_base, '/\\' );

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
		if(!defined('TFO_GRAPHVIZ_GRAPHVIZ_PATH') || !file_exists(TFO_GRAPHVIZ_GRAPHVIZ_PATH))
			return new WP_Error('graphviz_path', __('Graphviz path not specified, is wrong or binary is missing.', 'tfo-graphviz'));

		if(empty($this->dot))
			return new WP_Error('blank', __('No graph provided', 'tfo-graphviz'));

		$args = array(
			'-K'.$this->lang,
		);
		if($this->imap) {
			array_push($args,
				'-Tcmapx',
				'-o'.$mapfile
			);
		}
		array_push($args,
			'-T'.$this->output,
			'-o'.$imgfile
		);
			
		$cmd = TFO_GRAPHVIZ_GRAPHVIZ_PATH;
		foreach($args as $arg) {
			$cmd .= ' '.escapeshellarg($arg);
		}

		$ds = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('file', '/dev/null', 'w'),
		);
		$pipes = undef;
		$proc = proc_open($cmd, $ds, $pipes, '/tmp', array());
		if(is_resource($proc)) {
			fwrite($pipes[0], $this->dot);
			fclose($pipes[0]);
	
			$out = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			proc_close($proc);
		} else {
			return new WP_Error('graphviz_exec', __( 'Graphviz cannot generate graph', 'tfo-graphviz' ));
		}

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
