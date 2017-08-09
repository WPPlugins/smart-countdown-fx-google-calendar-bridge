<?php
/*
 * Plugin Name: Smart Countdown FX Google Calendar Bridge
 * Plugin URI: http://smartcalc.es/wp
 * Description: This plugin adds Google Calendar suport to Smart Cowntdown FX.
 * Version: 1.4
 * Author: Alex Polonski
 * Author URI: http://smartcalc.es/wp
 * License: GPL2
 */
defined( 'ABSPATH' ) or die();
final class SmartCountdownGoogleCal_Plugin {
	private static $instance = null;
	public static $options_page_slug = 'scd-google-cal-settings';
	public static $option_prefix = 'scd_google_cal_settings_';
	public static $text_domain = 'smart-countdown-google-cal';
	public static $provider_alias = 'scd_google_cal';
	public static $provider_name;
	public static function get_instance() {
		if( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	private function __construct() {
		require_once ( dirname( __FILE__ ) . '/includes/helper.php' );
		
		load_plugin_textdomain( self::$text_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		add_action( 'admin_init', array(
				$this,
				'register_my_settings' 
		) );
		
		add_action( 'admin_menu', array(
				$this,
				'add_my_menu' 
		) );
		
		add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
				$this,
				'add_plugin_actions' 
		) );
		
		add_filter( 'smartcountdownfx_get_event', array(
				$this,
				'get_current_events' 
		), 10, 2 );
		
		add_filter( 'smartcountdownfx_get_import_configs', array(
				$this,
				'get_configs' 
		) );
		
		self::$provider_name = __( 'Google Calendar', self::$text_domain );
		
		add_action( 'admin_enqueue_scripts', array(
				$this,
				'admin_scripts' 
		) );
	}
	public static function admin_scripts() {
		$plugin_url = plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) );
		
		/*
		 * we will uncomment this block if we decide to use date picker for
		 * recurrence start and end dates
		 * wp_enqueue_script( 'jquery-ui' );
		 * wp_enqueue_script( 'jquery-ui-datepicker' );
		 * wp_register_style( 'jquery-ui-css', $plugin_url . '/admin/jquery-ui.css' );
		 * wp_enqueue_style( 'jquery-ui-css' );
		 * wp_register_style( 'ui-override', $plugin_url . '/admin/ui-override.css' );
		 * wp_enqueue_style( 'ui-override' );
		 */
		
		wp_register_script( self::$provider_alias . '_script', $plugin_url . '/admin/admin.js', array(
				'jquery' 
		) );
		wp_enqueue_script( self::$provider_alias . '_script' );
	}
	public function add_my_menu() {
		add_options_page( __( 'Smart Countdown FX Google Calendar Bridge Settings' ), __( 'Google Calendar Bridge' ), 'administrator', self::$options_page_slug, array(
				$this,
				'add_plugin_options_page' 
		) );
	}
	public function register_my_settings() {
		self::registerSettings( 1 );
		self::registerSettings( 2 );
	}
	public function add_plugin_options_page() {
		?>
<div class="wrap">
	<h2><?php _e( 'Smart Countdown FX Google Calendar Bridge Settings', self::$text_domain ); ?></h2>

	<form method="post" action="options.php">
				<?php settings_fields( self::$options_page_slug ); ?>
				<?php do_settings_sections( self::$options_page_slug ); ?>
				<table class="form-table">
					<?php echo self::displaySettings(1); ?>
				</table>
		<hr />
		<table class="form-table">
					<?php echo self::displaySettings(2); ?>
				</table>
				<?php submit_button(); ?>
			</form>
</div>
<?php
	}
	public function add_plugin_actions( $links ) {
		$new_links = array();
		$new_links[] = '<a href="options-general.php?page=' . self::$options_page_slug . '">' . __( 'Settings' ) . '</a>';
		return array_merge( $new_links, $links );
	}
	public function get_current_events( $instance ) {
		$active_config = $instance['import_config'];
		if( empty( $active_config ) ) {
			return $instance;
		}
		
		$parts = explode( '::', $active_config );
		if( $parts[0] != self::$provider_alias ) {
			return $instance;
		}
		array_shift( $parts );
		
		$configs = array();
		foreach( $parts as $preset_index ) {
			$configs[$preset_index] = self::getOptions( $preset_index );
		}
		
		return SmartCountdownGoogleCal_Helper::getEvents( $instance, $configs );
	}
	public function get_configs( $configs ) {
		return array_merge( $configs, array(
				self::$provider_name => array(
						self::$provider_alias . '::1' => self::getTitle( 1 ),
						self::$provider_alias . '::2' => self::getTitle( 2 ),
						self::$provider_alias . '::1::2' => self::$provider_name . ': ' . __( 'Merge configurations', self::$text_domain ) 
				) 
		) );
	}
	private static function getTitle( $preset_index ) {
		$options = self::getOptions( $preset_index );
		return !empty( $options['title'] ) ? $options['title'] : __( 'Untitled' );
	}
	private static function registerSettings( $preset_index ) {
		register_setting( self::$options_page_slug, self::$option_prefix . $preset_index,  'SmartCountdownGoogleCal_Plugin::validateSettings' . $preset_index );
		register_setting( self::$options_page_slug, self::$option_prefix . 'events_cache_' . $preset_index );
		
		// check if new settings were saved before
		$settings = get_option( self::$option_prefix . $preset_index, false );
		if( !is_array( $settings ) ) {
			// missing new settings, try to transfer settings to new format
			$options = self::getDeprecatedOptions( $preset_index );
			update_option( self::$option_prefix . $preset_index, $options );
			// delete deprecated options
			self::deleteDeprecatedOptions( $preset_index );
		}
	}
	
	public static function validateSettings1( $input ) {
		return self::validateSettings( $input, 1 );
	}
	public static function validateSettings2( $input ) {
		return self::validateSettings( $input, 2 );
	}
	/*
	 * Validate form values and check if we have an uploaded file (oauth p12 key)
	 */
	private static function validateSettings( $input, $preset_index )
	{
		// sanitize title
		if( trim( $input['title'] ) == '' ) {
			$input['title'] = __( 'Untitled' );
		}
		$input['title'] = strip_tags( $input['title'] );
		
		// sanitize CSS parameters
		$input['title_css'] = strip_tags( $input['title_css'] );
		$input['date_css'] = strip_tags( $input['date_css'] );
		$input['location_css'] = strip_tags( $input['location_css'] );
		
		// color_filter will not be set at all if none of the checkboxes
		// are checked, so we set it to empty array here
		if( !isset( $input['color_filter'] ) ) {
			$input['color_filter'] = array();
		}
		
		// api_p12key: check if we have updated key, if not,
		// use the saved one
		$options = self::getOptions( $preset_index );
		$api_p12key = self::uploadKeyFile( $preset_index );
		if( !empty( $api_p12key ) ) {
			$input['api_p12key'] = $api_p12key;
		} else {
			$input['api_p12key'] = $options['api_p12key'];
		}
		
		return $input;
	}
	/*
	 * Precess uploaded p12 key file and return its contents.
	 * This method doesn't create files. p12 key will be stored in
	 * plugin settings.
	 */
	public static function uploadKeyFile( $preset_index ) {
		$files = $_FILES;
		$p12 = '';
		
		if( isset( $files[self::$option_prefix . 'api_p12key_' . $preset_index] ) ) {
			$filedata = $files[self::$option_prefix . 'api_p12key_' . $preset_index];

			if( !empty( $filedata['name'] ) ) {
				// add 'application/octet-stream' to allowed types - mozilla workaround
				$allowed_types = array(
						'application/x-pkcs12',
						'application/octet-stream' 
				);
				
				// a very rough file validation
				if( empty( $filedata['error'] ) && // no error
						$filedata['size'] < 10000 && // limited size (a rough guess)
						in_array( $filedata['type'], $allowed_types ) // allowed mime type
				) {
					// a file is present, read and return it base64-encoded
					$contents = file_get_contents( $filedata['tmp_name'] );
					if( $contents ) {
						$p12 = base64_encode( $contents );
					}
					@unlink( $filedata['tmp_name'] );
				} else {
					// wrong file - add message here!!!
				}
			}
		}
		return $p12;
	}
	
	private static function getOptions( $preset_index ) {
		$options = get_option( self::$option_prefix . $preset_index, array() );
		if( empty( $options ) ) {
			// new version settings were never saved. Fallback to old version settings
			$options = self::getDeprecatedOptions( $preset_index );
		}
		return $options;
	}
	
	private static function getDeprecatedOptions( $preset_index ) {
		$options = array(
				'title' => '',
				'auth' => '',
				'calendar_id' => '',
				'api_key' => '',
				'auth_email' => '',
				'api_p12key' => '',
				'events_cache_time' => 600,
				'all_day_event_start' => '',
				'show_title' => 1,
				'link_title' => 0,
				'show_location' => 0,
				'show_date' => 0,
				'color_filter' => array(), //array( '*' => 'on' ),
				'title_css' => '',
				'date_css' => '',
				'location_css' => ''
		);
		foreach( $options as $key => &$value ) {
			$default = $value;
			$value = get_option( self::$option_prefix . $key . '_' . $preset_index /*, $value */ );
		}
		return $options;
	}
	private static function displaySettings( $preset_index ) {
		$options = self::getOptions( $preset_index );
		
		ob_start();
		?>
<tr>
	<th colspan="2"><h4><?php _e( 'Configuration', self::$text_domain ); ?> <?php echo $preset_index; ?></h4></th>
</tr>
<tr valign="top">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>title_<?php echo $preset_index; ?>"><?php _e( 'Title' ); ?></label>
	</th>
	<td><input type="text" class="regular-text" id="<?php echo self::$option_prefix; ?>title_<?php echo $preset_index; ?>" name="<?php echo self::$option_prefix . $preset_index; ?>[title]" value="<?php echo esc_attr( $options['title'] ); ?>" />
		<p class="description"><?php _e( 'This title will appear in available event import profiles list in Smart Countdown FX configuration', self::$text_domain ); ?></p>
	</td>
</tr>
<tr valign="top">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>auth_<?php echo $preset_index; ?>"><?php _e('Authorization', self::$text_domain); ?></label>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'auth_' . $preset_index, self::$option_prefix . $preset_index . '[auth]', $options['auth'], array(
				'options' => array(
						'' => __( 'Disabled', self::$text_domain ),
						'api_key' => __( 'API Key', self::$text_domain ),
						'oauth' => __( 'OAuth', self::$text_domain ) 
				),
				'type' => 'optgroups',
				'class' => 'scd-er-hide-control' 
		) );
?>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-general">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>calendar_id_<?php echo $preset_index; ?>"><?php _e( 'Calendar ID', self::$text_domain ); ?></label>
	</th>
	<td><input type="text" class="large-text"
		id="<?php echo self::$option_prefix; ?>calendar_id_<?php echo $preset_index; ?>"
		name="<?php echo self::$option_prefix . $preset_index; ?>[calendar_id]"
		value="<?php echo esc_attr( $options['calendar_id'] ); ?>" />
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-api-key">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>api_key_<?php echo $preset_index; ?>"><?php _e( 'API Key', self::$text_domain ); ?></label>
	</th>
	<td><input type="text" class="large-text"
		id="<?php echo self::$option_prefix; ?>api_key_<?php echo $preset_index; ?>"
		name="<?php echo self::$option_prefix . $preset_index; ?>[api_key]"
		value="<?php echo esc_attr( $options['api_key'] ); ?>" />
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-auth-email">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>auth_email_<?php echo $preset_index; ?>"><?php _e( 'OAuth email', self::$text_domain ); ?></label>
	</th>
	<td><input type="text" class="large-text"
		id="<?php echo self::$option_prefix; ?>auth_email_<?php echo $preset_index; ?>"
		name="<?php echo self::$option_prefix . $preset_index; ?>[auth_email]"
		value="<?php echo esc_attr( $options['auth_email'] ); ?>" />
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-api-key-file">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>api_p12key_<?php echo $preset_index; ?>"><?php _e( 'p12 Key File', self::$text_domain ); ?></label>
	</th>
	<td>
		<?php echo SmartCountdownGoogleCal_Helper::apiKeyUploadControl( self::$option_prefix . 'api_p12key_' . $preset_index, self::$option_prefix . 'api_p12key_' . $preset_index, $options['api_p12key'] != '' ); ?>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-general">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>all_day_event_start_<?php echo $preset_index; ?>"><?php _e( 'All-day events start time', self::$text_domain ); ?></label>
	</th>
	<td>
<?php
$time_format = get_option( 'time_format' );	
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'all_day_event_start_' . $preset_index, self::$option_prefix . $preset_index . '[all_day_event_start]', $options['all_day_event_start'], array(
				'options' => array(
						'' => __( 'Discard all-day events', self::$text_domain ),
						'00:00:00' => __( 'Midnight', self::$text_domain ),
						'06:00:00' => date_format( new DateTime( '0000-00-00 06:00:00' ), $time_format),
						'07:00:00' => date_format( new DateTime( '0000-00-00 07:00:00' ), $time_format),
						'08:00:00' => date_format( new DateTime( '0000-00-00 08:00:00' ), $time_format),
						'09:00:00' => date_format( new DateTime( '0000-00-00 09:00:00' ), $time_format),
						'10:00:00' => date_format( new DateTime( '0000-00-00 10:00:00' ), $time_format),
						'11:00:00' => date_format( new DateTime( '0000-00-00 11:00:00' ), $time_format),
						'12:00:00' => date_format( new DateTime( '0000-00-00 12:00:00' ), $time_format),
				),
				'type' => 'optgroups' 
		) );
?>
	<p class="description"><?php _e( 'All-day events is a special case for a countdown. Select "Discard all-day events" to ignore this kind of events or choose an option that suits better for your calendar all-day events start time (midnight is not always the best option)', self::$text_domain ); ?></p>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-general">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>events_cache_time_<?php echo $preset_index; ?>"><?php _e( 'API cache time', self::$text_domain ); ?></label>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'events_cache_time_' . $preset_index, self::$option_prefix . $preset_index . '[events_cache_time]', $options['events_cache_time'], array(
				'options' => array(
						'0' => __( 'No caching', self::$text_domain ),
						'60' => sprintf( _n( '%d minute', '%d minutes', 1, self::$text_domain ), 1 ),
						'300' => sprintf( _n( '%d minute', '%d minutes', 5, self::$text_domain ), 5 ),
						'600' => sprintf( _n( '%d minute', '%d minutes', 10, self::$text_domain ), 10 )
				),
				'type' => 'optgroups' 
		) );
?>
		<p class="description"><?php _e( 'Events caching helps to save Google Calendar API requests quota. Select lower value if your calendar is frequently updated. <br /><strong>Attention: "No caching" will significantly aument calendar API usage</strong>', self::$text_domain ); ?></p>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-general">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>show_title_<?php echo $preset_index; ?>"><?php _e( 'Show event title', self::$text_domain ); ?></label>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'show_title_' . $preset_index, self::$option_prefix . $preset_index . '[show_title]', $options['show_title'], array(
				'options' => array(
						'0' => __( 'No' ),
						'1' => __( 'Yes' ),
				),
				'type' => 'optgroups' 
		) );
?>
		<span><label for="<?php echo self::$option_prefix; ?>title_css_<?php echo $preset_index; ?>"><?php _e( 'Title CSS: ', self::$text_domain ); ?></label>
		<input type="text" class="regular-text" 
			id="<?php echo self::$option_prefix; ?>title_css_<?php echo $preset_index; ?>" 
			name="<?php echo self::$option_prefix . $preset_index; ?>[title_css]" 
			value="<?php echo esc_attr( $options['title_css'] ); ?>" /></span>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-general">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>show_date_<?php echo $preset_index; ?>"><?php _e( 'Show event date', self::$text_domain ); ?></label>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'show_date_' . $preset_index, self::$option_prefix . $preset_index . '[show_date]', $options['show_date'], array(
				'options' => array(
						'0' => __( 'No' ),
						'1' => __( 'Yes' ),
				),
				'type' => 'optgroups' 
		) );
?>
		<span><label for="<?php echo self::$option_prefix; ?>date_css_<?php echo $preset_index; ?>"><?php _e( 'Date CSS: ', self::$text_domain ); ?></label>
		<input type="text" class="regular-text" 
			id="<?php echo self::$option_prefix; ?>date_css_<?php echo $preset_index; ?>" 
			name="<?php echo self::$option_prefix . $preset_index; ?>[date_css]" 
			value="<?php echo esc_attr( $options['date_css'] ); ?>" /></span>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-general">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>show_location_<?php echo $preset_index; ?>"><?php _e( 'Show event location', self::$text_domain ); ?></label>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'show_location_' . $preset_index, self::$option_prefix . $preset_index . '[show_location]', $options['show_location'], array(
				'options' => array(
						'0' => __( 'No' ),
						'1' => __( 'Yes' ),
				),
				'type' => 'optgroups' 
		) );
?>
		<span><label for="<?php echo self::$option_prefix; ?>location_css_<?php echo $preset_index; ?>"><?php _e( 'Location CSS: ', self::$text_domain ); ?></label>
		<input type="text" class="regular-text" 
			id="<?php echo self::$option_prefix; ?>location_css_<?php echo $preset_index; ?>" 
			name="<?php echo self::$option_prefix . $preset_index; ?>[location_css]" 
			value="<?php echo esc_attr( $options['location_css'] ); ?>" /></span>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-link-title">
	<th scope="row">
		<label for="<?php echo self::$option_prefix; ?>link_title_<?php echo $preset_index; ?>"><?php _e( 'Link event title', self::$text_domain ); ?></label>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::selectInput( self::$option_prefix . 'link_title_' . $preset_index, self::$option_prefix . $preset_index . '[link_title]', $options['link_title'], array(
				'options' => array(
						'0' => __( 'No' ),
						'1' => __( 'Yes' ),
				),
				'type' => 'optgroups' 
		) );
?>
	</td>
</tr>
<tr valign="top" class="scd-gc-hide scd-gcal-colors">
	<th scope="row">
		<?php _e( 'Events color filter', self::$text_domain ); ?>
	</th>
	<td>
<?php
		echo SmartCountdownGoogleCal_Helper::colorsFilterInput(self::$option_prefix . 'color_filter_' . $preset_index, self::$option_prefix . $preset_index . '[color_filter]', $options );
?>
	</td>
</tr>
<?php
		return ob_get_clean();
	}
	
	public static function deleteDeprecatedOptions( $preset_index ) {
		foreach( array(
				'title_',
				'auth_',
				'calendar_id_',
				'api_key_',
				'auth_email_',
				'api_auth_key_file_',
				'events_cache_',
				'events_cache_time_',
				'all_day_event_start_',
				'show_title_',
				'link_title_',
				'show_location_',
				'show_date_',
				'color_filter_',
				'title_css_',
				'date_css_',
				'location_css_'
		) as $option_name ) {
			delete_option( SmartCountdownGoogleCal_Plugin::$option_prefix . $option_name. $preset_index );
			delete_site_option( SmartCountdownGoogleCal_Plugin::$option_prefix . $option_name. $preset_index );
		}
	}
}

SmartCountdownGoogleCal_Plugin::get_instance();

function smartcountdown_google_cal_uninstall() {
	foreach( array( 1, 2 ) as $preset_index ) {
		// just in case old version is uninstalled, delete deprecated options
		SmartCountdownGoogleCal_Plugin::deleteDeprecatedOptions( $preset_index );
		// delete options
		// stand-alone
		delete_option( SmartCountdownGoogleCal_Plugin::$options_page_slug, SmartCountdownGoogleCal_Plugin::$option_prefix . 'events_cache_' . $preset_index );
		delete_option( SmartCountdownGoogleCal_Plugin::$options_page_slug, SmartCountdownGoogleCal_Plugin::$option_prefix . 'auth_key_file_' . $preset_index );
		// rest of options as array
		delete_option( SmartCountdownGoogleCal_Plugin::$option_prefix . $preset_index );
		delete_site_option( SmartCountdownGoogleCal_Plugin::$option_prefix . $preset_index );
		// events cache
		delete_option( SmartCountdownGoogleCal_Plugin::$option_prefix . 'events_cache_' . $preset_index );
		delete_site_option( SmartCountdownGoogleCal_Plugin::$option_prefix . 'events_cache_' . $preset_index );
	}
}
register_uninstall_hook( __FILE__, 'smartcountdown_google_cal_uninstall' );
