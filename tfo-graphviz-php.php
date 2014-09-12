<?php // $Id$

require_once(dirname(__FILE__).'/tfo-graphviz-method.php');

# Check the status of enable_dl() - if it's not enabled and gv.so isn't loaded
# then none of this will work
if (!extension_loaded('gv')) {
	$dl_enabled = get_cfg_var('dl_enabled');
	if (!$dl_enabled) {
		# We won't be able to load the module, so this method isn't usable
		$tfo_include_error = "The PHP 'gv' module is not loaded and 'dl_enabled' is off";
		return FALSE;
	}
}

foreach(array('gv.php', 'libgv-php5/gv.php', 'libgv-php4/gv.php', 'libgv-php/gv.php') as $gv) {
	try {
		@include_once($gv);
	} catch (Exception $e) {}
	if(class_exists('gv')) break;
}

if(!class_exists('gv')) {
	// Extension didn't load, so we can't either
	$tfo_include_error = "The PHP 'gv' module did not load";
	return FALSE;
}

class TFO_Graphviz_PHP extends TFO_Graphviz_Method {
	var $tmp_file;
	var $img_path_base;
	var $img_url_base;
	var $file;

	var $_debug = false;

	function TFO_Graphviz_PHP($dot, $atts, $img_path_base=null, $img_url_base=null) {
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
		if(!class_exists('gv'))
			return new WP_Error('blank', __("PHP 'gv' module not loaded", 'tfo-graphviz'));

		if(empty($this->dot))
			return new WP_Error('blank', __('No graph provided', 'tfo-graphviz'));

		$gv = gv::readstring($this->dot);
		if(!$gv)
			return new WP_Error('blank', __('Graphviz could not parse the DOT', 'tfo-graphviz'));

		if(!gv::layout($gv, $this->lang)) {
			gv::rm($gv);
			return new WP_Error('blank', __('Graphviz did not like the lang', 'tfo-graphviz'));
		}

		if(!gv::render($gv, $this->output, $imgfile)) {
			gv::rm($gv);
			return new WP_Error('graphviz_exec', __( 'Graphviz cannot generate graph', 'tfo-graphviz' ));
		}

		if($this->imap) {
			if(!gv::render($gv, 'cmapx', $mapfile)) {
				gv::rm($gv);
				return new WP_Error('graphviz_exec', __( 'Graphviz cannot generate image map', 'tfo-graphviz' ));
			}
		}

		gv::rm($gv); // all done

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

return TRUE;
