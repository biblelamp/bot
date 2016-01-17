<?php
// Images Processor Bot
// version 0.3 last update Jan-17-2016
// not less PHP 5.2.1 (file_put_contents(), sys_get_temp_dir())

// script's modes

$clear_mode = "clear";
$shedule_mode = "shedule";
$download_mode = "download";
$resize_mode = "resize";
$list_mode = "list";

// for queues

$db_name = "bot_queues.sqlite";
$queue_download = "download";
$queue_failed = "failed";
$queue_resize = "resize";
$queue_done = "done";

// new size for images

$new_width = $new_height = 640;

// messages

$use_messages = PHP_EOL . 'Use: bot (' .
	$clear_mode . '|' .
	$shedule_mode . '|' .
	$download_mode . '|' .
	$resize_mode . '|' .
	$list_mode . ')' . PHP_EOL;
$use_shedule_message = PHP_EOL . 'Use: bot shedule file_with_links.txt' . PHP_EOL;
$error_open_file = 'Error: failed to open file ';
$error_file_not_exists = 'File not exists';
$urls_processed = ' url(s) processed';
$dl_queue_empty = PHP_EOL . "Download queue is empty" . PHP_EOL;
$files_download = ' file(s) downloaded to ';
$rs_queue_empty = PHP_EOL . "Resize queue is empty" . PHP_EOL;
$files_resized = ' file(s) resized. Use: bot list';

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

$db = new PDO('sqlite:' . dirname(__FILE__) . DIRECTORY_SEPARATOR . $db_name);
$db->exec('CREATE TABLE IF NOT EXISTS ' . $queue_download . ' (url TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS ' . $queue_failed . ' (url TEXT NOT NULL, status VARCHAR(25) NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS ' . $queue_resize . ' (path TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS ' . $queue_done . ' (path TEXT NOT NULL)');

switch ($argv[1]) {

	// clear mode

	case $clear_mode:
		$db->exec('DELETE FROM ' . $queue_download);
		$db->exec('DELETE FROM ' . $queue_failed);
		$db->exec('DELETE FROM ' . $queue_resize);
		$db->exec('DELETE FROM ' . $queue_done);
		break;

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
				$db->exec('INSERT INTO ' . $queue_download . ' (url) VALUES (\'' . $img_url->url . '\')');
			} else {
				$db->exec('INSERT INTO ' . $queue_failed . ' (url, status) VALUES (\'' . $img_url->url . '\', \'' . $img_url->status . '\')');
            }
		}
		echo PHP_EOL . count($Urls). $urls_processed . PHP_EOL;
		break;

    // download mode

    case $download_mode:
        $counter = 0;
		$Results = $db->query('SELECT * FROM ' . $queue_download)->fetchAll();
		if (!$Results) {
			die ($dl_queue_empty);
		}
        foreach($Results as $url) {
            $img_url = new urlClass($url['url']);
			if ($img_url->isURLexists()) {
                $image = new imgClass();
                $image->load($img_url->url);
                $image->save(sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($img_url->url));
				$db->exec('INSERT INTO ' . $queue_resize . ' (path) VALUES (\'' . sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($img_url->url) . '\')');
                $counter++;
            } else {
                $db->exec('INSERT INTO ' . $queue_failed . ' (url, status) VALUES (\'' . $img_url->url . '\', \'' . $img_url->status . '\')');
            }
        }
        echo PHP_EOL . $counter. $files_download . sys_get_temp_dir() . PHP_EOL;
        $db->exec('DELETE FROM ' . $queue_download);
        break;

    // resize mode

    case $resize_mode:
		$counter = 0;
		$Results = $db->query('SELECT * FROM ' . $queue_resize)->fetchAll();
		if (!$Results) {
			die ($rs_queue_empty);
		}
        foreach($Results as $img) {
            if (file_exists($img['path'])) {
                $image = new imgClass();
                $image->load($img['path']);
                $image->resize($new_width, $new_height);
                $image->save(basename($img['path']));
				$db->exec('INSERT INTO ' . $queue_done . ' (path) VALUES (\'' . basename($img['path']) . '\')');
                $counter++;
            } else {
				$db->exec('INSERT INTO ' . $queue_failed . ' (url, status) VALUES (\'' . $img['path'] . '\', \'' . $error_file_not_exists . '\')');
            }
        }
        echo PHP_EOL . $counter . $files_resized . PHP_EOL;
        $db->exec('DELETE FROM ' . $queue_resize);
        break;

	// list mode

	case $list_mode:
		$Results = $db->query('SELECT * FROM ' . $queue_download)->fetchAll();
		if ($Results) {
			echo PHP_EOL . '::' . $queue_download . PHP_EOL;
		}
		foreach ($Results as $row) {
			echo $row['url'] . PHP_EOL;
		}
		$Results = $db->query('SELECT * FROM ' . $queue_failed)->fetchAll();
		if ($Results) {
			echo PHP_EOL . '::' . $queue_failed . PHP_EOL;
		}
		foreach ($Results as $row) {
			echo $row['url'] . ' ' . $row['status'] . PHP_EOL;
		}
		$Results = $db->query('SELECT * FROM ' . $queue_resize)->fetchAll();
		if ($Results) {
			echo PHP_EOL . '::' . $queue_resize . PHP_EOL;
		}
		foreach ($Results as $row) {
			echo $row['path'] . PHP_EOL;
		}
		$Results = $db->query('SELECT * FROM ' . $queue_done)->fetchAll();
		if ($Results) {
			echo PHP_EOL . '::' . $queue_done . PHP_EOL;
		}
		foreach ($Results as $row) {
			echo $row['path'] . PHP_EOL;
		}
		break;

    default:
        die($use_messages);
}