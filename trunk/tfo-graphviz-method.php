<?php
/**
 * tfo-graphviz-method.php
 *
 * @package default
 */


// $Id$

class TFO_Graphviz_Method {
	var $dot;
	var $lang, $simple, $output, $href, $imap;
	var $url;

	var $error;


	/**
	 * Base constructor for Graphviz methods.
	 *
	 * @param string  $dot  Type of Graphviz source.
	 * @param hash    $atts List of attributes for Graphviz generation.
	 */
	function TFO_GraphvizMethod($dot, $atts) {
		$this->__construct($dot, $atts);
	}


	/**
	 * Constructor implementation.
	 *
	 * @param string  $dot  Type of Graphviz source.
	 * @param hash    $atts List of attributes for Graphviz generation.
	 */
	function __construct($dot, $atts) {
		$this->dot  = (string) $dot;
		foreach (array('id', 'lang', 'simple', 'output', 'href', 'imap', 'title') as $att) {
			if (array_key_exists($att, $atts))
				$this->$att = $atts[$att];
		}
		$this->url = false;
	}


	/**
	 * Returns a hash of the contents of the current source file.
	 * Requires the file already be loaded into $this->dot .
	 *
	 * @return string The hash.
	 */
	function hash_file() {
		$hash = md5($this->dot);
		return substr($hash, 0, 32);
	}


	/**
	 * Returns the current URL.
	 *
	 * @return string The current URL.
	 */
	function url() {
		return $this->url;
	}


}


?>
