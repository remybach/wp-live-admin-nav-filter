<?php
/*
Plugin Name: Live Admin Nav Filter
Plugin URI: https://github.com/remybach/wp-live-admin-nav-filter
Description: Add a search box to the admin nav that allows you to more easily find that menu item you can't see at a glance.
Version: 1.0
Author: Rémy Bach
Author URI: http://remy.bach.me.uk
License: http://remybach.mit-license.org/
*/

class LiveAdminNavFilter {

	private $sections;
	private $checkboxes;
	private $settings;

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {
		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( &$this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'register_admin_scripts' ) );
		add_action( 'admin_head', array( &$this, 'admin_css_js' ) );

		// Settings menu
		$this->checkboxes = array();
		$this->settings = array();
		$this->get_settings();
		$this->sections['position'] = __( 'Position Settings' );
		$this->sections['colour'] = __( 'Colour Settings' );
		add_action( 'admin_menu', array( &$this, 'add_pages' ) );
		add_action( 'admin_init', array( &$this, 'register_settings' ) );

		// Initialize our settings page
		if ( ! get_option( 'lnf-options' ) ) {
			$this->initialize_settings();
		}

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
	} // end constructor

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {
		wp_register_style( 'live-admin-nav-filter-admin-styles', plugins_url( 'live-admin-nav-filter/css/admin.css' ) );
		wp_enqueue_style( 'live-admin-nav-filter-admin-styles' );
	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {
		wp_register_script( 'live-admin-nav-filter-admin-script', plugins_url( 'live-admin-nav-filter/js/admin.js' ) );
		wp_enqueue_script( 'live-admin-nav-filter-admin-script' );
	} // end register_admin_scripts

	public function admin_css_js() {
		$options = get_option('lnf-options');

		if ( !empty( $options ) ) {
			echo '<style type="text/css">';
				echo '.lnf-highlight { background:'.$options['highlight_colour'].'; }';
				echo '.lnf-focus, #adminmenu .lnf-focus .wp-submenu { border:2px solid '.$options['border_colour'].'; }';
			echo '</style>';

			echo '<script type="text/javascript">';
			echo '	var LNF_POSITION = "'.( !empty($options['position']) ? $options['position'] : 'bottom' ).'";';
			echo '	var LNF_HIDDEN = '.( !empty($options['hidden']) ? $options['hidden'] : 'false' ).';';
			echo '</script>';
		}
	} // end admin_css_js

	/**
	 * Add our settings menu.
	 */
	/* Add page(s) to the admin menu */
	public function add_pages() {
		$admin_page = add_options_page( 'Live Admin Nav Filter', 'Live Admin Nav Filter', 'manage_options', 'lnf-options', array( &$this, 'display_page' ) );
	} // end add_pages

	/* HTML to display the settings page */
	public function display_page() {
		echo '<div class="wrap">
		<div class="icon32" id="icon-options-general"></div>
		<h2>' . __( 'Live Admin Nav Filter' ) . '</h2>
		<form action="options.php" method="post">
			';
			settings_fields( 'lnf-options' );
			do_settings_sections( $_GET['page'] );
			echo '<p class="submit"><input name="Submit" type="submit" class="button-primary" value="' . __( 'Save Changes' ) . '" /></p>
		</form>

		<h3>' . __( 'Usage' ) . '</h3>

		<ul>
			<li>
				Hitting <code>\'/\'</code> while not in a text area or input field will let you quickly begin searching.
			</li>
			<li>
				While results are displayed, press the <code>up</code> and <code>down</code> keys to cycle through your results.
			</li>
			<li>
				When you\'re happy with your selection, hit the <code>enter</code> key to go to it.
			</li>
		</ul>

		<small class="credit">
			Lovingly crafted by <a href="http://remy.bach.me.uk/" target="_blank">Rémy Bach</a>.<br>
			Should you feel like throwing any<br>
			money my way, please do so below:
		</small>
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
		">
		<input type="image" src="https://www.paypalobjects.com/en_GB/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal — The safer, easier way to pay online.">
		<img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
		</form>';
	} // end display_page

	/* Define all settings and their defaults */
	public function get_settings() {
		// Position Settings
		$this->settings['position'] = array(
			'title'   => __( 'Position' ),
			'desc'    => __( 'Choose whether the filter field goes at the top, or the bottom of the menu.' ),
			'std'     => 'bottom',
			'type'    => 'select',
			'choices' => array( 'bottom'=>'Bottom', 'top'=>'Top' ),
			'section' => 'position'
		);
		$this->settings['hidden'] = array(
			'title'   => __( 'Hidden' ),
			'desc'    => __( 'Choose whether the filter field is hidden until a "/" is typed.' ),
			'std'     => 0,
			'type'    => 'select',
			'choices' => array( 0=>'No', 1=>'Yes' ),
			'section' => 'position'
		);

		// Colour Settings
		$this->settings['border_colour'] = array(
			'title'   => __( 'Border Colour' ),
			'desc'    => __( 'Choose a colour for the border around navigation elements that contain matches.' ),
			'std'     => '#FFED2B',
			'type'    => 'text',
			'section' => 'colour'
		);
		$this->settings['highlight_colour'] = array(
			'title'   => __( 'Highlight Colour' ),
			'desc'    => __( 'Choose a colour that will highlight your matched search.' ),
			'std'     => '#FFED2B',
			'type'    => 'text',
			'section' => 'colour'
		);
	} // end get_settings

	/* Initialize settings to their default values */
	public function initialize_settings() {
		$default_settings = array();
		foreach ( $this->settings as $id => $setting ) {
			if ( $setting['type'] != 'heading' )
				$default_settings[$id] = $setting['std'];
		}

		update_option( 'lnf-options', $default_settings );
	} // end initialize_settings

	/* Register settings via the WP Settings API */
	public function register_settings() {
		register_setting( 'lnf-options', 'lnf-options', array ( &$this, 'validate_settings' ) );

		foreach ( $this->sections as $slug => $title ) {
			add_settings_section( $slug, $title, array( &$this, 'display_section' ), 'lnf-options' );
		}

		$this->get_settings();

		foreach ( $this->settings as $id => $setting ) {
			$setting['id'] = $id;
			$this->create_setting( $setting );
		}
	} // end register_settings

	/* Description for section */
	public function display_section() {
		// code
	} // end display_section

	public function create_setting( $args = array() ) {
		$defaults = array(
			'id'      => 'default_field',
			'title'   => 'Default Field',
			'desc'    => 'This is a default description.',
			'std'     => '',
			'type'    => 'text',
			'section' => 'general',
			'choices' => array(),
			'class'   => ''
		);

		extract( wp_parse_args( $args, $defaults ) );

		$field_args = array(
			'type'      => $type,
			'id'        => $id,
			'desc'      => $desc,
			'std'       => $std,
			'choices'   => $choices,
			'label_for' => $id,
			'class'     => $class
		);

		if ( $type == 'checkbox' ) {
			$this->checkboxes[] = $id;
		}

		add_settings_field( $id, $title, array( $this, 'display_setting' ), 'lnf-options', $section, $field_args );
	} // end create_setting

	/* HTML output for individual settings */
	public function display_setting( $args = array() ) {
		extract( $args );

		$options = get_option( 'lnf-options' );

		if ( ! isset( $options[$id] ) && $type != 'checkbox' ) {
			$options[$id] = $std;
		} else if ( ! isset( $options[$id] ) ) {
			$options[$id] = 0;
		}

		$field_class = '';
		if ( $class != '' ) {
			$field_class = ' ' . $class;
		}

		switch ( $type ) {

			case 'heading':
				echo '</td></tr><tr valign="top"><td colspan="2"><h4>' . $desc . '</h4>';
				break;

			case 'checkbox':

				echo '<input class="checkbox' . $field_class . '" type="checkbox" id="' . $id . '" name="lnf-options[' . $id . ']" value="1" ' . checked( $options[$id], 1, false ) . ' /> <label for="' . $id . '">' . $desc . '</label>';

				break;

			case 'select':
				echo '<select class="select' . $field_class . '" name="lnf-options[' . $id . ']">';

				foreach ( $choices as $value => $label ) {
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $options[$id], $value, false ) . '>' . $label . '</option>';
				}

				echo '</select>';

				if ( $desc != '' ) {
					echo '<br /><span class="description">' . $desc . '</span>';
				}

				break;

			case 'radio':
				$i = 0;
				foreach ( $choices as $value => $label ) {
					echo '<input class="radio' . $field_class . '" type="radio" name="lnf-options[' . $id . ']" id="' . $id . $i . '" value="' . esc_attr( $value ) . '" ' . checked( $options[$id], $value, false ) . '> <label for="' . $id . $i . '">' . $label . '</label>';
					if ( $i < count( $options ) - 1 ) {
						echo '<br />';
					}
					$i++;
				}

				if ( $desc != '' ) {
					echo '<br /><span class="description">' . $desc . '</span>';
				}

				break;

			case 'textarea':
				echo '<textarea class="' . $field_class . '" id="' . $id . '" name="lnf-options[' . $id . ']" placeholder="' . $std . '" rows="5" cols="30">' . wp_htmledit_pre( $options[$id] ) . '</textarea>';

				if ( $desc != '' ) {
					echo '<br /><span class="description">' . $desc . '</span>';
				}

				break;

			case 'password':
				echo '<input class="regular-text' . $field_class . '" type="password" id="' . $id . '" name="lnf-options[' . $id . ']" value="' . esc_attr( $options[$id] ) . '" />';

				if ( $desc != '' ) {
					echo '<br /><span class="description">' . $desc . '</span>';
				}

				break;

			case 'text':
			default:
		 		echo '<input class="regular-text' . $field_class . '" type="text" id="' . $id . '" name="lnf-options[' . $id . ']" placeholder="' . $std . '" value="' . esc_attr( $options[$id] ) . '" />';

		 		if ( $desc != '' ) {
		 			echo '<br /><span class="description">' . $desc . '</span>';
		 		}

		 		break;
		}
	} // end display_setting

	/**
	 * Validate our input fields
	 */
	public function validate_settings( $input ) {
		$options = get_option( 'lnf-options' );

		foreach ( $this->checkboxes as $id ) {
			if ( isset( $options[$id] ) && ! isset( $input[$id] ) )
				unset( $options[$id] );
		}

		foreach ($input as $key => $val) {
			// Match hex codes only.
			if ( preg_match('/colour/', $key) && !preg_match( '/^#[a-zA-Z0-9]{6}$/', $val ) ) {
				return false;
			}
		}

		return $input;
	} // end validate_settings

} // end class

new LiveAdminNavFilter();