<?php
class imageProcessor {

    public $quality = 80; //Default quality
    protected $image, $filename,$outputDir, $original_info, $width, $height, $imagestring;

    function __construct($outputDir = null) {
        if ($outputDir) {
			$this->outputDir = $outputDir ."/";
			if (!is_dir($outputDir)) {
				mkdir($outputDir);
			}
		}
    }

    function __destruct() {
        if( $this->image !== null && get_resource_type($this->image) === 'gd' ) {
            imagedestroy($this->image);
        }
    }

    ###############
    # LOAD
    ###############
    function load($filename) {
        if (!extension_loaded('gd')) {
            throw new Exception('Required extension GD is not loaded.');
        }
        $this->filename = $filename;
        return $this->get_meta_data();
    }

    ###############
    # OUTPUT
    ###############
    function output($format = null, $quality = null) {
        $quality = $quality ?: $this->quality;

        // Determine mimetype
        switch (strtolower($format)) {
            case 'gif':
                $mimetype = 'image/gif';
                break;
            case 'jpeg':
            case 'jpg':
                imageinterlace($this->image, true);
                $mimetype = 'image/jpeg';
                break;
            case 'png':
                $mimetype = 'image/png';
                break;
            default:
                $info = (empty($this->imagestring)) ? getimagesize($this->filename) : getimagesizefromstring($this->imagestring);
                $mimetype = $info['mime'];
                unset($info);
                break;
        }

        // Output the image
        header('Content-Type: '.$mimetype);
        switch ($mimetype) {
            case 'image/gif':
                imagegif($this->image);
                break;
            case 'image/jpeg':
                imageinterlace($this->image, true);
                imagejpeg($this->image, null, round($quality));
                break;
            case 'image/png':
                imagepng($this->image, null, round(9 * $quality / 100));
                break;
            default:
                throw new Exception('Unsupported image format: '.$this->filename);
                break;
        }
    }
    
    ###############
    # SAVE
    ###############
    function save($filename = null, $quality = null, $format = null) {
        $quality = $quality ?: $this->quality;
        $filename = $this->outputDir . ($filename ?: basename($this->filename));
        $filename .= $this->file_ext($filename) ? "": ".".$this->original_info['format'];
        if( !$format ) {
            $format = $this->file_ext($filename) ?: $this->original_info['format'];
        }

        // Create the image
        switch (strtolower($format)) {
            case 'gif':
                $result = imagegif($this->image, $filename);
                break;
            case 'jpg':
            case 'jpeg':
                imageinterlace($this->image, true);
                $result = imagejpeg($this->image, $filename, round($quality));
                break;
            case 'png':
                $result = imagepng($this->image, $filename, round(9 * $quality / 100));
                break;
            default:
                throw new Exception('Unsupported format');
        }

        if (!$result) {
            throw new Exception('Unable to save image: ' . $filename);
        }
        return $this;
    }

	
    ###############
    # BLUR
    ###############
    function blur($type = 'selective', $passes = 1) {
        switch (strtolower($type)) {
            case 'gaussian':
                $type = IMG_FILTER_GAUSSIAN_BLUR;
                break;
            default:
                $type = IMG_FILTER_SELECTIVE_BLUR;
                break;
        }
        for ($i = 0; $i < $passes; $i++) {
            imagefilter($this->image, $type);
        }
        return $this;
    }

    ###############
    # BRIGHTNESS
    ###############
    function brightness($level) {
        imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $this->keep_within($level, -255, 255));
        return $this;
    }

    ###############
    # CONTRAST
    ###############
    function contrast($level) {
        imagefilter($this->image, IMG_FILTER_CONTRAST, $this->keep_within($level, -100, 100));
        return $this;
    }

    ###############
    # CROP
    ###############
    function crop($x1, $y1, $x2, $y2) {

        // Determine crop size
        if ($x2 < $x1) {
            list($x1, $x2) = array($x2, $x1);
        }
        if ($y2 < $y1) {
            list($y1, $y2) = array($y2, $y1);
        }
        $crop_width = $x2 - $x1;
        $crop_height = $y2 - $y1;

        // Perform crop
        $new = imagecreatetruecolor($crop_width, $crop_height);
        imagealphablending($new, false);
        imagesavealpha($new, true);
        imagecopyresampled($new, $this->image, 0, 0, $x1, $y1, $crop_width, $crop_height, $crop_width, $crop_height);

        // Update meta data
        $this->width = $crop_width;
        $this->height = $crop_height;
        $this->image = $new;

        return $this;

    }

    ###############
    # FLIP
    ###############
    function flip($direction) {
        $new = imagecreatetruecolor($this->width, $this->height);
        imagealphablending($new, false);
        imagesavealpha($new, true);
        switch (strtolower($direction)) {
            case 'y':
                for ($y = 0; $y < $this->height; $y++) {
                    imagecopy($new, $this->image, 0, $y, 0, $this->height - $y - 1, $this->width, 1);
                }
                break;
            default:
                for ($x = 0; $x < $this->width; $x++) {
                    imagecopy($new, $this->image, $x, 0, $this->width - $x - 1, 0, 1, $this->height);
                }
                break;
        }
        $this->image = $new;
        return $this;
    }

    ###############
    # GET HEIGHT
    ###############
    function get_height() {
        return $this->height;
    }
    
    ###############
    # GET WIDTH
    ###############
    function get_width() {
        return $this->width;
    }

    ###############
    # GET ORIENTATION
    ###############
    function get_orientation() {
        if (imagesx($this->image) > imagesy($this->image)) {
            return 'landscape';
        }
        if (imagesx($this->image) < imagesy($this->image)) {
            return 'portrait';
        }
        return 'square';
    }

    ###############
    # INVERT
    ###############
    function invert() {
        imagefilter($this->image, IMG_FILTER_NEGATE);
        return $this;
    }

    ###############
    # RESIZE
    ###############
    function resize($width, $height) {
        $new = imagecreatetruecolor($width, $height);
        if( $this->original_info['format'] === 'gif' ) {
            // Preserve transparency in GIFs
            $transparent_index = imagecolortransparent($this->image);
            $palletsize = imagecolorstotal($this->image);
            if ($transparent_index >= 0 && $transparent_index < $palletsize) {
                $transparent_color = imagecolorsforindex($this->image, $transparent_index);
                $transparent_index = imagecolorallocate($new, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($new, 0, 0, $transparent_index);
                imagecolortransparent($new, $transparent_index);
            }
        } else {
            // Preserve transparency in PNGs (benign for JPEGs)
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }

        // Resize
        imagecopyresampled($new, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

        // Update meta data
        $this->width = $width;
        $this->height = $height;
        $this->image = $new;
        return $this;
    }

    ###############
    # THUMBNAIL
    ###############
    function thumbnail($width, $height = null) {
        $height = $height ?: $width;

        // Determine aspect ratios
        $current_aspect_ratio = $this->height / $this->width;
        $new_aspect_ratio = $height / $width;

        // Fit to height/width
        if ($new_aspect_ratio > $current_aspect_ratio) {
            $this->fit_to_height($height);
        } else {
            $this->fit_to_width($width);
        }
        $left = floor(($this->width / 2) - ($width / 2));
        $top = floor(($this->height / 2) - ($height / 2));

        // Return trimmed image
        return $this->crop($left, $top, $width + $left, $height + $top);

    }

    ###############
    # GET EXTENSION
    ###############
    protected function file_ext($filename) {
        if (!preg_match('/\./', $filename)) {
            return '';
        }
        return preg_replace('/^.*\./', '', $filename);
    }

    ###############
    # GET INFORMATION
    ###############
    function get_info() {
        return $this->original_info;
    }   

    ###############
    # GET METADATA
    ###############
    protected function get_meta_data() {
        //gather meta data
        if(empty($this->imagestring)) {
            $info = getimagesize($this->filename);
            switch ($info['mime']) {
                case 'image/gif':
                    $this->image = imagecreatefromgif($this->filename);
                    break;
                case 'image/jpeg':
                    $this->image = imagecreatefromjpeg($this->filename);
                    break;
                case 'image/png':
                    $this->image = imagecreatefrompng($this->filename);
                    break;
                default:
                    throw new Exception('Invalid image: '.$this->filename);
                    break;
            }
        } elseif (function_exists('getimagesizefromstring')) {
            $info = getimagesizefromstring($this->imagestring);
        } else {
            throw new Exception('PHP 5.4 is required to use method getimagesizefromstring');
        }

        $this->original_info = array(
            'width' => $info[0],
            'height' => $info[1],
            'orientation' => $this->get_orientation(),
            'exif' => function_exists('exif_read_data') && $info['mime'] === 'image/jpeg' && $this->imagestring === null ? $this->exif = @exif_read_data($this->filename) : null,
            'format' => preg_replace('/^image\//', '', $info['mime']),
            'mime' => $info['mime']
        );
        $this->width = $info[0];
        $this->height = $info[1];
        imagesavealpha($this->image, true);
        imagealphablending($this->image, true);
        return $this;
    }

    ###############
    # HELPER // KEEP WITHIN
    ###############
    protected function keep_within($value, $min, $max) {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }
    
    ###############
    # HELPER // FIT TO HEIGHT
    ###############
    function fit_to_height($height) {
        $aspect_ratio = $this->height / $this->width;
        $width = $height / $aspect_ratio;
        return $this->resize($width, $height);
    }

    ###############
    # HELPER // FIT TO WIDTH
    ###############
    function fit_to_width($width) {
        $aspect_ratio = $this->height / $this->width;
        $height = $width * $aspect_ratio;
        return $this->resize($width, $height);
    }
}
