<?php 

/**
 * XBMC 12(frodo) Redownload all Thumbnails and Fanarts when updating db from 11(eden)
 * This script update posters and backdrops url list im XMBC mysql movie db from 
 * themoviedb.org scraper
 */

/**
 * TMDb api php class by glamorous
 * @link https://github.com/glamorous/TMDb-PHP-API
 */
require_once 'TMDb.php';

$tmdb_api_key = '';         # to be found in themoviedb account
$tmdb_lang = 'en';          # 
$db_host = 'localhost';     # mysql host
$db_user = '';              # mysql password
$db_name = 'xbmc_video60';  # xbmc_video60 is v.11 eden db name
$limit = 10;                # thumbnails, posters limit becouse somtimes tmdb have a lot of it
$preview = false;           # preview first poster of the movie 

######################################################################################################

$tmdb = new TMDb($tmdb_api_key, $tmdb_lang);

$image_array = array(
    'backdrops' => array('type' => TMDb::IMAGE_BACKDROP, 'field' => 'c20', 'preview_size' => 'w780', 'limit' => $limit),
    'posters' => array('type' => TMDb::IMAGE_POSTER, 'field' => 'c08', 'preview_size' => 'w500', 'limit' => $limit)
);

if (( $db = new mysqli($db_host, $db_user, $db_name) ) AND $db->select_db($db_name)) {
    $result = $db->query("SELECT `idMovie`,`c00`,`c09` FROM `movie` ORDER BY `idMovie` DESC");
    while ($row = $result->fetch_assoc()) {
        $movie_id = $row['c09'];
        if ($movie_id AND substr($movie_id, 0, 2) == 'tt') {
            $movie_images = $tmdb->getMovieImages($movie_id);
            if (!$movie_images['backdrops'] OR !$movie_images['posters']) {
                $_images = $tmdb->getMovieImages($movie_id, false);
                if (!$movie_images['backdrops'] AND $_images['backdrops']) {
                    $movie_images['backdrops'] = $_images['backdrops'];
                }
                if (!$movie_images['posters'] AND $_images['posters']) {
                    $movie_images['posters'] = $_images['posters'];
                }
            }
            if ($movie_images) {
                $update = array('backdrops' => '', 'posters' => '');
                foreach ($image_array as $field => $image_type) {
                    if (!empty($movie_images[$field])) {
                        foreach ($movie_images[$field] as $key => $foto) {
                            $preview_url = $tmdb->getImageUrl($foto['file_path'], $image_type['type'], $image_type['preview_size']);
                            $orginal_url = $tmdb->getImageUrl($foto['file_path'], $image_type['type'], 'original');
                            $update[$field] .= '<thumb preview="' . $preview_url . '" >' . $orginal_url . '</thumb>';
                            if (($key + 1) >= $image_type['limit']) {
                                break;
                            }
                        }
                    }
                }
                $update['backdrops'] .= $update['backdrops'] ? '<fanart>' . $update['backdrops'] . '</fanart>' : '';
                if ($update['backdrops'] OR $update['posters']) {
                    $update_query = '';
                    foreach ($image_array as $field => $image_type) {
                        if ($update_query) {
                            $update_query .= ', ';
                        }
                        if (!empty($update[$field])) {
                            $update_query .= " `" . $image_type['field'] . "` = '" . mysqli_real_escape_string($db, $update[$field]) . "' ";
                        }
                    }
                    if ($update_query) {
                        $update_query = "UPDATE `movie` SET " . $update_query 
                                . " WHERE `idMovie` = '" . mysqli_real_escape_string($db, $row['idMovie']) . "'";
                        if (!empty($movie_images['posters'])) {
                            echo '<div>' . $row['c00'] . '</div>';
                            $image_url = $tmdb->getImageUrl($movie_images['posters'][0]['file_path'], TMDb::IMAGE_POSTER, 'w92');
                            echo '<div><img src="' . $image_url . '" /></div>';
                        }
                        echo $update_query . PHP_EOL;
                        $db->query($update_query);
                    }
                }
            }
            sleep(1); #tmdb api have a request limit
        }
    }
} else {
    echo 'CONECTION ERROR' . PHP_EOL;
}
