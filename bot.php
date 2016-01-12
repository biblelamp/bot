<?php
// Images Processor Bot
// version 0.1 last update Jan-11-2016
// not less PHP 5.2.1 (file_put_contents(), sys_get_temp_dir())

// script's modes

$shedule_mode = "shedule";
$download_mode = "download";
$resize_mode = "resize";

// files for queues

$queue_download = "download.txt";
$queue_failed = "failed.txt";
$queue_resize = "resize.txt";
$queue_done = "done.txt";

// new size for images

$new_width = $new_height = 640;

// messages

$use_messages = PHP_EOL . 'Use: bot (' . $shedule_mode . '|' . $download_mode . '|' . $resize_mode . ')' . PHP_EOL;
$use_shedule_message = PHP_EOL . 'Use: bot shedule file_with_links.txt' . PHP_EOL;
$error_open_file = 'Error: failed to open file ';
$urls_processed = ' url(s) processed';
$dl_queue_empty = PHP_EOL . "Download queue is empty" . PHP_EOL;
$files_download = ' file(s) download to ';
$rs_queue_empty = PHP_EOL . "Resize queue is empty" . PHP_EOL;
$files_resized = ' file(s) resized. See done.txt';

// there are arguments?

if ($argc == 1) {
    die($use_messages);
}
// main procedure

switch ($argv[1]) {

    // shedule mode

    case $shedule_mode:
        if ($argc < 3) {
            die($use_shedule_message);
        }
		if (!file_exists($argv[2])) {
            die(PHP_EOL . $error_open_file . $argv[2] . PHP_EOL);
		}
		$Urls = file($argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach($Urls as $url) {
		    $Headers = @get_headers($url);
			if (strpos($Headers[0], '200')) {
                file_put_contents($queue_download, $url . "\n", FILE_APPEND | LOCK_EX);
			} else {
                file_put_contents($queue_failed, $url . ' ' . $Headers[0] . "\n", FILE_APPEND | LOCK_EX);
            }
		}
		echo PHP_EOL . count($Urls). $urls_processed . PHP_EOL;
		break;

    // download mode

    case $download_mode:
        if (!file_exists($queue_download)) {
            die ($dl_queue_empty);
        }
        $counter = 0;
        $Urls = file($queue_download, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($Urls as $url) {
            $Headers = @get_headers($url);
            if (strpos($Headers[0], '200')) {
                $image = file_get_contents($url);
                file_put_contents(sys_get_temp_dir() . '/' . basename($url), $image);
                file_put_contents($queue_resize, sys_get_temp_dir() . '/' . basename($url) . "\n", FILE_APPEND | LOCK_EX);
                $counter++;
            } else {
                file_put_contents($queue_failed, $url . ' ' . $Headers[0] . "\n", FILE_APPEND | LOCK_EX);
            }
        }
        echo PHP_EOL . $counter. $files_download . sys_get_temp_dir() . PHP_EOL;
        unlink($queue_download);
        break;

    // resize mode

    case $resize_mode:
            if (!file_exists($queue_resize)) {
            die ($rs_queue_empty);
        }
        $counter = 0;
        $Images = file($queue_resize, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach($Images as $img) {
            if (file_exists($img)) {
                list($width, $height) = getimagesize($img);
                // to keep the image proportions
                $mod_width = $mod_height = $new_height;
                if ($width != $height) {
                    if ($width > $height) {
                        $mod_height = $new_width / $width * $height;
                    } else {
                        $mod_width = $new_height / $height * $width;
                    }
                }
                $new_img = imagecreatetruecolor($new_width, $new_height);
                $white = imagecolorallocate($new_img, 255, 255, 255);
                imagefill($new_img, 0, 0, $white);
                $source_img = imagecreatefromjpeg($img);
                imagecopyresized($new_img, $source_img, 0, 0, 0, 0, $mod_width, $mod_height, $width, $height);
                imagejpeg($new_img, basename($img));
                file_put_contents($queue_done, basename($img) . "\n", FILE_APPEND | LOCK_EX);
                $counter++;
            } else {
                file_put_contents($queue_failed, $img . "\n", FILE_APPEND | LOCK_EX);
            }
        }
        echo PHP_EOL . $counter. $files_resized . PHP_EOL;
        unlink($queue_resize);
        break;

    default:
        die($use_messages);
}