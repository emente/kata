<?php
/**
 * @package kata
 */






/**
 * contains thumbnail utility class
 * @package kata
 */

/**
 * routines to resize images and handle downloads
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class ImageUtility {

	const IMGERR_TOOBIG = 1;
	const IMGERR_TOOSMALL = 2;
	const IMGERR_UNKNOWNFORMAT = 4;

	const UPL_TOOBIG = 1; //file too big
	const UPL_INTERNAL = 2; //internal error
	const UPL_EXTENSION = 3; //file extension not allowed

	private $imageQuality = 95;

	/**
	 * read a gif,jpg,png from disk and return image
	 * @param string $filename
	 * @return image
	 */
	function read($filename) {
		if (!file_exists($filename)) {
			return false;
		}

		$img = false;
		$temp = getimagesize($filename);
		switch ($temp[2]) {
			case IMAGETYPE_GIF :
				$img = imagecreatefromgif($filename);
				break;
			case IMAGETYPE_JPEG :
				$img = imagecreatefromjpeg($filename);
				break;
			case IMAGETYPE_PNG :
				$img = imagecreatefrompng($filename);
				break;
		}

		return $img;
	}

	/**
	 * write the given image to disk
	 * @param image $img
	 * @param int $type imagetype, IMAGETYPE_GIF usw.
	 * @param string $filename
	 */
	function write($img, $type, $filename) {
		switch ($type) {
			case IMAGETYPE_GIF :
				return imagegif($img, $filename);
				break;
			case IMAGETYPE_JPEG :
				return imagejpeg($img, $filename, $this->imageQuality);
				break;
			case IMAGETYPE_PNG :
				return imagepng($img, $filename, 9, PNG_ALL_FILTERS);
				break;
			default :
				throw new Exception('write: Uknown image type');
		}
	}

	/**
	 * set image write quality. 0-100, regardless of imagetype
	 * @param int $quality 0=worst 100=best
	 */
	function setQuality($quality) {
		$this->imageQuality=max(0,min($quality,100));
	}


	/**
	 * simply create a thumbnail, dont make it proportional
	 * @param image $image
	 * @param int $width
	 * @param int $height
	 */
	function makeThumbnail($image, $width, $height) {
		$thumb = imagecreatetruecolor($width, $height);
		imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
		return $thumb;
	}

	/**
	 * enshure picture fits proportionally into the destionation size. this
	 * could possibly result in the picture being cropped
	 * @param image $image
	 * @param int $max_x target width
	 * @param int $max_y target height
	 */
	function makeThumbnailProportional($image, $max_x, $max_y) {
		$width = $max_x;
		$height = $max_y;

		$width_orig = imagesx($image);
		$height_orig = imagesy($image);

		if ($width_orig < $height_orig) {
			$height = ($max_x / $width_orig) * $height_orig;
		} else {
			$width = ($max_y / $height_orig) * $width_orig;
		}

		if ($width < $max_x) {
			$width = $max_x;
			$height = ($max_x / $width_orig) * $height_orig;
		}

		if ($height < $max_y) {
			$height = $max_y;
			$width = ($max_y / $height_orig) * $width_orig;
		}

		// first cutout the region we want to use, otherwise thumbs for different sizes look different
		$thumb = imagecreatetruecolor($width, $height);
		imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

		// then scale this region to the wanted size
		$thumb2 = imagecreatetruecolor($max_x, $max_y);
		$w1 = ($width / 2) - ($max_x / 2);
		$h1 = ($height / 2) - ($max_y / 2);
		imagecopyresampled($thumb2, $thumb, 0, 0, $w1, $h1, $max_x, $max_y, $max_x, $max_y);

		return $thumb2;
	}

/**
 * does the image conform to given parameters?
 * @param string $filename ...
 * @param int $maxwidth
 * @param int $maxheight
 * @param int minwidth
 * @param int minheight
 * @param mixed IMGERR_ codes
 */
	function isImageInvalid($filename, $maxwidth, $maxheight, $minwidth = 1, $minheight = 1) {
		list ($width, $height, $type, $attr) = getimagesize($filename);

		if (($type != IMAGETYPE_GIF) && ($type != IMAGETYPE_JPEG) && ($type != IMAGETYPE_PNG)) {
			return ImageUtility :: IMGERR_UNKNOWNFORMAT;
		}
		if ($width > $maxwidth) {
			return ImageUtility :: IMGERR_TOOBIG;
		}
		if ($height > $maxheight) {
			return ImageUtility :: IMGERR_TOOBIG;
		}
		if ((false !== $minheight) && ($height < $minheight)) {
			return ImageUtility :: IMGERR_TOOSMALL;
		}
		if ((false !== $minwidth) && ($width < $minwidth)) {
			return ImageUtility :: IMGERR_TOOSMALL;
		}

		return false;
	}


/**
 * reduce several upload-errors to 3 general ones
 * @param string $fieldname name="" of the upload-input-element
 * @return mixed true=success, false=no file given, UPLOAD_ERR_
 */
	function simpleUploadError($fieldname) {
		if (isset ($_FILES[$fieldname]['name']) && (empty ($_FILES[$fieldname]['error']))) {
			if (is_uploaded_file($_FILES[$fieldname]['tmp_name'])) {
				return true;
			}
		}


		switch ($_FILES[$fieldname]['error']) {
			case UPLOAD_ERR_FORM_SIZE :
			case UPLOAD_ERR_INI_SIZE :
				return ImageUtility :: UPL_TOOBIG;
				break;

			case UPLOAD_ERR_PARTIAL :
			case UPLOAD_ERR_NO_TMP_DIR :
			case UPLOAD_ERR_CANT_WRITE :
				return ImageUtility :: UPL_INTERNAL;
				break;

			case UPLOAD_ERR_EXTENSION :
				return ImageUtility :: UPL_EXTENSION;
				break;

			case UPLOAD_ERR_NO_FILE :
			default :
				return false;
				break;
		}
	}

        /**
         * @var white skin color tone
         */
        public $whiteSkin = 0x793B24;
        /**
         * @var black skin color tone
         */
        public $blackSkin = 0xFEC5BF;

/**
 * try to classify how likely the picture contains nudity.
 * warning: O(scary) (read: can take several seconds depending for big pictures)
 *
 * @param resource $img image-resource of the picture to classify
 * @return float 0=no nudity 100=full frontal nudity ^^
 */
    function getNudity($img)
    {
        if(!$img) return false;

        $x = imagesx($img)-1;
        $y = imagesy($img)-1;
        $score = 0;
	    $arA = array();
	    $arB = array();

        $arA['R'] = ($this->whiteSkin >> 16) & 0xFF;
        $arA['G'] = ($this->whiteSkin >> 8) & 0xFF;
        $arA['B'] = $this->whiteSkin & 0xFF;

        $arB['R'] = ($this->blackSkin >> 16) & 0xFF;
        $arB['G'] = ($this->blackSkin >> 8) & 0xFF;
        $arB['B'] = $$this->blackSkin & 0xFF;

        $xPoints = array($x/8, $x/4, ($x/8 + $x/4), $x-($x/8 + $x/4), $x-($x/4), $x-($x/8));
        $yPoints = array($y/8, $y/4, ($y/8 + $y/4), $y-($y/8 + $y/4), $y-($y/8), $y-($y/8));
        $zPoints = array($xPoints[2], $yPoints[1], $xPoints[3], $y);

        for($i=1; $i<=$x; $i++) {
            for($j=1; $j<=$y; $j++) {
                $color = imagecolorat($img, $i, $j);
                if($color >= $this->whiteSkin && $color <= $this->blackSkin) {
                    $color = array('R'=> ($color >> 16) & 0xFF, 'G'=> ($color >> 8) & 0xFF, 'B'=> $color & 0xFF);
                    if($color['G'] >= $arA['G'] && $color['G'] <= $arB['G'] && $color['B'] >= $arA['B'] && $color['B'] <= $arB['B']) {
                        if($i >= $zPoints[0] && $j >= $zPoints[1] && $i <= $zPoints[2] && $j <= $zPoints[3]) {
                            $score += 3;
                        } elseif($i <= $xPoints[0] || $i >=$xPoints[5] || $j <= $yPoints[0] || $j >= $yPoints[5]) {
                            $score += 0.10;
                        } elseif($i <= $xPoints[0] || $i >=$xPoints[4] || $j <= $yPoints[0] || $j >= $yPoints[4]) {
                            $score += 0.40;
                        } else {
                            $score += 1.50;
                        }
                    }//score
                }//colorinrange
            }//forj
        }//fori

        $score = min(100,round( ($score * 100) / ($x * $y) , 2));
        return $score;
    }

}
