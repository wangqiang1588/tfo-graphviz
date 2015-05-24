<?php
/**
 * tfo-graphviz.php
 *
 * @package tfo-graphviz
 *
 * TFO-Graphviz WordPress plugin
 * Copyright (C) 2010 Chris Luke <chrisy@flirble.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */


// $Id$
/*
Plugin Name: TFO Graphviz
Plugin URI: http://blog.flirble.org/projects/graphviz
Description: Converts inline DOT code into an image, with optional image map, using Graphviz.
Version: 1.10
Author: Chris Luke
Author URI: http://blog.flirble.org/
Copyright: Chris Luke
Copyrught: The Flirble Organisation
License: GPL2+
*/

if (!defined('ABSPATH')) exit;

@define('TFO_GV_DEBUG', false);

class TFO_Graphviz {
	var $options;
	var $methods = array(
		'TFO_Graphviz_Graphviz' => 'graphviz',
		'TFO_Graphviz_Remote' => 'remote',
		'TFO_Graphviz_PHP' => 'php'
	);
	var $method_label = array(
		'graphviz' => 'Local Graphviz installation using the <code>dot</code> Graphviz binary (recommended)',
		'remote' => 'Remote Graphviz server over HTTP (not implemented) (easiest)',
		'php' => 'Local PHP bindings for Graphviz (eg, <code>libgv-php5</code> on Debian/Ubuntu) (fastest)',
	);
	var $langs = array('dot', 'neato', 'twopi', 'circo', 'fdp');
	var $outputs = array('gif', 'png', 'jpg');
	var $count, $err;


	/**
	 * Basic initialization.
	 */
	function init() {
		$this->options = get_option('tfo-graphviz');
		$this->count = 1;

		@define('TFO_GRAPHVIZ_CONTENT', 'tfo-graphviz');
		@define('TFO_GRAPHVIZ_CONTENT_DIR', WP_CONTENT_DIR.'/'.TFO_GRAPHVIZ_CONTENT);
		@define('TFO_GRAPHVIZ_CONTENT_URL', WP_CONTENT_URL.'/'.TFO_GRAPHVIZ_CONTENT);
		@define('TFO_GRAPHVIZ_GRAPHVIZ_PATH', $this->options['graphviz_path']);
		@define('TFO_GRAPHVIZ_MAXAGE', $this->options['maxage']);

		add_action('wp_head', array(&$this, 'wp_head'));
		add_shortcode('graphviz', array(&$this, 'shortcode'));

		register_shutdown_function(array(&$this, 'cleanup_content_dir'));
	}


	/**
	 * Remove old files from the content directory.
	 */
	function cleanup_content_dir() {
		if (TFO_GRAPHVIZ_MAXAGE && TFO_GRAPHVIZ_MAXAGE > 0 && is_dir(TFO_GRAPHVIZ_CONTENT_DIR)) {
			$zaplist = array();
			if ($dh = @opendir(TFO_GRAPHVIZ_CONTENT_DIR)) {
				while (($fname = readdir($dh)) !== false) {
					$file = TFO_GRAPHVIZ_CONTENT_DIR.'/'.$fname;
					if (!is_file($file)) continue;

					$st = @stat($file);
					if (!$st) continue;

					if ((time() - $st['mtime']) > (TFO_GRAPHVIZ_MAXAGE * 24*60*60)) {
						array_push($zaplist, $file);
					}
				}
				closedir($dh);
			}
			foreach ($zaplist as $fname) {
				@unlink($fname);
			}
		}
	}


	/**
	 * Renders HTML output for the page head section.
	 */
	function wp_head() {
		if (empty($this->options['css']))
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


	/**
	 * Shortcode -> <img> markup.  Creates images as necessary.
	 *
	 * @param hash    $_atts Attributes given in the shortcode element.
	 * @param string  $dot   The DOT contents from inside the shortcode section.
	 * @return string The rendered HTML that the shortcode section is replaced with.
	 */
	function shortcode($_atts, $dot) {
		$atts = shortcode_atts(array(
				'id' => 'tfo_graphviz_'.($this->count++),
				'lang' => 'dot',
				'simple' => false,
				'digraph' => false,
				'graph' => false,
				'output' => 'png',
				'imap' => false,
				'href' => false,
				'title' => '',
				'remote_key' => $this->options['remote_key'],
			), $_atts);

		if (TFO_GV_DEBUG) file_put_contents(sys_get_temp_dir() . "/pre", $dot);

		$dot = preg_replace(array('#<br\s*/?>#i', '#</?p>#i'), ' ', $dot);

		$dot = str_replace(
			array('&lt;', '&gt;', '&quot;', '&#8220;', '&#8221;', '&#039;', '&#8125;', '&#8127;', '&#8217;', '&#038;', '&amp;', "\r", "\xa0", '&#8211;'),
			array('<',    '>',    '"',      '"',       '"',       "'",      "'",       "'",       "'",       '&',      '&',     '',   '-',    ''),
			$dot
		);

		if ($atts['simple']) { // emulate eht-graphviz
			$dot = "digraph ".$atts['id']." {\n$dot\n}\n";
		} elseif ($atts['digraph']) {
			$dot = "digraph ".$atts['id']." {\n$dot\n}\n";
		} elseif ($atts['graph']) {
			$dot = "graph ".$atts['id']." {\n$dot\n}\n";
		}

		if (TFO_GV_DEBUG) file_put_contents(sys_get_temp_dir() . "/post", $dot);

		$gv = $this->graphviz($dot, $atts);
		if (!$gv) {
			$e = "Graphviz generation failed";
			if ($this->err) $e .= ': '.$this->err;
			else $e .= '.';
			return $e;
		}

		$url = false;
		try {
			$url = $gv->url();
		} catch (Exception $e) {
			$e = "Graphviz generation failed";
			if ($this->err) $e .= ': '.$this->err;
			else $e .= '.';
			return $e;
		}
		if (!is_wp_error($url)) {
			$url = esc_url($url);
			$href = $gv->href;
			if ($href) {
				if (strtolower($href) == 'self') $href = $url;
				else $href = clean_url($href);
			}
			$alt = esc_attr($gv->title);
			$ret = "<img src=\"$url\" class=\"graphviz\"";
			if (!empty($alt)) $ret .= " alt=\"$alt\" title=\"$alt\"";
			if (!empty($gv->imap)) $ret .= " usemap=\"#$gv->id\"";
			$ret .= " />";
			if (!empty($href)) $ret = "<a href=\"".$href."\">$ret</a>";
			if (!empty($gv->imap)) $ret .= "\n$gv->imap";

		} else {
			$ret = "<p><b>Error generating Graphviz image</b></p>\n";
			$ret .= "<pre>";
			$ret .= $url->get_error_message();
			$d = $url->get_error_data();
			if ($d) {
				$ret .= "\n";
				$ret .= $url->get_error_data();
			}
			$ret .= "</pre>\n";
		}

		return $ret;
	}


	/**
	 * Invokes the selected Graphviz method.
	 *
	 * @param string  $dot  The dot source text to use.
	 * @param string  $atts Attributes that control the generation.
	 * @return bool True on success, False otherwise.
	 */
	function &graphviz($dot, $atts) {
		$this->err = false;
		if (empty($this->methods[$this->options['method']])) {
			$this->err = 'Unknown method "'.$this->options['method'].'"';
			return false;
		}

		// Validate atts
		$atts['id'] = attribute_escape($atts['id']);
		if ($atts['lang'] && !in_array($atts['lang'], $this->langs)) {
			$this->err = "Unknown lang: ".$atts['lang'];
			return false;
		}
		if ($atts['output'] && !in_array($atts['output'], $this->outputs)) {
			$this->err = "Unknown output: ".$atts['output'];
			return false;
		}

		$yes = array('true', 'yes', '1');
		foreach (array('simple', 'imap') as $att) {
			if ($atts[$att] && in_array(strtolower($atts[$att]), $yes)) $atts[$att] = true;
			else $atts[$att] = false;
		}

		if (!isset($atts['remote_key'])) $atts['remote_key'] = $this->options['remote_key'];

		if (!tfo_mkdir_p(TFO_GRAPHVIZ_CONTENT_DIR)) {
			$this->err = "Directory <code>".TFO_GRAPHVIZ_CONTENT_DIR."</code> is either not writable, not a directory or not creatable";
			return false;
		}

		$gv_method = $this->options['method'];
		require_once dirname( __FILE__ ).'/tfo-graphviz-'.$this->methods[$gv_method].'.php';
		$gv_object = new $gv_method($dot, $atts, TFO_GRAPHVIZ_CONTENT_DIR, TFO_GRAPHVIZ_CONTENT_URL);
		if (!$gv_object) {
			$this->err = "Unable to create Graphviz renderer, check your plugin settings";
			return false;
		}

		return $gv_object;
	}


}


if (!function_exists('tfo_mkdir_p')) :


	/**
	 * Simple helper to mkdir a directory and all missing parent directories.
	 *
	 * @param string  $target Directory to mkdir.
	 * @return bool True on success, False otherwise.
	 */
	function tfo_mkdir_p($target) {
		// modified from php.net/mkdir user contributed notes
		if (file_exists($target)) {
			if (!@is_dir($target) || !@is_writable($target))
				return false;
			else
				return true;
		}

		// Attempting to create the directory may clutter up our display.
		if (@mkdir($target)) {
			$stat = @stat(dirname($target));
			$dir_perms = $stat['mode'] & 0007777;  // Get the permission bits.
			@chmod($target, $dir_perms);
			return true;
		} else {
			if (is_dir(dirname($target)))
				return false;
		}

		// If the above failed, attempt to create the parent node, then try again.
		if (tfo_mkdir_p(dirname($target)))
			return tfo_mkdir_p($target);

		return false;
	}


endif;

if (is_admin()) {
	require dirname(__FILE__).'/tfo-graphviz-admin.php';
	$tfo_graphviz = new TFO_Graphviz_Admin;
	register_activation_hook(__FILE__, array(&$tfo_graphviz, 'activation_hook'));
} else {
	$tfo_graphviz = new TFO_Graphviz;
}

add_action('init', array( &$tfo_graphviz, 'init'));
?>
