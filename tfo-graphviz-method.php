<?php
/**
 * tfo-graphviz-method.php
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
