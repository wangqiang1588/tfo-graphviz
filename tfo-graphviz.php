<?php
/*
Plugin Name: TFO Graphviz
Plugin URI: http://blog.flirble.org/graphviz
Description: Converts inline DOT code into an image, with optional image map, using Graphviz.
Version: 1.0
Author: Chris Luke
Author URI: http://blog.flirble.org/
Copyright: Chris Luke
Copyrught: The Flirble Organisation
License: GPL2+
*/

if(!defined('ABSPATH')) exit;

class TFO_Graphviz {
	var $options;
	var $methods = array(
		'TFO_Graphviz_Graphviz' => 'graphviz',
		'TFO_Graphviz_Remote' => 'remote'
	);
	var $langs = array('dot', 'neato', 'twopi', 'circo', 'fdp');
	var $outputs = array('gif', 'png', 'jpg');
	var $count;

	function init() {
		$this->options = get_option('tfo-graphviz');
		$this->count = 1;
	
		@define('TFO_GRAPHVIZ_GRAPHVIZ_PATH', $this->options['graphviz_path']);
		@define('TFO_GRAPHVIZ_CONVERT_PATH', $this->options['convert_path']);
	
		add_action('wp_head', array(&$this, 'wp_head'));
		add_shortcode('graphviz', array(&$this, 'shortcode'));
	}

	function wp_head() {
		if(empty($this->options['css']))
			return;
?>
<style type="text/css">
/* <![CDATA[ */
<?php echo $this->options['css']; ?>

/* ]]> */
</style>
<?php
	}

	// [graphviz
	//  id="id"
	//  lang="dot|neato|twopi|circo|fdp"
	//  simple="true|false"
	//  output="png|gif|jpg"
	//  imap="true|false"
	//  href="url|self"
	// ]
	// Shortcode -> <img> markup.  Creates images as necessary.
	function shortcode($_atts, $dot) {
		$atts = shortcode_atts(array(
			'id' => 'tfo_graphviz_'.($this->count++),
			'lang' => 'dot',
			'simple' => false,
			'output' => 'png',
			'imap' => false,
			'href' => false,
			'title' => '',
		), $_atts);

		$dot = preg_replace(array('#<br\s*/?>#i', '#</?p>#i'), ' ', $dot);

		$dot = str_replace(
			array('&lt;', '&gt;', '&quot;', '&#8220;', '&#8221;', '&#039;', '&#8125;', '&#8127;', '&#8217;', '&#038;', '&amp;', "\r", "\xa0", '&#8211;'),
			array('<',    '>',    '"',      '"',       '"',        "'",      "'",       "'",       "'",       '&',      '&',    '',    '-', ''),
			$dot
		);

		if($atts['simple']) { # emulate eht-graphviz
			$dot = "digraph ".$atts['id']." {\n$dot\n}\n";
		}

		$gv = $this->graphviz($dot, $atts);
		$url = clean_url($gv->url());
		$href = $gv->href;
		if($href) {
			if(strtolower($href) == 'self') $href = $url;
			else $href = clean_url($href);
		}
		$alt = attribute_escape(is_wp_error($gv->error) ? $gv->error->get_error_message() . ": $gv->dot" : $gv->title);

		$ret = "<img src=\"$url\" class=\"graphviz\"";
		if(!empty($alt)) $ret .= " alt=\"$alt\" title=\"$alt\"";
		if(!empty($gv->imap)) $ret .= " usemap=\"#$gv->id\"";
		$ret .= " />";
		if(!empty($href)) $ret = "<a href=\"".$href."\">$ret</a>";
		if(!empty($gv->imap)) $ret .= "\n$gv->imap";

		return $ret;
	}
	
	function &graphviz($dot, $atts) {
		if(empty($this->methods[$this->options['method']]))
			return false;

		// Validate atts
		$atts['id'] = attribute_escape($atts['id']);
		if($atts['lang'] && !in_array($atts['lang'], $this->langs)) return false;
		if($atts['output'] && !in_array($atts['output'], $this->outputs)) return false;

		$yes = array('true', 'yes', '1');
		foreach(array('simple', 'imap') as $att) {
			if($atts[$att] && in_array(strtolower($atts[$att]), $yes)) $atts[$att] = true;
			else $atts[$att] = false;
		}
		require_once(dirname( __FILE__ )."/tfo-graphviz-{$this->methods[$this->options['method']]}.php" );
		$gv_object = new $this->options['method']($dot, $atts, WP_CONTENT_DIR.'/tfo-graphviz', WP_CONTENT_URL.'/tfo-graphviz');
		if(!$gv_object) {
			print "argh!";
			return false;
		}
		if(isset($this->options['wrapper'])) $gv_object->wrapper($this->options['wrapper']);

		// Force generation of the image etc
		//$gv_object->url();

		return $gv_object;
	}
}

if(is_admin()) {
	require(dirname(__FILE__).'/tfo-graphviz-admin.php');
	$tfo_graphviz = new TFO_Graphviz_Admin;
	register_activation_hook(__FILE__, array(&$tfo_graphviz, 'activation_hook'));
} else {
	$tfo_graphviz = new TFO_Graphviz;
}

add_action('init', array( &$tfo_graphviz, 'init'));
?>
