<?php
/*
Plugin Name: last.fm Live!
Plugin URI: http://2amlife.com/projects/lastfmlive
Description: Displays your recently played tracks PLUS will display what you currently listening to live via Last.FM.
Author: Ryan Peel
Version: 0.2.0
Author URI: http://2amlife.com/
Text Domain: lastfmlive

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

date_default_timezone_set('UTC');

!defined('WP_ADMIN_URL') ? define('WP_ADMIN_URL', get_option('siteurl') . '/wp-admin') :0;

!defined('WP_CONTENT_URL') ? define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content') :0;
!defined('WP_CONTENT_DIR') ? define('WP_CONTENT_DIR', ABSPATH . 'wp-content') : 0;
!defined('WP_PLUGIN_URL') ? define('WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins') : 0;
!defined('WP_PLUGIN_DIR') ? define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins') : 0;
define('LASTFMLIVE_ROOT', WP_PLUGIN_DIR."/".basename(dirname( __FILE__ )));
define('LASTFMLIVE_URL', WP_PLUGIN_URL."/".basename(dirname( __FILE__ )));
define('LASTFMLIVE_TPL_DIR', LASTFMLIVE_ROOT."/tpl");
define('LASTFMLIVE_LAK', "ab56575f312e3ed82281984edd2f1f1e");

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

function lastfmlive_setup(){
   require_once(livechoons_ROOT."/lib/class.tpl.php");
	$tpl = new fastTPL(LASTFMLIVE_TPL_DIR);
	$tpl->define(array('settings' => "settings.html", "header" => "header.html"));

	$tpl->assign("STYLE", file_get_contents(LASTFMLIVE_ROOT."/styles.css"));
	$tpl->assign("URL", LASTFMLIVE_URL."/");
	$tpl->assign("ICON_CLASS", "settings");
	$tpl->assign("PAGE_TITLE", "LastFM_Live - Settings");
	$tpl->assign("HEADER", $tpl->fetchParsed("header"));
	$tpl->assign("USERNAME", get_option('lastfmlive_lastfm_username'));
	$tpl->assign("TRACKLIMIT",  get_option('lastfmlive_lastfm_track_limit'));
	$tpl->assign("PREVIEW", lastfmlive_fetchRecentTracks());
	echo $tpl->fetchParsed("settings");
}

function lastfmlive_fetchRecentTracks($user, $limit){
	if($user == "" || $limit == ""){
		return "";
	}
	$recent_tracks = lastfm_apicall('user.getrecenttracks&user='.$user, $limit);
// 	echo "<pre>";print_r($recent_tracks);echo "</pre>";
// 	exit();
	require_once(LASTFMLIVE_ROOT."/lib/class.tpl.php");
	$tpl = new fastTPL(LASTFMLIVE_TPL_DIR);
	$tpl->define(array('recent_tracks_widget_block' => "recent_tracks_widget_block.html", "recent_tracks_widget" => "recent_tracks_widget.html"));
	$tracks = "";
	if(is_array($recent_tracks['recenttracks']['track'])){
		$t = count($recent_tracks['recenttracks']['track']);
		$i = 0;
		while($t > $i){
			$track =  $recent_tracks['recenttracks']['track'][$i];
			$time = time_ago($track['date']['uts'],  $i);
			if($time !== false){
				$time = $track['@attr']['nowplaying'] == true ? "Listening now.." : $time;
				if($track['image'][0]['#text'] != ""){
					$image = $track['image'][0]['#text'];
				} else {
					$artist_images = lastfm_apicall('artist.getimages&artist='.urlencode($track['artist']['#text']), 1);
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
				$tpl->assign("SONG_NAME", /*strlen($track['name']) > 16 ? substr($track['name'], 0, 13)."..." :*/ $track['name']);
				$tpl->assign("SONG_TITLE", $track['name']);
				$tpl->assign("ARTIST_TITLE",$track['artist']['#text']);
				$tpl->assign("SONG_URL", $track['url']);
				$tpl->assign("ARTIST_URL", "http://www.last.fm/music/".urlencode($track['artist']['#text']));
				$tpl->assign("TIME", $time);
				$tpl->assign("NOW_PLAYING", $track['@attr']['nowplaying'] == true ? " lastfmlive-now-playing" : "");
				$tracks .= $tpl->fetchParsed("recent_tracks_widget_block");
				}
			$i++;
		}
	}
	$tpl->assign("RECENT_TRACKS", $tracks);
	$tpl->assign("PROFILE_URL", "http://www.last.fm/user/$user");
	return  $tpl->fetchParsed("recent_tracks_widget");
}

function lastfm_apicall($method, $limit){
	$data = file_get_contents('http://ws.audioscrobbler.com/2.0/?method='.$method."&limit=".$limit.'&format=json&api_key='.LASTFMLIVE_LAK);
	if(function_exists(json_decode)){
		return json_decode($data, true);
	} else {
		require_once(LASTFMLIVE_ROOT.'/lib/class.json.php');
		$JSON = new serviceJSON(SERVICES_JSON_LOOSE_TYPE);
		return $JSON->decode($data);
	}
}

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



function time_ago( $datefrom , $ignore_future=true ){
//    convertTime(
//    if($datefrom<=0) { return "A long time ago"; }
  $dateto = time(); 

   $difference = $dateto - $datefrom;

   // Seconds
	if($difference < 15 && $ignore_future !== 0){
		return false;
   } else if($difference < 60){
      $time_ago   = $difference . ' second' . ( $difference > 1 ? 's' : '' ).' ago';
   } else if( $difference < 60*60 ){
         $ago_seconds   = $difference % 60;
        $ago_seconds   = ( ( $ago_seconds AND $ago_seconds > 1 ) ? ' and '.$ago_seconds.' seconds' : ( $ago_seconds == 1 ? ' and '.$ago_seconds.' second' : '' ) );
        $ago_minutes   = floor( $difference / 60 );
        $time_ago      = $ago_minutes . ' minute' . ( $ago_minutes > 1 ? 's' : '' ).' ago';
	} else if ( $difference < 60*60*24 ){
       $ago_hours      = floor( $difference / ( 60 * 60 ) );
       $time_ago      = $ago_hours. ($ago_hours > 1 ? ' hours' : ' hour').' ago';
   }else if ( $difference < 60*60*24*7 ){
		$ago_days = floor( $difference / ( 3600 * 24 ) );
      $time_ago       = $ago_days. ' day' . ($ago_days > 1 ? 's' : '' ).' ago';;
   } else if ( $difference < 60*60*24*30 ){
      $ago_weeks      = floor( $difference / ( 3600 * 24 * 7) );
      $time_ago      = $ago_weeks . ' week'. ($ago_weeks > 1 ? 's' : '' ).' ago';
   } else if ( $difference < 60*60*24*365 ){
      $days_diff   = round( $difference / ( 60 * 60 * 24 ) );
      $ago_months   = floor( $days_diff / 30 );
      $time_ago   =  $ago_months .' month'. ( $ago_months > 1 ? 's' : '' ).' ago';
   } else if ( $difference >= 60*60*24*365 ) {
      $ago_months   = round( $difference / ( 60 * 60 * 24 * 30.5 ) ) % 12;
      $ago_months   = ( ( $ago_months AND $ago_months > 1 ) ? ' and ' . $ago_months . ' months' : ( $ago_months == 1 ? ' and '.$ago_months.' month' : '' ) );
      $ago_years   = floor( $difference / ( 60 * 60 * 24 * 365 ) );#30 * 12
      $ago_years   = $ago_years . ' year'. ($ago_years > 1 ? 's' : '' ) ;
      $time_ago   = $ago_years.' ago';
   }

   return $time_ago;
}

class LastFM_LiveRecentTracks extends WP_Widget {

	function LastFM_LiveRecentTracks() {
		parent::WP_Widget(false, $name = 'Last.FM Live! - Recent Tracks');
	}

	function widget($args, $instance) {
        extract( $args );
		$tracklimit = $instance['tracklimit'] > 20 ? 20 : $instance['tracklimit'];
		echo $before_widget;
        echo $before_title.$instance['title'].$after_title;
		echo lastfmlive_fetchRecentTracks($instance['username'], $tracklimit);
		echo $after_widget;
	}

	function update($new_instance, $old_instance) {
        return $new_instance;
	}

	function form($instance) {
        $title = esc_attr($instance['title']);
		$username = esc_attr($instance['username']);
		$tracklimit = esc_attr($instance['tracklimit']);
        ?>
            <p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('title:'); ?> </label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('last.fm username:'); ?> </label>
				<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" name="<?php echo $this->get_field_name('username'); ?>" type="text" value="<?php echo $username; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('tracklimit'); ?>"><?php _e('track limit:'); ?> </label>
				<input class="widefat" id="<?php echo $this->get_field_id('tracklimit'); ?>" name="<?php echo $this->get_field_name('tracklimit'); ?>" type="text" value="<?php echo $tracklimit; ?>" />
			</p>
        <?php
	}
	
}

add_action('widgets_init', create_function('', 'return register_widget("LastFM_LiveRecentTracks");'));

//future admin & installation hooks
// add_action('admin_menu', 'lastfmlive_admin_menu');
//add_action("admin_print_scripts", 'lastfmlive_insert_scripts');
// register_activation_hook(__FILE__, 'lastfmlive_install');
// register_deactivation_hook(__FILE__, 'lastfmlive_uninstall');
// add_action('wp_ajax_lastfmlive_update_option', 'lastfmlive_update_option');