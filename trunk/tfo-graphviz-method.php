<?php // $Id$

class TFO_Graphviz_Method {
	var $dot;
	var $lang, $simple, $output, $href, $imap;
	var $url;

	var $error;

	function TFO_GraphvizMethod($dot, $atts) {
		$this->__construct($dot, $atts);
	}

	function __construct($dot, $atts) {
		$this->dot  = (string) $dot;
		foreach(array('id', 'lang', 'simple', 'output', 'href', 'imap', 'title') as $att) {
			if($atts[$att]) $this->$att = $atts[$att];
		}
		$this->url = false;
	}

	function url() {
		return $this->url;
	}
}
?>
