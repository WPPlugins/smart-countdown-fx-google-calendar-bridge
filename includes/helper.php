<?php
/*
 * Version: 1.3
 * Author: Alex Polonski
 * Author URI: http://smartcalc.es/wp
 * License: GPL2
 */
defined( 'ABSPATH' ) or die();

define( 'SCD_GCAL_CALENDAR_MAX_RESULTS', 10 );
define( 'SCD_GCAL_API_DEAFULT_CACHE_TIME', 600 );
class SmartCountdownGoogleCal_Helper {
	public static function selectInput( $id, $name, $selected = '', $config = array() ) {
		$config = array_merge( array(
				'type' => 'integer',
				'start' => 1,
				'end' => 10,
				'step' => 1,
				'default' => 0,
				'padding' => 2,
				'class' => '' 
		), $config );
		
		if( !empty( $config['class'] ) ) {
			$config['class'] = ' class="' . $config['class'] . '"';
		}
		$html = array();
		
		if( $config['type'] == 'integer' ) {
			$html[] = '<select id="' . $id . '" name="' . $name . '"' . $config['class'] . '>';
			
			for( $v = $config['start']; $v <= $config['end']; $v += $config['step'] ) {
				$html[] = '<option value="' . $v . '"' . ( $selected == $v ? ' selected' : '' ) . '>' . str_pad( $v, $config['padding'], '0', STR_PAD_LEFT ) . '</option>';
			}
		} elseif( $config['type'] == 'optgroups' ) {
			// plain lists and option groups supported
			$html[] = '<select id="' . $id . '" name="' . $name . '"' . $config['class'] . '>';
			
			foreach( $config['options'] as $value => $option ) {
				if( is_array( $option ) ) {
					// this is an option group
					$html[] = '<optgroup label="' . esc_html( $value ) . '">';
					foreach( $option as $v => $text ) {
						$html[] = '<option value="' . $v . '"' . ( $v == $selected ? ' selected' : '' ) . '>';
						$html[] = esc_html( $text );
						$html[] = '</option>';
					}
					$html[] = '</optgroup>';
				} else {
					// this is a plain select option
					$html[] = '<option value="' . $value . '"' . ( $value == $selected ? ' selected' : '' ) . '>';
					$html[] = esc_html( $option );
					$html[] = '</option>';
				}
			}
		}
		
		$html[] = '</select>';
		
		return implode( "\n", $html );
	}
	public static function colorsFilterInput( $id, $name, $config ) {
		$options = array( '*' => array( 'background' => 'transparent', 'text' => __( 'Default' ) ) );
		$values = $config['color_filter'];
		
		if( $config['auth'] != 'oauth' ) {
			return __( ' OAuth authentication method required', SmartCountdownGoogleCal_Plugin::$text_domain );
		}
		
		// get calendar colors
		
		// Include and setup google client library
		if( !function_exists('google_api_php_client_autoload')) {
			// check if other plugins already loaded the library to avoid PHP errors
			require_once dirname( __FILE__ ) . '/../lib/autoload.php';
		}
		$client = new Google_Client();
		$client->setApplicationName( SmartCountdownGoogleCal_Plugin::$provider_alias );

		// Calendar ID is required
		$calendarId = $config['calendar_id'];
		if( empty( $calendarId ) ) {
			return __( 'Calendar ID not set', SmartCountdownGoogleCal_Plugin::$text_domain );
		}
		$auth_email = $config['auth_email'];
		$p12key = $config['api_p12key'];
					
		if( empty( $auth_email ) ) {
			return __( 'OAuth email not set', SmartCountdownGoogleCal_Plugin::$text_domain );
		}
		if( empty( $p12key ) ) {
			return __( 'Missing OAuth p12 key', SmartCountdownGoogleCal_Plugin::$text_domain );
		}
		// decode stored key to recover original p12 data
		$p12key = base64_decode( $p12key );
					
		// Readonly access
		$scopes = "https://www.googleapis.com/auth/calendar.readonly";
					
		$cred = new Google_Auth_AssertionCredentials( $auth_email, array(
			$scopes 
		), $p12key );
		$client->setAssertionCredentials( $cred );
		
		try
		{
			if( $client->getAuth()->isAccessTokenExpired() ) {
				$client->getAuth()->refreshTokenWithAssertion( $cred );
			}
			$service = new Google_Service_Calendar( $client );
			$calendar = $service->calendarList->get( $calendarId );
			$access_role = $calendar->getAccessRole();
			$event_colors = array();
			if( $access_role == 'writer' || $access_role == 'owner' )
			{
				// yes, we can setup color filtering
				$colors = $service->colors->get();
				foreach( $colors->getEvent() as $key => $color )
				{
					$options[$key] = array( 'background' => $color->getBackground(), 'foreground' => $color->getForeground() );
				}
			} else {
				return __( 'Color filter not active - a "writer" permission is required', SmartCountdownGoogleCal_Plugin::$text_domain );
			}
		}
		catch(Exception $e)
		{
			return __( 'Invalid configuration, error: ', SmartCountdownGoogleCal_Plugin::$text_domain ) . $e->getMessage();
		}

		$html = array ();
		
		foreach ( $options as $value => $color ) {
			$field_id = $id . $value;
			$field_name = $name . '[' . $value . ']';
			$html [] = '<input type="checkbox" id="' . $field_id . '" name="' . $field_name . '"' . ( !empty( $values[$value] ) && $values[$value] == 'on'  ? ' checked' : '' ) . ' />';
			$html [] = '<label for="' . $field_id . '">';
			if( empty( $color['text'] ) ) {
				$html [] = '<span style="display:inline-block;width:8em;background-color:' . $color['background'] . '">&nbsp;</span>';
			} else {
				$html [] = esc_attr( $color['text'] );
			}
			$html[] = '</label>';
			$html [] = '<br />';
		}
		
		return implode( "\n", $html );
	}
	public static function apiKeyUploadControl( $id, $name, $is_uploaded ) {
		$html = array();
		
		if( $is_uploaded ) {
			// initially hide upload control
			$input_style = ' style="display:none;"';
			$html[] = '<button type="button" class="button-primary refresh-p12-file">' . __( 'Update p12 key', SmartCountdownGoogleCal_Plugin::$text_domain ) . '</button>';
		} else {
			// initially show upload control
			$input_style = '';
		}
		$html[] = '<input type="file" class="upload-p12-file" id="' . $id . '" name="' . $name . '" value=""' . $input_style . ' />';
		return implode( "\n", $html );
	}
	public static function getEvents( $instance, $configs ) {
		if( empty( $configs ) ) {
			return $instance;
		}
		
		// init imported array
		$imported = array();
		
		// get current UTC time for cache expiration check
		$now = new DateTime( current_time( 'mysql', true ) );
		
		foreach( $configs as $preset_index => $config ) {
			if( empty( $config['auth'] ) ) {
				// configuration disabled - skip it
				continue;
			}
			
			// if this plugin is used with old version of Smart Countdown FX we presume that
			// countdown_to_end mode is always OFF
			$countdown_to_end = !empty( $instance['countdown_to_end'] ) ? true : false;
			
			// Include and setup google client library
			if( !function_exists('google_api_php_client_autoload')) {
				// check if other plugins already loaded the library to avoid PHP errors
				require_once dirname( __FILE__ ) . '/../lib/autoload.php';
			}
			$client = new Google_Client();
			$client->setApplicationName( SmartCountdownGoogleCal_Plugin::$provider_alias );
			
			// Get current auth mode from plugin settings
			$auth_mode = $config['auth'];
			
			// Calendar ID is required for both auth modes
			$calendarId = $config['calendar_id'];
			if( empty( $calendarId ) ) {
				error_log('Google Calendar bridge for Smart Countdown FX warning: Calendar ID not set');
				return $instance;
			}
			
			// reset events for current configuration preset
			$events = null;
			
			// get events cache from option. We use events caching in session in order to conserve API usage cuota.
			// each configuration preset maintains its own events cache
			$gcal_cache = get_option( SmartCountdownGoogleCal_Plugin::$option_prefix . 'events_cache_' . $preset_index, null );
			
			$now_ts = $now->getTimestamp();
			if( !empty( $gcal_cache ) ) {
				$cache_ts = $gcal_cache['timestamp'];
				// check if cache has not expired
				$cache_time = $config['events_cache_time'];
				if( $now_ts - $cache_ts < $cache_time ) {
					// we have events in cache
					$events = unserialize( $gcal_cache['events'] );
					$calendar_default_tz = $gcal_cache['calendar_default_tz'];
				}
			}
			
			if( is_null( $events ) ) {
				// request Google API for events
				$events = array();
				
				if( $auth_mode == 'api_key' ) {
					$api_key = $config['api_key'];
					$client->setDeveloperKey( $api_key );
					$cal = new Google_Service_Calendar( $client );
					
					// for simple API access by key we cannot get calendar settings (login required), so
					// we use timezone from WordPress general settings
					$calendar_default_tz = get_option( 'timezone_string' );
					
					if( empty( $calendar_default_tz ) ) {
						$offset = get_option( 'gmt_offset' ) * 3600;
						
						// for now we set timezone as UTC
						$calendar_default_tz = 'UTC';
						
						// later we consider other options. TBC...
					}
					
					$query_params = array(
							'singleEvents' => true,
							'orderBy' => 'startTime',
							'timeMin' => date( DateTime::ATOM ),
							'maxResults' => SCD_GCAL_CALENDAR_MAX_RESULTS 
					);
					try {
						$events = $cal->events->listEvents( $calendarId, $query_params )->getItems();
					} catch( Exception $e ) {
						error_log('Google Calendar bridge for Smart Countdown FX error: ' . $e->getMessage());
						return $instance;
					}
				} else {
					// OAuth
					
					$auth_email = $config['auth_email'];
					$p12key = $config['api_p12key'];
					
					if( empty( $auth_email ) ) {
						error_log('Google Calendar bridge for Smart Countdown FX warning: Auth email not set for OAuth autorization');
						return $instance;
					}
					if( empty( $p12key ) ) {
						error_log('Google Calendar bridge for Smart Countdown FX warning: p12key missing for OAuth autorization');
						return $instance;
					}
					// decode stored key to recover original p12 data
					$p12key = base64_decode( $p12key );
					
					// Readonly access
					$scopes = "https://www.googleapis.com/auth/calendar.readonly";
					
					$cred = new Google_Auth_AssertionCredentials( $auth_email, array(
							$scopes 
					), $p12key );
					$client->setAssertionCredentials( $cred );
					
					try {
						if( $client->getAuth()->isAccessTokenExpired() ) {
							$client->getAuth()->refreshTokenWithAssertion( $cred );
						}
						$service = new Google_Service_Calendar( $client );
						
						$query_params = array(
								'singleEvents' => true,
								'orderBy' => 'startTime',
								'timeMin' => date( DateTime::ATOM ),
								'maxResults' => SCD_GCAL_CALENDAR_MAX_RESULTS 
						);
						
						$calendar_default_tz = $service->calendarList->get( $calendarId )->timeZone;
						
						$events = $service->events->listEvents( $calendarId, $query_params )->getItems();
					} catch( Exception $e ) {
						error_log('Google Calendar bridge for Smart Countdown FX error: ' . $e->getMessage());
						return $instance;
					}
				}
				
				// store events cache in options
				update_option( SmartCountdownGoogleCal_Plugin::$option_prefix . 'events_cache_' . $preset_index, array(
						'timestamp' => $now_ts,
						'calendar_default_tz' => $calendar_default_tz,
						'events' => serialize( $events ) 
				) );
			}
			
			// we have events array for current configuration preset at this point 
			
			// prepare filter events by color
			$colors_filter = $config['color_filter'];
			
			foreach( $events as $event ) {
				$start = $event->getStart();
				$end = $event->getEnd();
				$visibility = $event->getVisibility();
				$description = $event->getDescription();
				$hangout_link = $event->getHangoutLink();
				$html_link = $event->getHtmlLink();
				$location = $event->getLocation();
				$status = $event->getStatus();
				$title = $event->getSummary();
				
				// Colors feature. There are several conditions to be fulfilled in order to
				// get colors working:
				// 1. OAuth authentication
				// 2. Service account email, added to calendar's sharing list must have write permissions
				
				// Only apply color filter for oauth, settings could have been saved for other
				// configurations but we should ignore them
				if( !empty( $colors_filter ) && $auth_mode == 'oauth' ) {
					// color filtering is active
					$color = $event->getColorId();
					// default calendar color option corresponds to "*" in filter array
					$color = empty( $color ) ? '*' : $color;
					if( !isset( $colors_filter[$color] ) ) {
						continue;
					}
				}
				
				if( $status != 'confirmed' ) {
					continue;
				}
				
				// normally an "all day" event will have both dateTime properties empty
				$is_all_day = empty( $start->dateTime ) && empty( $end->dateTime );
				
				if( $is_all_day && empty( $config['all_day_event_start'] ) ) {
					// skip all-day events if set so in plugin configuration
					continue;
				}
				
				// get event duration and handle all-day events
				if( !$is_all_day ) {
					$start_date = new DateTime( $start->dateTime );
					$end_date = new DateTime( $end->dateTime );
					$duration = $end_date->getTimestamp() - $start_date->getTimestamp();
					// google API already formats event date ready for JS Date(), i.e. with offset appended
					$start_date = $start->dateTime;
					$end_date = $end->dateTime;
				} else {
					// at the moment we explicitly set all-day events duration to zero
					$duration = 0;
					$all_day_start = new DateTime( $start->date . 'T' . $config['all_day_event_start'] );
					// convert all-day start date to UTC
					$default_timezone = new DateTimeZone( $calendar_default_tz );
					$offset = $default_timezone->getOffset( $all_day_start );
					$all_day_start->setTimestamp( $all_day_start->getTimestamp() - $offset );
					$all_day_start->setTimezone( new DateTimeZone( 'UTC' ) );
					// prepare the date for JS Date()
					$start_date = $all_day_start->format( 'c' );
				}
				
				$display_date = empty( $start->dateTime ) ? new DateTime( $start->date . 'T' . $config['all_day_event_start'] ) : new DateTime( $start->dateTime );
				$display_tz = $start->getTimezone();
				
				if( !empty( $display_tz ) ) {
					$display_date->setTimezone( new DateTimeZone( $display_tz ) );
				}
				$display_date = $display_date->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
				
				// Construct event title
				$title_css = !empty( $config['title_css'] ) ? ' style="' . $config['title_css'] . '"' : '';
				$date_css = !empty( $config['date_css'] ) ? ' style="' . $config['date_css'] . '"' : '';
				$location_css = !empty( $config['location_css'] ) ? ' style="' . $config['location_css'] . '"' : '';
				
				if( !empty( $config['show_title'] ) ) {
					$title = '<span' . $title_css . '>' . ( !empty( $title ) ? $title : __( 'Private event', SmartCountdownGoogleCal_Plugin::$text_domain ) ) . '</span>';
					$link = $visibility != 'private' ? $html_link : null;
					// only link title for public calenadrs
					if( !empty( $link ) && !empty( $config['link_title'] ) && $auth_mode == 'api_key' ) {
						$title = '<a alt="direct link to google calendar" href="' . $link . '">' . $title . '</a>';
					}
					if( !empty( $location ) && !empty( $config['show_location'] ) ) {
						$title .= ' <span' . $location_css . '>' . $location . '</span>';
					}
					// no date display for all-day events
					if( !$is_all_day && !empty( $config['show_date'] ) ) {
						$title .= ' <span' . $date_css .'>';
						$title .= $display_date;
						if( !empty( $display_tz ) && $display_tz != $calendar_default_tz ) {
							$title .= ' (' . $display_tz . ')';
						}
						$title .= '</span>';
					}
				} else {
					// event title hidden in current configuration
					$title = '';
				}
				if( !$countdown_to_end || $duration == 0) {
					$imported[] = array(
							'deadline' => $start_date,
							'title' => $title,
							'duration' => $duration
					);
				} else {
					// "countdown to end" mode - add 2 events to timeline:
					// event start time - normal
					$imported[] = array(
							'deadline' => $start_date,
							'title' => $title,
							'duration' => 0
					);
					// event end time - with countdown_to_end flag
					$imported[] = array(
							'deadline' => $end_date,
							'title' => $title,
							'duration' => 0,
							'is_countdown_to_end' => 1
					);
				}
			}
		}
		
		// if 'imported' is not set yet (cound have come from previous plugins)
		// initialize
		if( !isset( $instance['imported'] ) ) {
			$instance['imported'] = array();
		}
		
		// add imported events to instance (use provide alias as key)
		$instance['imported'][SmartCountdownGoogleCal_Plugin::$provider_alias] = $imported;
		
		return $instance;
	}
}