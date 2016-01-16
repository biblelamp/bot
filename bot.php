<?php
// Images Processor Bot
// version 0.2 last update Jan-16-2016
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
$files_download = ' file(s) downloaded to ';
$rs_queue_empty = PHP_EOL . "Resize queue is empty" . PHP_EOL;
$files_resized = ' file(s) resized. See done.txt';

// class for URLs

class urlClass {
    public $url;
    public $status;
    public function __construct($url) {
        $this->url = $url;
    }
    public function getStatus() {
        $Headers = @get_headers($this->url);
        $this->status = $Headers[0];
    }
    public function isURLexists() {
        $this->getStatus();
        return (strpos($this->status, '200') ? true : false);
    }
}

// class for images

class imgClass {
    private $image;
    private $width;
    private $height;
    private $image_type;
    public function load($filename) {
        $image_info = getimagesize($filename);
        list($this->width, $this->height, $this->image_type) = getimagesize($filename);
        switch ($this->image_type) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($filename);
                return true;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($filename);
                return true;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($filename);
                return true;
            default:
                return false;
        }
    }
    public function save($filename) {
        switch ($this->image_type) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image, $filename);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->image,$filename);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->image,$filename);
        }
    }
    public function getWidth() {
      return $this->width;
   }
    public function getHeight() {
      return $this->height;
   }
    public function resize($width, $height) {
        $new_width = $width;
        $new_height = $height;
        if (($width / $height) != ($this->getWidth() / $this->getHeight())) {
            if ($this->getWidth() > $this->getHeight()) {
                $new_height = $width / $this->getWidth() * $this->getHeight();
            } else {
                $new_width = $height / $this->getHeight() * $this->getWidth();
            }
        }
        $new_img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($new_img, 255, 255, 255);
        imagefill($new_img, 0, 0, $white);
        imagecopyresized($new_img, $this->image, 0, 0, 0, 0, $new_width, $new_height, $this->getWidth(), $this->getHeight());
        $this->image = $new_img;
    }
}

// main procedure begin

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
            $img_url = new urlClass($url);
			if ($img_url->isURLexists()) {
                file_put_contents($queue_download, $img_url->url . "\n", FILE_APPEND | LOCK_EX);
			} else {
                file_put_contents($queue_failed, $img_url->url . ' ' . $img_url->getStatus() . "\n", FILE_APPEND | LOCK_EX);
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
            $img_url = new urlClass($url);
			if ($img_url->isURLexists()) {
                $image = new imgClass();
                $image->load($img_url->url);
                $image->save(sys_get_temp_dir() . '/' . basename($img_url->url));
                file_put_contents($queue_resize, sys_get_temp_dir() . '/' . basename($img_url->url) . "\n", FILE_APPEND | LOCK_EX);
                $counter++;
            } else {
                file_put_contents($queue_failed, $img_url->url . ' ' . $img_url->getStatus() . "\n", FILE_APPEND | LOCK_EX);
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
        foreach($Images as $img_path) {
            if (file_exists($img_path)) {
                $image = new imgClass();
                $image->load($img_path);
                $image->resize($new_width, $new_height);
                $image->save(basename($img_path));
                file_put_contents($queue_done, basename($img_path) . "\n", FILE_APPEND | LOCK_EX);
                $counter++;
            } else {
                file_put_contents($queue_failed, $img_path . "\n", FILE_APPEND | LOCK_EX);
            }
        }
        echo PHP_EOL . $counter . $files_resized . PHP_EOL;
        unlink($queue_resize);
        break;

    default:
        die($use_messages);
}