<?php
/*
Plugin Name: Last.fm Sidebar
Plugin URI: http://www.peppery.net.nz/projects/
Description: Displays your last played last.fm track in your Wordpress sidebar.
Author: Harrison Gulliver
Version: 1.04
Author URI: http://www.peppery.net.nz/
*/

function init_lastsidebar(){
    function widget_lastfm($args) {
        extract($args);
	$options = get_option('widget_lastfm');
	
	if(time() - $options['cdate'] < $options['ctime'] && $options['ctime'] !== "0") {
	    echo "<!-- last.fm sidebar: requesting cached data from wordpress database -->";
	    $getcache = 1;
	} else {
	    echo "<!-- last.fm sidebar: cache data is outdated, fetching new data from last.fm -->";
	    $api = @file_get_contents("http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=". $options['username'] ."&limit=1&api_key=a660630623c2545898f403777bc22146&randseed=". rand(0,9999));
	    $xml = @simplexml_load_string($api);
	}

	if(!$api || $getcache == 1) {
	    $cache = base64_decode($options['cache']);
	    $xml = simplexml_load_string($cache);
	} else {
	    $options['cache'] = base64_encode($api);
	    $options['cdate'] = time();
	    update_option('widget_lastfm', $options);
	}
	
	$artist = $xml->recenttracks->track->artist; // artist
	$song = $xml->recenttracks->track->name; // song
	$url = $xml->recenttracks->track->url; //url
	
	$data = $xml->recenttracks->track;
	
	if($options['size'] == "small") { $size = 0; $dimensions = 34; } // This could be done another way but I had enough trouble with album art as it is.
	if($options['size'] == "medium") { $size = 1; $dimensions = 64; }
	if($options['size'] == "large") { $size = 2; $dimensions = 126; }
	if($options['size'] !== "small" && $options['size'] !== "medium" && $options['size'] !== "large" && $options['size'] !== "disabled") { $size = 0; $dimensions = 34; }
	
	if(strlen($data->image[$size]) > 7 && $options['size'] !== "disabled") {
	    $art = '<img src="'. $data->image[$size] .'" align="left" height="'. $dimensions .'" width="'. $dimensions .'" style="border: 1px solid grey; padding: 2px; margin: 2px;">';
	}
	
	if(strlen($xml->recenttracks->track->album) > 3) {
	    $album = "<em>from the album <strong>". $xml->recenttracks->track->album ."</strong>.</em><br />";
	}
	
	function prefixdate($x, $y) {
	    $x = explode(".", $x);
	    if($x[0] == "1") {
		return $x[0] ." ". $y ." ago";
	    } else {
		return $x[0] ." ". $y ."s ago";
	    }
	}
	
	if($data['nowplaying']) {
	    $date = "Listening now";
	} else {
	    date_default_timezone_set("UTC");
	    $date = strtotime($data->date);
	    
	    $difference = time() - $date;
	    
	    if($difference < 61) {
		$date = prefixdate($difference, "second");
	    } elseif($difference < 3601) {
		$date = prefixdate($difference/3600, "hour");
	    } elseif($difference < 604800) {
		$date = prefixdate($difference/3600/24, "day");
	    } else {
		$date = prefixdate($difference/3600/24/7, "week");
	    }
	}
    ?>
	    <?php echo $before_widget; ?>
	        <?php echo $before_title
	            . $options['title']
	            . $after_title; ?>
	        <?php echo $art; ?><strong><?php echo $song; ?></strong> by <strong><?php echo $artist; ?></strong><br/ >
	    	<?php echo $album; ?><?php echo $date; ?> via <a href="http://www.last.fm/user/<?php echo $options['username']; ?>">last.fm</a>.
	    <?php echo $after_widget; ?>
    <?php
    }

    function widget_lastfm_control() {
    // This is based off the Automattic Google Search plugin, it's slightly confusing. Sorry.
	$options = get_option('widget_lastfm');
	if ( !is_array($options) )
	    $options = array('user'=>'', 'cache'=>'', 'ctime'=>'150', 'cdate'=>'1', 'title'=>__('Now Playing', 'widgets'), 'size'=>'small');
	    if ( $_POST['lastfm-submit'] ) {
		$options['title'] = strip_tags(stripslashes($_POST['lastfm-title']));
		$options['username'] = strip_tags(stripslashes($_POST['lastfm-user']));
		$options['size'] = strip_tags(stripslashes($_POST['lastfm-size']));
		$options['ctime'] = strip_tags(stripslashes($_POST['lastfm-ctime']));
                if(!$options['stats']) {
                    file_get_contents("http://counter.dreamhost.com/cgi-bin/Count.cgi?df=peppery-wplast.fm.dat&pad=F&ft=0&dd=E&istrip=T"); // for stats. thanks@
                    $options['stats'] = rand(0,9);
                }
		update_option('widget_lastfm', $options);
	    }
	    $title = htmlspecialchars($options['title'], ENT_QUOTES);
	    $user = htmlspecialchars($options['username'], ENT_QUOTES);
	    $size = htmlspecialchars($options['size'], ENT_QUOTES);
	    $ctime = htmlspecialchars($options['ctime'], ENT_QUOTES);
	    echo 'Widget title: <input style="width: 200px;" id="lastfm-title" name="lastfm-title" type="text" value="'.$title.'" /></br>';
	    echo 'Last.fm username: <input style="width: 200px;" id="lastfm-user" name="lastfm-user" type="text" value="'.$user.'" /></br />';
	    echo 'Album art size (currently: '. $size .'): <select id="lastfm-size" name="lastfm-size"><option value="small">Small (34x34)</option><option value="medium">Medium (64x64)</option><option value="large">Large (126x126)</option><option value="disabled">Disable album art</option></select><br />';
	    echo 'Cache expiry date (in seconds): <a href="#" onclick="javascript:alert(\'Last.fm Sidebar cache support:\r\nUse this to specify the number of seconds to keep your currently playing song data in the database. Default is 150 seconds (2 minutes).\r\n\r\nYou can disable this by entering 0. This will force the plugin to fetch new data from Last.fm on every page load. !! THIS IS NOT RECOMMENDED !! It puts unneccasary strain on the Last.fm servers as well as slowing down your blog loading.\r\nNote that disabling cache simply means that your blog will ask last.fm every page load. The widget will put this information in the database in case it fails to connect to last.fm in the future, it simply loads the data from the database.\');">[what is this?]</a> <input style="width: 200px;" id="lastfm-ctime" name="lastfm-ctime" type="text" value="'.$ctime.'" /></br />';
	    echo '<input type="hidden" id="lastfm-submit" name="lastfm-submit" value="1" />';
    }
    
    register_sidebar_widget("Last.fm Sidebar", "widget_lastfm");
    register_widget_control("Last.fm Sidebar", 'widget_lastfm_control');

}
add_action("plugins_loaded", "init_lastsidebar");
?>
