<?php
// Images Processor Bot
// version 0.4 last update Jan-19-2016
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

// class for queues

class queClass {
    private $db;
    private $queue_name;
    private $field_name;
    public function __construct($db, $name, $field, $type) {
        $this->db = $db;
        if ((is_array($field))&&(is_array($type))) {
            for($idx = 0; $idx < count($field); $idx++) {
                $type[$idx] = $field[$idx] . ' ' . $type[$idx];
            }
            $field_type = implode(',', $type);
            $field = implode(',', $field);
        } else {
            $field_type = $field . ' ' . $type;
        }
        $this->queue_name = $name;
        $this->field_name = $field;
        $db->exec('CREATE TABLE IF NOT EXISTS ' . $this->queue_name . ' (' . $field_type . ')');
    }
    public function add($value) {
        if (is_array($value)) {
            foreach ($value as &$v) {
                $v = '\'' . $v . '\'';
            }
            $value = implode(',', $value);
        } else {
            $value = '\'' . $value . '\'';
        }
        $this->db->exec('INSERT INTO ' . $this->queue_name . ' (' . $this->field_name . ') VALUES (' . $value . ')');
    }
    public function select() {
        return $this->db->query('SELECT * FROM ' . $this->queue_name)->fetchAll();
    }
    public function show() {
    	$Results = $this->select();
		if ($Results) {
			echo PHP_EOL . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $this->queue_name . PHP_EOL;
		}
		foreach ($Results as $row) {
            $field_names = explode(',', $this->field_name);
            foreach($field_names as $name) {
                echo $row[$name] . ' ';
            }
			echo PHP_EOL;
		}
    }
    public function clear() {
        $this->db->exec('DELETE FROM ' . $this->queue_name);
    }
}

// main procedure begin

if ($argc == 1) {
    die($use_messages);
}

$db = new PDO('sqlite:' . dirname(__FILE__) . DIRECTORY_SEPARATOR . $db_name);
$queueDownload = new queClass($db, $queue_download, 'url', 'TEXT NOT NULL');
$queueFailed = new queClass($db, $queue_failed, array('url', 'status'), array('TEXT NOT NULL', 'VARCHAR(25) NOT NULL'));
$queueResize = new queClass($db, $queue_resize, 'path', 'TEXT NOT NULL');
$queueDone = new queClass($db, $queue_done, 'path', 'TEXT NOT NULL');

switch ($argv[1]) {

	// clear mode

	case $clear_mode:
		$queueDownload->clear();
        $queueFailed->clear();
		$queueResize->clear();
		$queueDone->clear();
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
                $queueDownload->add($img_url->url);
			} else {
                $queueFailed->add(array($img_url->url, $img_url->status));
            }
		}
		echo PHP_EOL . count($Urls). $urls_processed . PHP_EOL;
		break;

    // download mode

    case $download_mode:
        $counter = 0;
        $Results = $queueDownload->select();
		if (!$Results) {
			die ($dl_queue_empty);
		}
        foreach($Results as $url) {
            $img_url = new urlClass($url['url']);
			if ($img_url->isURLexists()) {
                $image = new imgClass();
                $image->load($img_url->url);
                $image->save(sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($img_url->url));
                $queueResize->add(sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($img_url->url));
                $counter++;
            } else {
                $queueFailed->add(array($img_url->url, $img_url->status));
            }
        }
        echo PHP_EOL . $counter. $files_download . sys_get_temp_dir() . PHP_EOL;
        $queueDownload->clear();
        break;

    // resize mode

    case $resize_mode:
		$counter = 0;
		$Results = $queueResize->select();
		if (!$Results) {
			die ($rs_queue_empty);
		}
        foreach($Results as $img) {
            if (file_exists($img['path'])) {
                $image = new imgClass();
                $image->load($img['path']);
                $image->resize($new_width, $new_height);
                $image->save(basename($img['path']));
                $queueDone->add(basename($img['path']));
                $counter++;
            } else {
                $queueFailed->add(array($img['path'], $error_file_not_exists));
            }
        }
        echo PHP_EOL . $counter . $files_resized . PHP_EOL;
        $queueResize->clear();
        break;

	// list mode

	case $list_mode:
        $queueDownload->show();
        $queueFailed->show();
        $queueResize->show();
        $queueDone->show();
		break;

    default:
        die($use_messages);
}