<?php
/*
Plugin Name: last.fm Live!
Plugin URI: http://2amlife.com/projects/lastfm-live
Description: Displays recently played tracks from a last.fm account in a widget, along with the currently playing track live in realtime.
Author: Ryan Peel
Version: 0.2.5
Author URI: http://2amlife.com/
Text Domain: lastfm-live

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor,
Boston, MA  02110-1301, USA.
---
Copyright (C) 2009, Ryan Peel ryan@2amlife.com
*/

if(function_exists(date_default_timezone_set)){
	date_default_timezone_set('UTC');
}

!defined('WP_ADMIN_URL') ? define('WP_ADMIN_URL', get_option('siteurl') . '/wp-admin') :0;

!defined('WP_CONTENT_URL') ? define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content') :0;
!defined('WP_CONTENT_DIR') ? define('WP_CONTENT_DIR', ABSPATH . 'wp-content') : 0;
!defined('WP_PLUGIN_URL') ? define('WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins') : 0;
!defined('WP_PLUGIN_DIR') ? define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins') : 0;
define('LASTFMLIVE_ROOT', WP_PLUGIN_DIR."/".basename(dirname( __FILE__ )));
define('LASTFMLIVE_URL', WP_PLUGIN_URL."/".basename(dirname( __FILE__ )));
define('LASTFMLIVE_TPL_DIR', LASTFMLIVE_ROOT."/tpl");
define('LASTFMLIVE_LAK', "ab56575f312e3ed82281984edd2f1f1e");
define('LASTFMLIVE_VERSION', "0.2.5");

// function lastfmlive_install(){
//
// }
//
// function lastfmlive_insert_scripts() {
//    wp_enqueue_script("lastfmlive", LASTFMLIVE_URL."/lastfmlive.js", array('prototype'));
// }

// function lastfmlive_admin_menu(){
//    $settings = __("LastFM_Live Setup", 'lastfmlive');
//    add_options_page($settings, $settings, 8, 'lastfmlive_setup', 'lastfmlive_setup');
// }

// function lastfmlive_setup(){
//    require_once(livechoons_ROOT."/lib/class.tpl.php");
// 	$tpl = new fastTPL(LASTFMLIVE_TPL_DIR);
// 	$tpl->define(array('settings' => "settings.html", "header" => "header.html"));
//
// 	$tpl->assign("STYLE", file_get_contents(LASTFMLIVE_ROOT."/styles.css"));
// 	$tpl->assign("URL", LASTFMLIVE_URL."/");
// 	$tpl->assign("ICON_CLASS", "settings");
// 	$tpl->assign("PAGE_TITLE", "LastFM_Live - Settings");
// 	$tpl->assign("HEADER", $tpl->fetchParsed("header"));
// 	$tpl->assign("USERNAME", get_option('lastfmlive_lastfm_username'));
// 	$tpl->assign("TRACKLIMIT",  get_option('lastfmlive_lastfm_track_limit'));
// 	$tpl->assign("PREVIEW", lastfmlive_fetchRecentTracks());
// 	echo $tpl->fetchParsed("settings");
// }



//for future admin page
// function lastfmlive_update_option(){
// 	if(is_array($_REQUEST['option'])){
// 		foreach($_REQUEST['option'] as $option){
// 			update_option($option , $_REQUEST['result'][$option]);
// 		}
// 		_e("Your settings have been saved!", 'lastfmlive');
// 		exit(0);
// 	}
// 	_e("Unknown Error Saving Data", 'lastfmlive');
// 	exit(0);
// }





class LastFM_LiveRecentTracks extends WP_Widget {

	function LastFM_LiveRecentTracks() {
		parent::WP_Widget(false, $name = 'last.fm Live! - Recent Tracks');
	}

	function widget($args, $instance) {
        extract( $args );
		if($instance['username'] != '' &&  $instance['tracklimit'] != ''){
			$tracklimit = $instance['tracklimit'] > 20 ? 20 : $instance['tracklimit'];
			echo $before_widget;
			echo $before_title.$instance['title'].$after_title;
			echo $this->fetchRecentTracks($instance['username'], $tracklimit, $instance['livetxt']);
			echo $after_widget;
		}
	}

	function update($new_instance, $old_instance) {
        return $new_instance;
	}

	function form($instance) {
        $title = esc_attr($instance['title']);
		$username = esc_attr($instance['username']);
		$tracklimit = esc_attr($instance['tracklimit']);
		require_once(LASTFMLIVE_ROOT."/lib/class.tpl.php");
		$tpl = new fastTPL(LASTFMLIVE_TPL_DIR);
		$tpl->define(array('settings' => "settings.html"));
		$tpl->assign("TITLE_ID", $this->get_field_id('title'));
		$tpl->assign("TITLE_LABEL", _('title:'));
		$tpl->assign("TITLE_NAME", $this->get_field_name('title'));
		$tpl->assign("TITLE_VALUE", esc_attr($instance['title']));
		$tpl->assign("USERNAME_ID", $this->get_field_id('username'));
		$tpl->assign("USERNAME_LABEL", _('username:'));
		$tpl->assign("USERNAME_NAME", $this->get_field_name('username'));
		$tpl->assign("USERNAME_VALUE", esc_attr($instance['username']));
		$tpl->assign("LIVETXT_ID", $this->get_field_id('livetxt'));
		$tpl->assign("LIVETXT_LABEL", _('live text:'));
		$tpl->assign("LIVETXT_NAME", $this->get_field_name('livetxt'));
		$tpl->assign("LIVETXT_VALUE", esc_attr($instance['livetxt']) == '' ? "Listening Now..." : esc_attr($instance['livetxt']));
		$tpl->assign("TRACKLIMIT_ID", $this->get_field_id('tracklimit'));
		$tpl->assign("TRACKLIMIT_LABEL", _('tracklimit:'));
		$tpl->assign("TRACKLIMIT_NAME", $this->get_field_name('tracklimit'));
		$tpl->assign("TRACKLIMIT_VALUE", esc_attr($instance['tracklimit']) == '' ? "5" : esc_attr($instance['tracklimit']));
		echo $tpl->fetchParsed("settings");
	}

	function fetchRecentTracks($user, $limit, $livetxt){
		$recent_tracks = $this->apiRequest('user.getrecenttracks&user='.$user, $limit);
// 		echo "<pre>";print_r($recent_tracks);echo "</pre>";
// 		exit();
		$livetxt == '' ? $livetxt = "Listening Now..." : 0;
		require_once(LASTFMLIVE_ROOT."/lib/class.tpl.php");
		$tpl = new fastTPL(LASTFMLIVE_TPL_DIR);
		$tpl->define(array('recent_tracks_widget_block' => "recent_tracks_widget_block.html", "recent_tracks_widget" => "recent_tracks_widget.html"));
		$tracks = "";
		if(is_array($recent_tracks['recenttracks']['track'])){
			$t = $limit;
			$i = 0;
			while($t > $i){
				$track =  $recent_tracks['recenttracks']['track'][$i];
				$time = !$track['@attr']['nowplaying'] ? $this->fuzzytime($track['date']['uts']) : $livetxt;
				if($track['image'][0]['#text'] != ""){
					$image = $track['image'][0]['#text'];
				} else {
					$artist_images = $this->apiRequest('artist.getimages&artist='.urlencode($track['artist']['#text']), 1);
		// 			echo "<pre>";print_r($artist_images);echo "</pre>";
		// 			exit();
					if($artist_images['images']['image']['sizes']['size'][0]['#text'] != ""){
						$image = $artist_images['images']['image']['sizes']['size'][0]['#text'];
					} else {
						$image = "http://cdn.last.fm/flatness/catalogue/noimage/2/default_artist_small.png";
					}
				}
				$tpl->assign("ALBUM_IMAGE", $image);
				$tpl->assign("ALBUM_TEXT", $track['album']['#text']);
				$tpl->assign("SONG_NAME", $track['name']);
				$tpl->assign("SONG_TITLE", $track['name']);
				$tpl->assign("ARTIST_TITLE",$track['artist']['#text']);
				$tpl->assign("SONG_URL", $track['url']);
				$tpl->assign("ARTIST_URL", "http://www.last.fm/music/".urlencode($track['artist']['#text']));
				$tpl->assign("TIME", $time==="full" ? $track['date']['#text'] : $time);
				$tpl->assign("NOW_PLAYING", $track['@attr']['nowplaying'] == true ? " lastfmlive-now-playing" : "");
				$tracks .= $tpl->fetchParsed("recent_tracks_widget_block");
				$i++;
			}
		}
		$tpl->assign("STYLE", file_get_contents(LASTFMLIVE_ROOT."/styles.css"));
		$tpl->assign("RECENT_TRACKS", $tracks);
		$tpl->assign("PROFILE_URL", "http://www.last.fm/user/$user");
		return  $tpl->fetchParsed("recent_tracks_widget");
	}

	function apiRequest($method, $limit){
		$http_options = array(
			'http' => array(
				'method' => "GET",
				'header' => "User-Agent: Wordpress Plugin: last.fm Live!(http://2amlife.com/projects/lastfm-live) version ".LASTFMLIVE_VERSION."\r\n"
			)
		);
		$context = stream_context_create($http_options);
		$data = file_get_contents('http://ws.audioscrobbler.com/2.0/?method='.$method."&limit=".$limit.'&format=json&api_key='.LASTFMLIVE_LAK, false, $context);
		if($data !== false){
			if(function_exists(json_decode)){
				return json_decode($data, true);
			} else {
				require_once(LASTFMLIVE_ROOT.'/lib/class.json.php');
				$JSON = new serviceJSON(SERVICES_JSON_LOOSE_TYPE);
				return $JSON->decode($data);
			}
		} else {
			return $data;
		}
	}

	function fuzzytime($datefrom){

		$difference = time() - $datefrom;
		$show_fuzzy_detail = false;

		if($difference < 60){
			$plural = $difference > 1 ? 's' : '';
			return $difference.' second'.$plural.' ago';
		} else if( $difference < 60*60 ){
			$ago_seconds = $difference % 60;
			$plural = $ago_seconds > 1?'s':'';
			$ago_seconds_txt = $ago_seconds > 0 && $show_fuzzy_detail === true ?' and '.$ago_seconds.' second'.$plural.' ago' : ' ago';
			$ago_minutes = floor( $difference / 60 );
			$minplural= $ago_minutes > 1 ? 's' : '';
			return $ago_minutes . ' minute'.$minplural.$ago_seconds_txt;
		} else if ( $difference < 60*60*24 ){
			$ago_hours = floor( $difference / ( 60 * 60 ) );
			$plural = $ago_hours > 1 ? 's' : '';
			return  $ago_hours.' hour'.$plural.' ago';
		}else if ( $difference >= 60*60*24 && !$is_playing) {
			return "full";
		}/*if ( $difference < 60*60*24*7 ){
			$ago_days = floor( $difference / ( 3600 * 24 ) );
			return $ago_days. ' day' . ($ago_days > 1 ? 's' : '' ).' ago';;
		} else if ( $difference < 60*60*24*30 ){
			$ago_weeks = floor( $difference / ( 3600 * 24 * 7) );
			return $ago_weeks . ' week'. ($ago_weeks > 1 ? 's' : '' ).' ago';
		} else if ( $difference < 60*60*24*365 ){
			$days_diff   = round( $difference / ( 60 * 60 * 24 ) );
			$ago_months   = floor( $days_diff / 30 );
			return  $ago_months .' month'. ( $ago_months > 1 ? 's' : '' ).' ago';
		} else if ( $difference >= 60*60*24*365 ) {
			return date("M jS H:is", $datefrom);
		}*/
	}
}

add_action('widgets_init', create_function('', 'return register_widget("LastFM_LiveRecentTracks");'));

//future admin & installation hooks
// add_action('admin_menu', 'lastfmlive_admin_menu');
//add_action("admin_print_scripts", 'lastfmlive_insert_scripts');
// register_activation_hook(__FILE__, 'lastfmlive_install');
// register_deactivation_hook(__FILE__, 'lastfmlive_uninstall');
// add_action('wp_ajax_lastfmlive_update_option', 'lastfmlive_update_option');