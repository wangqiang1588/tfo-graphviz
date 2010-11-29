<?php

if (!defined('ABSPATH')) exit;

class TFO_Graphviz_Admin extends TFO_Graphviz {
	var $errors;

	function init() {
		parent::init();
		$this->errors = new WP_Error;

		add_action('admin_menu', array(&$this, 'admin_menu'));
	}

	function admin_menu() {
		$hook = add_options_page('TFO Graphviz', 'TFO Graphviz', 'manage_options', 'tfo-graphviz', array(&$this, 'admin_page'));
		add_action("load-$hook", array( &$this, 'admin_page_load' ) );

		if(!tfo_mkdir_p(TFO_GRAPHVIZ_CONTENT_DIR)) // will attempt to create if doesn't exist
			add_action('admin_notices', array(&$this, 'not_writeable_error'));
		if(!empty($this->options['activated'])) {
			add_action('admin_notices', array(&$this, 'activated_notice'));
			unset($this->options['activated']);
			update_option('tfo-graphviz', $this->options);
		}

		add_filter('plugin_action_links_'.plugin_basename(dirname(__FILE__).'/tfo-graphviz.php'), array(&$this, 'plugin_action_links'));
	}

	function not_writeable_error() {
?>
	<div id="tfo-graphviz-chmod" class="error fade"><p><?php printf(
		__('<code>%s</code> must be writeable for TFO Graphviz to work.'),
		esc_html(TFO_GRAPHVIZ_CONTENT_DIR)
	); ?></p></div>
<?php
	}

	function activated_notice() {
?>
	<div id="tfo-graphviz-config" class="updated fade"><p><?php printf(
		__('Make sure to check your <a href="%s">TFO Graphviz Settings</a>.'),
		esc_url(admin_url('options-general.php?page=tfo-graphviz'))
	); ?></p></div>
<?php
	}
	function plugin_action_links($links) {
		array_unshift($links, '<a href="options-general.php?page=tfo-graphviz">'.__('Settings')."</a>");
		return $links;
	}

	function admin_page_load() {
		if(!current_user_can('manage_options'))
			wp_die(__('You need more special-sauce to manage TFO Graphviz.', 'tfo-graphviz'));

		add_action('admin_head', array(&$this, 'admin_head'));

		if(empty($_POST['tfo-graphviz'])) {
			return;
		}

		check_admin_referer('tfo-graphviz');

		if($this->update(stripslashes_deep($_POST['tfo-graphviz']))) {
			wp_redirect(add_query_arg('updated', '', wp_get_referer()));
			exit;
		}
	}

	function update($new) {
		if(!is_array($this->options))
			$this->options = array();
		extract($this->options, EXTR_SKIP);

		if (isset($new['method'])) {
			if(empty($this->methods[$new['method']])) {
				$this->errors->add('method', __( 'Invalid Graphviz generation method', 'tfo-graphviz' ), $new['method'] );
			} else {
				$method = $new['method'];
			}
		}

		if ( isset( $new['css'] ) ) {
			$css = str_replace( array( "\n", "\r" ), "\n", $new['css'] );
			$css = trim( preg_replace( '/[\n]+/', "\n", $css ) );
		}

		if ( isset( $new['wrapper'] ) ) {
			$wrapper = str_replace( array("\n", "\r"), "\n", $new['wrapper'] );
			if ( !$wrapper = trim( preg_replace('/[\n]+/', "\n", $new['wrapper'] ) ) )
				$wrapper = false;

		}

		if ( isset( $new['graphviz_path'] ) ) {
			$new['graphviz_path'] = trim( $new['graphviz_path'] );
			if ( ( !$new['graphviz_path'] || !file_exists( $new['graphviz_path'] ) ) && 'TFO_Graphviz_Remote' != $method )
				$this->errors->add( 'graphviz_path', __( '<code>graphviz</code> path not found.', 'tfo-graphviz' ), $new['graphviz_path'] );
			else
				$graphviz_path = $new['graphviz_path'];
		}

		if ( isset( $new['convert_path'] ) ) {
			$new['convert_path'] = trim( $new['convert_path'] );
			if ( ( !$new['convert_path'] || !file_exists( $new['convert_path'] ) ) && 'unused' == $method )
				$this->errors->add( 'convert_path', __( '<code>convert</code> path not found.', 'tfo-graphviz' ), $new['convert_path'] );
			else
				$convert_path = $new['convert_path'];
		}

		if(isset($new['maxage'])) {
			$maxage = trim($new['maxage']) + 0;
		}

		$this->options = compact('css', 'graphviz_path', 'convert_path', 'wrapper', 'method', 'maxage');
		update_option('tfo-graphviz', $this->options);
		return !count($this->errors->get_error_codes());
	}

	// Attempts to use current settings to generate a temporory image (new with every page load)
	function test_image() {
		if ( 'TFO_Graphviz_Remote' != $this->options['method'] && !is_writable( TFO_GRAPHVIZ_CONTENT_DIR ) )
			return false;

		if ( is_array( $this->options ) )
			extract( $this->options, EXTR_SKIP );

		if ('TFO_Graphviz_Graphviz' == $method && (!$graphviz_path))
			return;

		@unlink( TFO_GRAPHVIZ_CONTENT_DIR.'/test.png' );

		$graphviz_object = $this->graphviz('digraph test { a1 -> a2 -> a3 -> a1; }', array(
			'id' => 'test',
			'lang' => 'dot',
			'simple' => 'false',
			'output' => 'png',
			'imap' => false
		));
		if(!$graphviz_object) {
			return false;
		}

		if ( !empty( $wrapper ) )
			$graphviz_object->wrapper( $wrapper );

		$message = '';

		$r = false;

		$url = $graphviz_object->url();
		if ( !empty( $graphviz_object->tmp_file ) )
			exec( 'mv ' . escapeshellarg( "$graphviz_object->tmp_file.log" ) . ' ' . TFO_GRAPHVIZ_CONTENT_DIR.'/test.log' );

		if(is_wp_error($url)) {
			$code = $url->get_error_code();
			if ( false !== strpos( $code, '_exec' ) ) :
				$message = "<div class='error'>\n";
				$exec = $url->get_error_data( $code );
				exec( $exec, $out, $r );
				$message .= "<h4>Command run:</h4>\n";
				$message .= "<div class='pre'><code>$exec</code></div>\n";
				$out = preg_replace( '/tex_.+?\.log/i', '<strong><a href="' . clean_url( content_url( TFO_GRAPHVIZ_CONTENT.'/test.log' ) ) . '">test.log</a></strong>', join("\n", $out));
				$message .= "<h4>Result:</h4>\n";
				$message .= "<div class='pre'><code>$out</code></div>\n";
				$message .= "<p>Exit code: $r</p>\n";
				$message .= "</div>";
			else :
				$message = '<div class="error"><p>' . $url->get_error_message() . "</p></div>\n";
			endif;
			echo $message;
		} else {
			if ( !empty( $graphviz_object->file ) ) {
				exec('mv ' . escapeshellarg("$graphviz_object->file" ) . ' ' . TFO_GRAPHVIZ_CONTENT_DIR.'/test.png');
				$url = content_url( TFO_GRAPHVIZ_CONTENT.'/test.png' ) . "?" . mt_rand();
			}
			@unlink(TFO_GRAPHVIZ_CONTENT_DIR.'/test.log');
			$alt = attribute_escape( __( 'Test Image', 'tfo-graphviz' ) );
			echo "<img class='test-image' src='" . clean_url( $url ) . "' alt='$alt' />\n";
			echo "<p class='test-image'>" . __( 'If you can see a graph then all is well.', 'tfo-graphviz' ) . '</p>';
			$r = true;
		}
		return $r;
	}

	function admin_head() {
		$current_method = $this->methods[$this->options['method']] ? $this->methods[$this->options['method']] : 'graphviz';
?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery( function($) {
	$( '#tfo-graphviz-method-switch :radio' ).change( function() {
		$( '.tfo-graphviz-method' ).hide().css( 'background-color', '' );
		$( '.' + this.id ).show().css( 'background-color', '#ffffcc' );
	} );
} );
/* ]]> */
</script>
<style type="text/css">
/* <![CDATA[ */
p.test-image {
	text-align: center;
	font-size: 1.4em;
}
img.test-image {
	display: block;
	margin: 0 auto 1em;
}
.syntax p {
	margin-top: 0;
}
.syntax code {
	white-space: nowrap;
}
.tfo-graphviz-method {
	display: none;
}
tr.tfo-graphviz-method-<?php echo $current_method; ?> {
	display: block;
}
tr.tfo-graphviz-method-<?php echo $current_method; ?> {
	display: table-row;
}
/* ]]> */
</style>
<?php
	}

	function admin_page() {
		if(!current_user_can( 'manage_options'))
			wp_die(__('You need more special-sauce to manage TFO Graphviz.', 'tfo-graphviz'));

		$available_methods = array();	
		$default_wrappers = array();
		foreach ( $this->methods as $class => $method ) {
			$included = include_once(dirname(__FILE__)."/tfo-graphviz-$method.php" );
			if($included === FALSE) // module didn't load, or indicated it was not useable
				continue;
			$available_methods[$class] = $method;
			if('TFO_Graphviz_Remote' == $class)
				continue;
			$graphviz_object = new $class('a->b;', array(id=>'admin',simple=>true));
			$default_wrappers[$method] = $graphviz_object->wrapper();
		}
		unset( $class, $method, $graphviz_object );

		if(sizeof($available_methods) == 0 || !$available_methods['TFO_Graphviz_Graphviz']) { // eek!
			$available_methods['TFO_Graphviz_Graphviz'] = $this->methods['TFO_Graphviz_Graphviz'];
		}

		if(!is_array($this->options))
			$this->options = array();

		$values = $this->options;

		$errors = array();
		if($errors = $this->errors->get_error_codes()) :
			foreach ( $errors as $e )
				$values[$e] = $this->errors->get_error_data( $e );
	?>
	<div id='graphviz-config-errors' class='error'>
		<ul>
		<?php foreach ( $this->errors->get_error_messages() as $m ) : ?>
			<li><?php echo $m; ?></li>
		<?php endforeach; ?>
		</ul>
	</div>
	<?php	endif; ?>

	<div class='wrap'>
	<h2><?php _e( 'TFO Graphviz Options', 'tfo-graphviz' ); ?></h2>

	<?php if ( empty( $errors ) ) $this->test_image(); ?>

	<form action="<?php echo clean_url( remove_query_arg( 'updated' ) ); ?>" method="post">

	<table class="form-table">
	<tbody>
		<?php if ( empty( $errors ) ): ?>
		<tr>
			<th scope="row"><?php _e( 'Syntax' ); ?></th>
			<td class="syntax">
				<p><?php printf( __( 'You may use either the shortcode syntax %s<br />to insert graphs into your posts.', 'tfo-graphviz' ),
					'<code>[graphviz]digraph test { a0 -> a1 -> a0; }[/graphviz]</code>'
				); ?></p>
				<p><?php _e( 'For more information, see the <a href="http://blog.flirble.org/projects/tfo-graphviz/">FAQ</a>' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
		<tr<?php if ( in_array( 'method', $errors ) ) echo ' class="form-invalid"'; ?>>
			<th scope="row"><?php _e( 'Graphviz generation method', 'tfo-graphviz' ); ?></th>
			<td class="syntax">
				<p>Only available methods will be shown. If you have Graphviz installed locally and no options are shown, make sure the
				"<code>graphviz</code> path" option below is set correctly.</p>
				<ul id="tfo-graphviz-method-switch">
				<?php
				$mcount = 0;
				foreach($available_methods as $class => $method) {
					?><li><label for="tfo-graphviz-method-<?php echo $method;?>"><input type="radio" name="tfo-graphviz[method]" id="tfo-graphviz-method-<?php echo $method;?>" value='<?php echo $class;?>'<?php checked($class, $values['method']); ?> /> <?php _e($this->method_label[$method], 'tfo-graphviz'); ?></label></li><?php
				}
				?></ul>
			</td>
		</tr>

		<tr class="tfo-graphviz-path<?php if ( in_array( 'graphviz_path', $errors ) ) echo ' form-invalid'; ?>">
			<th scope="row"><label for="tfo-graphviz-graphviz-path"><?php _e( '<code>graphviz</code> path' ); ?></label></th>
			<td><input type='text' name='tfo-graphviz[graphviz_path]' value='<?php echo attribute_escape( $values['graphviz_path'] ); ?>' id='tfo-graphviz-graphviz-path' /><?php
				if ( !$this->options['graphviz_path'] ) {
					$guess_graphviz_path = trim( @exec( 'which dot' ) );
					if ( $guess_graphviz_path && file_exists( $guess_graphviz_path ) )
						printf( ' ' . _c( 'Try: <code>%s</code>|Try: guess_graphviz_path', 'tfo-graphviz' ), $guess_graphviz_path );
					else
						echo ' ' . __( 'Not found.  Enter full path to a Graphviz binary, for example <code>/usr/bin/dot</code>, or choose another Graphwiz generation method.', 'tfo-graphviz' );
				}
			?></td>
		</tr>

		<tr class="tfo-graphviz-maxage<?php if ( in_array( 'graphviz_maxage', $errors ) ) echo ' form-invalid'; ?>">
			<th scope="row"><label for="tfo-graphviz-maxage"><?php _e('Maximum age, in days, of generated content (enter <em>0</em> to disable expiration)'); ?></label></th>
			<td><input type='text' name='tfo-graphviz[maxage]' value='<?php echo attribute_escape( $values['maxage'] ); ?>' id='tfo-graphviz-maxage' /></td>
		</tr>

<!--
		<tr class="tfo-graphviz-method tfo-graphviz-method-graphviz<?php if ( in_array( 'wrapper', $errors ) ) echo ' form-invalid	'; ?>">
			<th scope="row"><label for="tfo-graphviz-wrapper"><?php _e( 'DOT Preamble', 'tfo-graphviz' ); ?></label></th>
			<td>
				<textarea name='tfo-graphviz[wrapper]' rows='8' cols="50" id='tfo-graphviz-wrapper'><?php echo wp_specialchars( $values['wrapper'] ); ?></textarea>
			</td>
		</tr>
	<?php foreach ( $default_wrappers as $method => $default_wrapper ) : ?>
		<tr class="tfo-graphviz-method tfo-graphviz-method-<?php echo $method; ?>">
			<th></th>
			<td>
				<h4>Leaving the above blank will use the following default preamble.</h4>
				<div class="pre"><code><?php echo $default_wrapper; ?></code></div>
			</td>
		</tr>
	<?php endforeach; ?>
-->
	</tbody>
	</table>


	<p class="submit">
		<input type="submit" class="button-primary" value="<?php echo attribute_escape( __( 'Update TFO Graphviz Options', 'tfo-graphviz' ) ); ?>" />
		<?php wp_nonce_field( 'tfo-graphviz' ); ?>
	</p>
	</form>
	</div>
	<?php
	}

	// Sets up default options
	function activation_hook() {
		if ( is_array( $this->options ) )
			extract( $this->options, EXTR_SKIP );

		global $themecolors;

		if ( empty( $method ) )
			$method = 'TFO_Graphviz_Graphviz';

		if ( empty( $css ) )
			$css = 'img.graphviz { vertical-align: middle; border: none; }';

		if ( empty( $graphviz_path ) )
			$graphviz_path = trim( @exec( 'which dot' ) );
		if ( empty( $convert_path ) )
			$convert_path = trim( @exec( 'which convert' ) );

		$graphviz_path = $graphviz_path && @file_exists($graphviz_path) ? $graphviz_path : false;
		$convert_path = $convert_path && @file_exists( $convert_path ) ? $convert_path : false;

		if ( empty( $wrapper ) )
			$wrapper = false;

		if(empty($maxage))
			$maxage = 30;

		$activated = true;

		$this->options = compact('method', 'css', 'graphviz_path', 'convert_path', 'wrapper', 'maxage', 'activated' );
		update_option( 'tfo-graphviz', $this->options );
	}
}
