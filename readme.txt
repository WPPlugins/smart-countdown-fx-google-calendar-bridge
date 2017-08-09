=== Smart Countdown FX Google Calendar Bridge ===
Contributors: alex3493 
Tags: smart countdown fx, countdown, counter, count down, timer, event, widget, import plugin, recurring, google, calendar
Requires at least: 4.0
Tested up to: 4.6.1
Stable tag: 1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Smart Countdown FX Google Calendar Bridge adds Google Calendar support to Smart Countdown FX.

== Description ==
Smart Countdown FX Google Calendar Bridge **requires [Smart Countdown FX][2] version 1.3 or higher**, please do not forget to update before proceeding.

Up to two independent configurations can be setup.

Both "Public API Key" and "OAuth" authorization methods are supported. Additional one-time configuration of your Google API and calendar is required. Refer to [documentation][1] for details.

**Other features**

When configuring Smart Countdown FX or adding a shortcode to you post you can choose one of or both configurations defined in "Smart Countdown FX Google Calendar Bridge" settings. The opition to use both configurations will merge events from both calendars into a single timeline.

For samples and complete documentation [see this page][1]

 [1]: http://wp.smartcalc.org/google-calendar/
 [2]: https://wordpress.org/plugins/smart-countdown-fx/

== Installation ==
Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from Plugins page. Open "Settings" and configure recurrence pattern(s)

== Frequently Asked Questions ==
= How does one use the shortcode, exactly? =
Actually there is a single shortcode - "import_config".

<http://wp.smartcalc.org/google-calendar/> - complete list of attribute values for this shortcode has been provided to answer this exact question.

= I have installed the plugin, but the counter doesn't appear in available widgets list. =
Do not forget to install and activate the main plugin - Smart Countdown FX.

= I have configured the widget but it is not displayed. =
Please, check "Counter display mode" setting in the widget options. If "Auto - both countdown and countup" is not selected, the widget might have been automatically hidden because the event is still in the future or already in the past. If you are using "Smart Countdown FX Google Calendar Bridge" plugin check that "Import events from:" setting is correct. Then go to "Smart Countdown FX Google Calendar Bridge" settings and make sure that configuration selected in "Import events from:" is not set to "Disabled".
**Linking a widget to a disabled configuration will hide the counter becuse no events will be found**

= I have configured Smart Countdown FX widget, Google Calendar Bridge plugin and my Google Calendar account, and there are events in calendar, but the counter it is not displayed. =
Google Calendar Bridge plugin configuration is not trivial and possible errors and / or misprints may affect plugin-to-Google account connection. Normally you would double-check all settings (API key, Calendar ID, etc.). If it doesn't solve connection issues you can refer to PHP error log for Google Calendar Bridge plugin related errors and warnings. 

= I have inserted the countdown in a post, but it is not displayed. What's wrong? =
Check the spelling of "fx_preset" attribute (if you included it in attributes list). Try the standard fx_preset="Sliding_text_fade.xml". Also check "mode" attribute. Set in to "auto". If you are using Google Calendar Bridge plugin check that import_config attribute is correct, e.g.: import_config="scd_google_cal::1" to use the first pattern. Then go to "Smart Countdown FX Google Calendar Bridge" settings and make sure that Configuration 1 "Authorization" is not set to "Disabled".
**Linking a widget to a disabled configuration will hide the counter becuse no events will be found**

== Screenshots ==
1. Plugin settings sample 1

2. Plugin settings sample 2

== Changelog ==

= 1.4 =

Add error logging for Google Calendar account connection issues.

= 1.3 =

Bug fix - the plugin stopped working if other plugins were using Google PHP library on the same site.

= 1.2 =

Added support for "countdown to event end" mode

= 1.1 =

Plugin settings optimization, bug fixes

= 1.0 =

First release