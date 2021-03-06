<?php
/**
 * This file was developed as part of the Concerto digital signage project
 * at RPI.
 *
 * Copyright (C) 2009 Rensselaer Polytechnic Institute
 * (Student Senate Web Technologies Group)
 *
 * This program is free software; you can redistribute it and/or modify it 
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.  You should have received a copy
 * of the GNU General Public License along with this program.
 *
 * @package      Concerto
 * @author       Web Technologies Group, $Author$
 * @copyright    Rensselaer Polytechnic Institute
 * @license      GPLv2, see www.gnu.org/licenses/gpl-2.0.html
 * @version      $Revision$
 */
//Content Rendering API
//Version 0.08
//Notes: You shouldn't be touching this file directly.  You should be calling through the render/index.php handler and passing the version 007
include(COMMON_DIR.'user.php');     //Class to represent a site user
include(COMMON_DIR.'feed.php');     //Class to represent a content feed
include(COMMON_DIR.'content.php');  //Class to represent content items in the system


if($_REQUEST['select'] == 'system'){
    system_info();
} else {
    $criteria = validation($_REQUEST);
    $contents = content_selection($criteria);
    if($criteria['format'] == 'raw'){
        render_raw($contents, $criteria);
    }elseif($criteria['format'] == 'html'){
        render_html($contents, $criteria);
    }elseif($criteria['format'] == 'rawhtml'){
        render_rawhtml($contents, $criteria);
    }elseif($criteria['format'] == 'rss'){
        render_rss($contents, $criteria);
    }elseif($criteria['format'] == 'json'){
        render_json($contents, $criteria);
    }
}

//Grab and check user values
function validation($request){
    //Default Values
    $criteria['select'] = 'feed';
    $criteria['format'] = 'rss';
    $criteria['orderby'] = 'id';
    $criteria['range'] = 'live';
    //End default values

    //Define acceptable values
    $select_av = array('content', 'feed', 'user');
    $format_av = array('raw','html','rss','json','rawhtml');
    $orderby_av = array('id', 'rand', 'type_id', 'mime_type', 'submitted','start_time', 'end_time', 'user_id');
    $range_av = array('live', 'future', 'past', 'all');
    //End Acceptable values

    //First see if an API version is defined
    if(isset($request['api'])){
        $criteria['api'] = $request['api'];
    }

    //Check the selection array
    if(in_array($request['select'],$select_av)){
        $criteria['select'] = $request['select'];
    }

    //Set the selection id and verify it matches the selection type
    if(($criteria['select'] == 'feed' && is_numeric($request['select_id'])) ||
      ($criteria['select'] == 'user' && is_string($request['select_id'])) ||
      ($criteria['select'] == 'content' && is_numeric($request['select_id']))){
        $criteria['select_id'] = escape($request['select_id']);
    } else {
        return false;
    }

    //Check the format to match something
    if(isset($request['format']) && in_array($request['format'], $format_av) ){
        $criteria['format'] = $request['format'];
    }

    //Check the ordering to match something
    if(isset($request['orderby']) && in_array($request['orderby'], $orderby_av) ){
        $criteria['orderby'] = $request['orderby'];
    }

    //Check the range to match something
    if(isset($request['range']) && in_array($request['range'], $range_av) ){
        $criteria['range'] = $request['range'];
    }

    //Check the count, if any
    if(isset($request['count']) && is_numeric($request['count'])){
        $criteria['count'] = $request['count'];
    }

    //Check the type, if any
    if(isset($request['type'])){
        $sql = "SELECT id FROM type WHERE name LIKE '" . escape($request['type']) . "'";
        $res = sql_query($sql);
        if($res){
            $row = sql_row_keyed($res,0);
            $criteria['type_id'] = $row['id'];
        }
    }

    //Check height and width, if needed
    if(is_numeric($request['width']) && is_numeric($request['height'])){
        $criteria['width'] = $request['width'];
        $criteria['height'] = $request['height'];
    }

    return $criteria;
}

function content_selection($criteria){
    $content_arr = array();

    //Select content for a content type
    if($criteria['select'] == 'content'){
        $content = new Content($criteria['select_id']);
        if($content->set){
            $content_arr[] = $content;
        }
    }else{
        if($criteria['select'] == 'feed'){
            $sql_base = "SELECT content.id FROM content LEFT JOIN feed_content ON content.id = feed_content.content_id WHERE feed_content.moderation_flag = 1 AND feed_content.feed_id = {$criteria['select_id']}";
        }elseif($criteria['select'] == 'user'){
            $sql_base = "SELECT content.id FROM content LEFT JOIN user ON content.user_id = user.id LEFT JOIN feed_content ON content.id = feed_content.content_id WHERE feed_content.moderation_flag = 1 AND user.username = '{$criteria['select_id']}'"; 
        }

        //Handle range
        if(isset($criteria['range']) && $criteria['range'] != 'all'){
            if($criteria['range'] == 'live'){
                $sql_base .= " AND content.start_time <= NOW() AND content.end_time >= NOW()";
            }elseif($criteria['range'] == 'future'){
                $sql_base .= " AND content.start_time > NOW()";
            }elseif($criteria['range'] == 'past'){
                $sql_base .= " AND content.end_time < NOW()";
            }
        }
        //Handle type
        if(isset($criteria['type_id']) && $criteria['type_id'] != 'all'){
            $sql_base .= " AND content.type_id = {$criteria['type_id']}";
        }
        //Apply the grouping to remove dups
        $sql_base .= ' GROUP BY content.id';
        //Handle ordering
        if(isset($criteria['orderby'])){
            if($criteria['orderby'] == 'rand'){
                $sql_base .= " ORDER BY RAND()";
            } else {
                $sql_base .= " ORDER BY {$criteria['orderby']}";
            }
        }
        //Handle count
        if(isset($criteria['count'])){
            $sql_base .= " LIMIT 0, {$criteria['count']}";
        }
        $res = sql_query($sql_base);
        if($res){
          $i=0;
            while($row = sql_row_keyed($res,$i)){
                $content_arr[] = new Content($row['id']);
                $i++;
            }
        }
    }
    return $content_arr;
}

function render_html($content_arr, $criteria){
    foreach($content_arr as $content){
    ?>
<div id="concerto_<?php echo $content->id ?>" class="concerto">
	<div class="concerto_name"><?php echo $content->name ?></div>
<?php if(false===strpos($content->mime_type,'image')){ ?>
	<div class="concerto_content"><?php echo $content->content ?></div>
<?php } else { ?>
	<div class="concerto_content"><img src="<?php echo 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria) ?>&select=content&select_id=<?php echo $content->id ?>&format=raw" alt="<?php echo htmlspecialchars($content->name) ?>" /></div>
<?php } ?>
</div>
<?php
    }
}

function render_rawhtml($content_arr, $criteria){
    foreach($content_arr as $content){
    ?>
<?php if(false===strpos($content->mime_type,'image')){ ?>
    <?php echo $content->content ?>
<?php } else { ?>
    <img src="<?php echo 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria) ?>&select=content&select_id=<?php echo $content->id ?>&format=raw" alt="<?php echo htmlspecialchars($content->name) ?>" />
<?php } ?>
<?php
    }
}

function render_rss($content_arr, $criteria){
    if($criteria['select'] == 'user'){
        $filter = 'username';
    }else{
        $filter = 'id';
    }
    $sql = "SELECT name FROM {$criteria['select']} WHERE $filter = '{$criteria['select_id']}' LIMIT 1";
    $res = sql_query($sql);
    $row = sql_row_keyed($res,0);
    $feed_title = $row['name'];
    
    header("Content-type: text/xml");
    echo '<?xml version="1.0"?>';
?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><?php echo htmlspecialchars(utf8_encode($feed_title)) ?></title>
        <link>http://<?php echo $_SERVER['SERVER_NAME'] ?>/<?php echo ROOT_URL ?></link>
        <description>RSS Feed from Concerto API</description>
        <language>en-us</language>
        <pubDate><?php echo rssdate("now") ?></pubDate>
        <generator>Concerto API 0.08</generator>
        <webMaster><?php echo SYSTEM_EMAIL ?> (Concerto Digital Signage)</webMaster>
        <atom:link href="<?php echo 'http://' . $_SERVER['SERVER_NAME'] . htmlspecialchars($_SERVER['REQUEST_URI']) ?>" rel="self" type="application/rss+xml" />
        <image>
            <url><?php echo 'http://' . $_SERVER['SERVER_NAME'] . ADMIN_BASE_URL ?>/images/concerto_48x48.png</url>
            <title><?php echo htmlspecialchars(utf8_encode($feed_title)) ?></title>
            <link>http://<?php echo $_SERVER['SERVER_NAME'] ?>/<?php echo ROOT_URL ?></link>
            <width>48</width>
            <height>48</height>
        </image>

<?php 
 /*Sometimes, including the 'index.php' portion of the URL breaks clients like the built-in OS X screensaver.
  *By removing the reference to index.php, the screensaver doesn't know what to expect, and works.
  *This has taken multiple months to find.
  */
  $script_name = preg_replace('/(index\.php)$/', '', $_SERVER['SCRIPT_NAME']);
  
  foreach($content_arr as $content){
        $link = 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria) . '&select_id=' . $content->id . '&select=content&format=rss';
        $link = htmlspecialchars($link);
        $feeds = $content->list_feeds();
        $user = new User($content->user_id);
        $rss_link = 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria, 'rss') . '&select=content&select_id='.$content->id.'&format=raw';
        
        /*
         * So 10.6 inspects the last few characters of the enclosure url querystring
         * instead of the characters in the filename.  We add on this fake param to
         * satisfy its selfish needs.  If I had more time before this had to work,
         * I would make my own non-lame screensaver.
         */
         preg_match('/.+\/(.+)/',$content->mime_type,$matches);
         if(isset($matches[1]) && $matches[1] == 'jpeg'){
          $file_ext = 'jpg';
         } elseif(isset($matches[1])) {
          $file_ext = $matches[1];
         } else {
          $file_ext = 'unknown';
         }
        
       /* If mod_rewrite isn't enabled, this won't work, but it is required for some clients that care about file extensions
        * Without mod_rewrite, you can just use remove "'image_' . $content->content"
        */
        $raw_link = 'http://' . $_SERVER['SERVER_NAME'] .  $script_name . 'image_' . $content->content . '?' . criteria_string($criteria) . '&select=content&select_id='.$content->id.'&format=raw&file_ext=.' . $file_ext;
        
        if(strpos($content->mime_type,'image') !== false){
            $desc = '<![CDATA[ <img src="' . $rss_link . '" /> ]]>';
        } elseif(strpos($content->mime_type, 'html')){
            $desc = '<![CDATA[' . $content->content . ' ]]>';
        } else {
            $desc = $content->content;
        }
?>
        <item>
            <title><?php echo htmlspecialchars($content->name) ?></title>
            <link><?php echo $link ?></link>
            <description><?php echo $desc ?></description>
            <pubDate><?php echo rssdate($content->submitted) ?></pubDate>
            <author><?php echo $user->username ?>@rpi.edu (<?php echo htmlspecialchars(utf8_encode($user->name)) ?>)</author>
            <guid isPermaLink="false"><?php echo 'http://' . $_SERVER['SERVER_NAME'] . ADMIN_URL ?>/content/show/<?php echo $content->id ?></guid>
<?php          foreach($feeds as $feed_obj){
                if($feed_obj['moderation_flag'] == 1 && $feed_obj['feed']->type != 3){
                    $feed = $feed_obj['feed'];
                    $feed_link = 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria) . "&select=feed&select_id={$feed->id}&format=rss";
?>
            <category domain="<?php echo htmlspecialchars($feed_link) ?>"><?php echo htmlspecialchars($feed->name) ?></category>
<?php
                }
            }
            if(strpos($content->mime_type,'image') !== false){
?>
            <enclosure url="<?php echo htmlspecialchars($raw_link) ?>" type="<?php echo $content->mime_type ?>" length='0'/>
            <media:content url="<?php echo htmlspecialchars($raw_link) ?>" type="<?php echo $content->mime_type ?>" expression="full" />
            <media:title type="plain"><?php echo htmlspecialchars($content->name) ?></media:title>
            <media:thumbnail url="<?php echo htmlspecialchars($rss_link) ?>" width="100" height="100"/>
<?php
            }
?>
            <dcterms:valid>
                start=<?php echo w3date($content->start_time) ?>;
                end=<?php echo w3date($content->end_time) ?>;
                scheme=W3C-DTF
            </dcterms:valid>
        </item>
<?php  } ?>
    </channel>
</rss>
<?php
}

function render_raw($content_arr, $criteria){
    foreach($content_arr as $content){
        if(false===strpos($content->mime_type,'image')){
            echo "{$content->content}\n";
        } else {
            if(isset($criteria['width']) && isset($criteria['height'])){
                render('image', $content->content, $criteria['width'], $criteria['height']);
            } else {
                render('image', $content->content);
            }
        }
    }
}

function render_json($content_arr, $criteria){
    $data_arr = array();
    foreach($content_arr as $content){
        $content_dat = array();
        $content_dat['id'] = $content->id;
        $content_dat['title'] = $content->name;
        $content_dat['mime_type'] = $content->mime_type;
        $user = new User($content->user_id);
        if(false!==strpos($content->mime_type,'image')){
            $content_dat['content'] = $raw_link = 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria) . '&select=content&select_id='.$content->id.'&format=raw';
        } else {
            $content_dat['content'] = $content->content;
        }
        $content_dat['start_time'] = $content->start_time;
        $content_dat['end_time'] = $content->end_time;
        $content_dat['submitted'] = $content->submitted;
        $content_dat['user']['name'] = $user->name;
        $content_dat['user']['username'] = $user->username;
        $feeds = $content->list_feeds();
        foreach($feeds as $feed_obj){
            if($feed_obj['moderation_flag'] == 1 && $feed_obj['feed']->type != 3){
                $feed_dat = array();
                $feed = $feed_obj['feed'];
                $feed_link = 'http://' . $_SERVER['SERVER_NAME'] .  $_SERVER['SCRIPT_NAME'] . '?' . criteria_string($criteria) . "&select=feed&select_id={$feed->id}&format=rss";
                $feed_dat['name'] = $feed->name;
                $feed_dat['id'] = $feed->id;
                $content_dat['feed'][] = $feed_dat;
            }
        }
        $data_arr[] = $content_dat;
    }
    if(isset($_REQUEST['callback']) && preg_match('/^[A-Za-z0-9_]+$/i',$_REQUEST['callback'])){
      $callback = $_REQUEST['callback'];
      echo $callback . '(' . json_encode($data_arr) . ')';
    }  else {
      echo json_encode($data_arr);
    }
}

function criteria_string($criteria, $case = ''){
    $crit_vals = array();
    $crits = array('api', 'width', 'height');
    if($case == 'rss'){
        $criteria['width'] = 100;
        $criteria['height'] = 100;
    }
    foreach($crits as $crit){
        if(isset($criteria[$crit])){
            $crit_arr[] = "$crit={$criteria[$crit]}";
        }
    }
    if(is_array($crit_arr)){
        return implode('&', $crit_arr);
    } else {
        return "";
    }
}

function system_info(){
    header("Content-type: text/xml");
    echo '<?xml version="1.0"?>';
    $sql = "SELECT id, name FROM feed WHERE type != 3";
    $res = sql_query($sql);
    $i=0;
?>

<systeminfo>
  <feeds>
<?php
    while($row = sql_row_keyed($res, $i)){
?>
    <feed>
        <id><?php echo $row['id'] ?></id>
        <name><?php echo htmlspecialchars($row['name']) ?></name>
    </feed>
<?php
        $i++;
    }
?>
  </feeds>
<?php
    $sql = "SELECT name FROM type";
    $res = sql_query($sql);
    $i = 0;
?>
  <types>
<?php
    while($row = sql_row_keyed($res, $i)){
?>
    <type><?php echo htmlspecialchars($row['name']) ?></type>
<?php
        $i++;
    }
?>
  </types>
</systeminfo>
<?php
}

//Function to generate W3-DTF friendly date.  Complies with some RFC
function w3date($date){
  $newdate = strtotime($date);
  
 //returns the date ready for the rss feed
 $date = date('Y-m-d', $newdate) . 'T' . date('g:i:s+T',$newdate);
 return $date;
}

//Function to generate an RSS friendly date.  Complies with some RFC
function rssdate($date){
  $newdate = strtotime($date);
  
 //returns the date ready for the rss feed
 $date = date('D, d M Y H:i:s T',$newdate);
 return $date;
}
?>
