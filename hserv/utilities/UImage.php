<?php
namespace hserv\utilities;
use hserv\utilities\USanitize;
use hserv\utilities\USystem;

/**
* Image manipulation library
*
* createFromString - creates image with given text
* makeURLScreenshot - makes screenshot for given url
* getRemoteImage - downloads image from given url
* getImageFromFile - returns image object for given file
* changeImageColor - changes black color in given image to new one (for icons), adds circle and circle background
* getImageType - returns extension based of exif
* checkMemoryForImage - verifies if image can be loaded into memory
*
* safeLoadImage -  memory safe load from file to image object
* createScaledImageFile - creates thumbnail for given image file
* resizeImage - resizes given image
* getPdfThumbnail - creates thumbnail from pdf file
*
* getPrevailBackgroundColor - finds prevail background color
*
* private
* _resizeImageGD - creates scaled image with native GD php functions
* _parseColor
* _rgb2hex
*
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

//use Screen\Capture; //for micorweber

class UImage {

    /**
    * Creates image with given text
    *
    * @param mixed $desc - text to be inserted into resulted image
    * @return resource - image with the given text
    */
    public static function createFromString($desc) {
        $desc = preg_replace('/\\s+/', ' ', $desc);

        $font = 3; $fw = imagefontwidth($font); $fh = imagefontheight($font);
        $desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", false));
        $longlines = false;
        if (count($desc_lines) > intval(100/$fh)) {
            $longlines = true;
        } else {
            foreach ($desc_lines as $line) {
                if (strlen($line) >= intval(100/$fw)) {
                    $longlines = true;
                    break;
                }
            }
        }
        if ($longlines) {
            $font = 1; $fw = imagefontwidth($font); $fh = imagefontheight($font);
            $desc_lines = explode("\n", wordwrap($desc, intval(100/$fw)-1, "\n", true));
        }


        $im = imagecreate(100, 100);
        $white = imagecolorallocate($im, 255, 255, 255);
        $grey = imagecolorallocate($im, 160, 160, 160);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, 100, 100, $white);

        //imageline($im, 35, 25, 65, 75, $grey);
        imageline($im, 33, 25, 33, 71, $grey);
        imageline($im, 33, 25, 62, 25, $grey);
        imageline($im, 62, 25, 67, 30, $grey);
        imageline($im, 67, 30, 62, 30, $grey);
        imageline($im, 62, 30, 62, 25, $grey);
        imageline($im, 67, 30, 67, 71, $grey);
        imageline($im, 67, 71, 33, 71, $grey);

        $y = intval((100 - count($desc_lines)*$fh) / 2);
        foreach ($desc_lines as $line) {
            $x = intval((100 - strlen($line)*$fw) / 2);
            imagestring($im, $font, $x, $y, $line, $black);
            $y += $fh;
        }

        return $im;
    }


    /**
    * makes screenshot for given url (using either WEBSITE_THUMBNAIL_SERVICE
    * or Google PageSpeed Insights API
    *
    * @param mixed $url
    */
    public static function makeURLScreenshot($siteURL){

        if(!filter_var($siteURL, FILTER_VALIDATE_URL)){
            return array('error'=>'URL to generate snapshot '.$siteURL.' is not valid');
        }

        //$remote_path =  str_replace("[URL]", $sURL, WEBSITE_THUMBNAIL_SERVICE);
        $heurist_path = tempnam(HEURIST_SCRATCH_DIR, "_temp_");


        if(defined('WEBSITE_THUMBNAIL_SERVICE') && WEBSITE_THUMBNAIL_SERVICE!=''){

            $remote_path =  str_replace("[URL]", $siteURL, WEBSITE_THUMBNAIL_SERVICE);
            $filesize = saveURLasFile($remote_path, $heurist_path);//save url screenshot in tep file

            //check the dimension of returned thumbanil in case it is less than 50 - consider it as error
            if(strpos($remote_path, substr(WEBSITE_THUMBNAIL_SERVICE,0,24))==0){

                $image_info = getimagesize($heurist_path);
                if($image_info[1]<50){
                    //remove temp file
                    unlink($heurist_path);
                    return array('error'=>'Thumbnail generator service can\'t create the image for specified URL');
                }
            }

        }else{

            //call Google PageSpeed Insights API
            $googlePagespeedData = file_get_contents( //loadRemoteURLContent
                'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url='.$siteURL.'&screenshot=true');

            //decode json data
            $googlePagespeedData = json_decode($googlePagespeedData, true);

            //screenshot data

            //full-page-screenshot details screenshot data
            //screenshot-thumbnails details items[] data
            $screenshot = @$googlePagespeedData['lighthouseResult']['audits']['final-screenshot']['details']['data'];

            //$screenshot = str_replace(array('_','-'),array('/','+'),$screenshot);

            $fp = fopen($heurist_path, "w+");
            fwrite($fp, base64_decode($screenshot));
            fclose($fp);

            //display screenshot image
            //echo "<img src=\"data:image/jpeg;base64,".$screenshot."\" /-->";
        }

        if(file_exists($heurist_path)){

            $filesize = getFileSize($heurist_path); //UFile

            $file = new \stdClass();
            $file->original_name = 'snapshot.jpg';
            $file->name = $heurist_path; //pathinfo($heurist_path, PATHINFO_BASENAME);//name with ext
            $file->fullpath = $heurist_path;
            $file->size = $filesize;
            $file->type = 'jpg';

            return $file;

        }else{
            return array('error'=>'Cannot download image from thumbnail generator service. '.$siteURL.' to '.$heurist_path);
        }

    }

    /**
    * Downloads image from given url
    *
    * @param mixed $remote_url
    * @return resource
    */
    public static function getRemoteImage($remote_url, &$orientation=null){  //get_remote_image

        $img = null;

        $data = loadRemoteURLContent($remote_url, false);//get_remote_image as raw data
        if($data){

            if(isset($orientation)){
                //save into file
                $_tmp = tempnam(HEURIST_SCRATCHSPACE_DIR, 'img');
                //imagejpeg($data, $_tmp);
                file_put_contents($_tmp,  $data);
                $orientation = UImage::getImageOrientation($_tmp);
                unlink($_tmp);
            }

            try{
                $img = imagecreatefromstring($data);
            }catch(\Exception  $e){
                $img = false;
            }
        }else{
            $img = false;
        }

        return $img;
    }


    /**
    * Returns image object for given filename
    *
    * @param mixed $filename
    * @return {false|GdImage|null}
    */
    public static function getImageFromFile($filename){
        $mimeExt = UImage::getImageType($filename);
        $image = null;
        if($mimeExt){
            $image = UImage::safeLoadImage($filename, $mimeExt);
        }
        return $image;
    }

    /**
    * Changes black color in given image to new one (for icons)
    * Adds circle and circle background
    */
    public static function changeImageColor($filename, $filename_new, $color_new, $circle_color, $bg_circle_color){

        if(file_exists($filename)){


            $color_new = UImage::_parseColor($color_new);
            $circle_color = UImage::_parseColor($circle_color);
            $bg_circle_color = UImage::_parseColor($bg_circle_color);

            // load icon
            $img_icon = @imagecreatefrompng($filename);

            if($img_icon===false) {return;}

            $color_old = array(0,0,0);//???? 54,100,139);
            $color_new = (!$color_new)?array(255, 0, 0):$color_new;  //array(54,64,80);
            /* RGB of your inside color */
            $rgb = $color_new; //array(0,0,255);

            // Negative values, don't edit
            $rgb = array(($color_old[0]-$rgb[0]),($color_old[1]-$rgb[1]),($color_old[2]-$rgb[2]));
            imagefilter($img_icon, IMG_FILTER_NEGATE);
            imagefilter($img_icon, IMG_FILTER_COLORIZE, $rgb[0], $rgb[1], $rgb[2]);
            imagefilter($img_icon, IMG_FILTER_NEGATE);
            //imagealphablending( $img_icon, false );
            //imagesavealpha( $img_icon, true );

            if($bg_circle_color!=null || $circle_color!=null){

                $img = imagecreatetruecolor(25, 25);//truecolor image 25x25 pix
                imagealphablending( $img, false );
                imagesavealpha( $img, true );

                //fill the background color
                //$bg = imagecolorallocate($img, 200, 200, 200);
                //imagecolortransparent($img, $bg);
                // make the background transparent
                $bg = imagecolorallocatealpha($img, 200, 200, 200, 127);

                //draw transparent rectangle
                imagefilledrectangle($img, 0, 0, 25, 25, $bg);//fill bg rectangle

                // draw filled circle
                if($bg_circle_color!=null){
                    //$col_ellipse = imagecolorallocate($img, $bg_circle_color[0], $bg_circle_color[1], $bg_circle_color[2]);
                    $col_ellipse = imagecolorallocatealpha($img, $bg_circle_color[0], $bg_circle_color[1], $bg_circle_color[2], 80);
                    imagefilledellipse($img, 12, 12 , 24, 24, $col_ellipse);
                }
                // draw circle
                if($circle_color!=null){
                    $col_ellipse = imagecolorallocate($img, $circle_color[0], $circle_color[1], $circle_color[2]);
                    imagearc($img, 12, 12 , 24, 24,  0, 360, $col_ellipse);
                    //imagearc($img, 12, 12 , 23, 23,  0, 360, $col_ellipse);
                }

                $imageInfo = getimagesize($filename);
                if(is_array($imageInfo) && $imageInfo[0]==24  && $imageInfo[1]==24){
                    imagecopy($img, $img_icon, 1, 1, 0, 0, 24, 24);
                }else{
                    imagecopy($img, $img_icon, 4, 4, 0, 0, 16, 16);
                }

                // output
                if($filename_new){
                    imagepng($img, $filename_new);//save to file
                }else{
                    header("Content-type: image/png");
                    imagepng($img);
                }
                imagedestroy($img);
                //readfile($filename2);
            }else{
                // output
                if($filename_new){
                    imagepng($img_icon, $filename_new);//save to file
                }else{
                    header("Content-type: image/png");
                    imagepng($img_icon);
                }
            }

            imagedestroy($img_icon);

        }
    }

    /**
    * Returns extension based of exif
    *
    * @param mixed $filename
    * @return null
    */
    public static function getImageType($filename){

        $mimeExt = null;

        if(file_exists($filename)){

            if (function_exists('exif_imagetype')) {
                switch(@exif_imagetype($filename)){
                    case IMAGETYPE_JPEG:
                        $mimeExt = 'jpg';
                        break;
                    case IMAGETYPE_PNG:
                        $mimeExt = 'png';
                        break;
                    case IMAGETYPE_GIF:
                        $mimeExt = 'gif';
                        break;
                    default;
                }
            }else{
                $path_parts = pathinfo($filename);
                switch($path_parts['extension']) {
                    case 'jpeg':
                    case 'jfif':
                    case 'jpg':
                    case 'jpe':
                        $mimeExt = 'jpg';
                        break;
                    case 'gif':
                        $mimeExt = 'gif';
                        break;
                    case 'png':
                        $mimeExt = 'png';
                        break;
                    default;
                }
            }
        }

        return  $mimeExt;
    }


    /**
    * Returns orientation based of exif
    *
    * @param mixed $file_path
    * @return int
    */
    public static function getImageOrientation($file_path){

        if (!function_exists('exif_read_data')) {
            return 0;
        }
        $exif = @exif_read_data($file_path);
        if ($exif === false) {
            return 0;
        }
        $orientation = (int)@$exif['Orientation'];
        if ($orientation < 2 || $orientation > 8) {
            return 0;
        }else{
            return $orientation;
        }

    }

    /**
    * Verifies the size of image - is it possible to load into allowed memory
    *
    * @param mixed $filename
    * @param mixed $mimeExt
    */
    public static function checkMemoryForImage($filename, $mimeExt){

        $errorMsg = null;

        //if img check memory to be allocated
        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jfif':
            case 'jpg':
            case 'jpe':
            case 'image/gif':
            case 'gif':
            case 'image/png':
            case 'png':

                $imageInfo = getimagesize($filename);
                if(is_array($imageInfo)){
                    if(array_key_exists('channels', $imageInfo) && array_key_exists('bits', $imageInfo)){
                        $memoryNeeded = round(($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2,16)) * 1.65);
                    }else{ //width x height
                        $memoryNeeded = round($imageInfo[0] * $imageInfo[1]*3);
                    }

                    $error_msg = USystem::isMemoryAllowed( $memoryNeeded );
                    if($error_msg!==true){
                        $errorMsg = $error_msg;
                    }
                }
                break;
            default:
                break;
        }

        return $errorMsg;

    }

    /**
    * memory safe load from file to image object
    *
    * @param mixed $filename
    * @param mixed $mimeExt
    */
    public static function safeLoadImage($filename, $mimeExt){

        $img = null;

        $errline_prev = 0;

        set_error_handler(function($errno, $errstr, $errfile, $errline=null, array $errcontext=null) {
            global $errline_prev, $filename, $file;

            //it may report error several times with different messages - send for the first one
            if($errline_prev!=$errline){

                $errline_prev=$errline;
                //database, record ID and name of bad image
                sendEmail(HEURIST_MAIL_TO_ADMIN, 'Cannot load image file. DB:'.HEURIST_DBNAME,
                    'File :'.$filename.' is corrupted. System message: '.$errstr);
                //ID#'.$file['ulf_ID'].'

            }
            return false;
        });//, E_WARNING);

        switch($mimeExt) {
            case 'image/jpeg':
            case 'jpeg':
            case 'jfif':
            case 'jpg':
            case 'jpe':
                $img = @imagecreatefromjpeg($filename);
                break;
            case 'image/gif':
            case 'gif':
                $img = @imagecreatefromgif($filename);
                break;
            case 'image/png':
            case 'png':
                $img = @imagecreatefrompng($filename);
                break;
            default:
                $img = false; //not image
                break;
        }

        restore_error_handler();

        return $img;
    }

    /**
    * Creates thumbnail for given image file
    */
    public static function createScaledImageFile($filename, $scaled_file, $max_width = 200, $max_height = 200, $create_error_thumb=true, $force_type='png'){

        $mimeExt = UImage::getImageType($filename);

        if($mimeExt){
            $errorMsg = UImage::checkMemoryForImage($filename, $mimeExt);

            if(!$errorMsg){

                if (extension_loaded('imagick')) {
                    $res = UImage::_resizeImageImagic($filename, $scaled_file, $max_width, $max_height, $force_type);
                    if($res!==true) {$errorMsg = 'Cannot resize image. '.$res;}
                }else{
                    $img = UImage::safeLoadImage($filename, $mimeExt);
                    if($img){
                        UImage::_resizeImageGD($img, $scaled_file, $max_width, $max_height);
                        if(!file_exists($scaled_file)){
                            $errorMsg = 'Cannot resize image';
                        }
                    }else{
                        $errorMsg = 'Cannot load image file';
                    }
                }

            }
            if($errorMsg && $create_error_thumb)
            {
                $img = UImage::createFromString($errorMsg);
                imagepng($img, $scaled_file);
                imagedestroy($img);
                return $errorMsg;

            }else{
                return file_exists($scaled_file)?true:$errorMsg;
            }
        }
    }


    /**
    * Resizes given image to PNG
    * saves into $thumbnail_file and returns true if success
    * Used ONLY in recordFile.php fileCreateThumbnail
    *
    * @param mixed $filename
    */
    public static function resizeImage($img, $thumbnail_file=null, $x = 200, $y = 200, $orientation=0){

        if($orientation>0){
            $img = UImage::gd_orient_image($img, $orientation);
        }

        $no_enlarge = false;
        // calculate image size
        // note - we never change the aspect ratio of the image!
        $orig_x = imagesx($img);
        $orig_y = imagesy($img);

        $rx = $x / $orig_x;
        $ry = $y / $orig_y;

        $scale = $rx ? $ry ? min($rx, $ry) : $rx : $ry;

        if ($no_enlarge  &&  $scale > 1) {
            $scale = 1;
        }

        $new_x = ceil($orig_x * $scale);
        $new_y = ceil($orig_y * $scale);

        $img_resized = imagecreatetruecolor($new_x, $new_y)  or die;

        // Handle transparency
        imagecolortransparent($img_resized, imagecolorallocate($img_resized, 0, 0, 0));
        imagealphablending($img_resized, false);
        imagesavealpha($img_resized, true);

        imagecopyresampled($img_resized, $img, 0, 0, 0, 0, $new_x, $new_y, $orig_x, $orig_y)  or die;

        if ($thumbnail_file) {
            $resized_file = $thumbnail_file;
        }else{
            //?????
            $resized_file = tempnam(HEURIST_SCRATCHSPACE_DIR, 'resized');
        }

        imagepng($img_resized, $resized_file);//save into file
        imagedestroy($img);
        imagedestroy($img_resized);

        if($thumbnail_file==null){
            //remove themp file
            unlink($resized_file);
        }

        return true;
    }

    private static function gd_imageflip($image, $mode) {
        if (function_exists('imageflip')) {
            return imageflip($image, $mode);
        }
        $new_width = $src_width = imagesx($image);
        $new_height = $src_height = imagesy($image);
        $new_img = imagecreatetruecolor($new_width, $new_height);
        $src_x = 0;
        $src_y = 0;
        switch ($mode) {
            case '1': // flip on the horizontal axis
                $src_y = $new_height - 1;
                $src_height = -$new_height;
                break;
            case '2': // flip on the vertical axis
                $src_x  = $new_width - 1;
                $src_width = -$new_width;
                break;
            case '3': // flip on both axes
                $src_y = $new_height - 1;
                $src_height = -$new_height;
                $src_x  = $new_width - 1;
                $src_width = -$new_width;
                break;
            default:
                return $image;
        }
        imagecopyresampled(
            $new_img,
            $image,
            0,
            0,
            $src_x,
            $src_y,
            $new_width,
            $new_height,
            $src_width,
            $src_height
        );
        return $new_img;
    }

    private static function gd_orient_image($src_img, $orientation) {
        if ($orientation < 2 || $orientation > 8) {
            return $src_img;
        }
        switch ($orientation) {
            case 2:
                $new_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_VERTICAL') ? IMG_FLIP_VERTICAL : 2
                );
                break;
            case 3:
                $new_img = imagerotate($src_img, 180, 0);
                break;
            case 4:
                $new_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1
                );
                break;
            case 5:
                $tmp_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_HORIZONTAL') ? IMG_FLIP_HORIZONTAL : 1
                );
                $new_img = imagerotate($tmp_img, 270, 0);
                imagedestroy($tmp_img);
                break;
            case 6:
                $new_img = imagerotate($src_img, 270, 0);
                break;
            case 7:
                $tmp_img = UImage::gd_imageflip(
                    $src_img,
                    defined('IMG_FLIP_VERTICAL') ? IMG_FLIP_VERTICAL : 2
                );
                $new_img = imagerotate($tmp_img, 270, 0);
                imagedestroy($tmp_img);
                break;
            case 8:
                $new_img = imagerotate($src_img, 90, 0);
                break;
            default:
                return false;
        }

        return $new_img;
    }


    /**
    * Creates thumbnail from pdf file
    *
    * @param mixed $filename
    * @param mixed $thumbnail_file
    */
    public static function getPdfThumbnail( $filename, $thumbnail_file ){

        if(!extension_loaded('imagick')){

            $cmd = 'convert -thumbnail x200 -flatten ';//-background white -alpha remove
            $cmd .= ' '.escapeshellarg($filename.'[0]');
            $cmd .= ' '.escapeshellarg($thumbnail_file);
            exec($cmd, $output, $error);

            if ($error) {
                USanitize::errorLog('ERROR on pdf thumbnail creation: '.$filename.'  '.$cmd.'   '.implode('\n', $output));
                return false;
            }

        }else{
            //Imagic
            try {

                $im =  new \Imagick($filename.'[0]');
                $im->setImageFormat('png');
                $im->thumbnailImage(200,200);

                if(file_exists($thumbnail_file)){
                    unlink($thumbnail_file);
                }
                $im->writeImage($thumbnail_file);

            } catch(\ImagickException $e) {
                USanitize::errorLog($e . ', From Database: ' . HEURIST_DBNAME);
                return false;
            }

        }
        return true;
        //$resized = file_get_contents($thumbnail_file);
        //return $resized;
    }

    /**
    * Finds prevail background color need php version >5.5
    * NOT USED
    * @param mixed $file
    */
    public static function getPrevailBackgroundColor2($filename){

        $image = UImage::getImageFromFile($filename);
        if($image){

            $scaled = imagescale($image, 1, 1, IMG_BICUBIC);//since v5.5
            $index = imagecolorat($scaled, 0, 0);
            $rgb = imagecolorsforindex($scaled, $index);
            /* $red = round(round(($rgb['red'] / 0x33)) * 0x33);
            $green = round(round(($rgb['green'] / 0x33)) * 0x33);
            $blue = round(round(($rgb['blue'] / 0x33)) * 0x33);
            return sprintf('#%02X%02X%02X', $red, $green, $blue);
            */
            return sprintf('#%02X%02X%02X', $rgb['red'], $rgb['green'], $rgb['blue']);
        }else{
            return '#FFFFFF';
        }

    }


    /**
    * Finds prevail background color
    *
    * @param mixed $file
    */
    public static function getPrevailBackgroundColor($filename){
        // histogram options

        $maxheight = 300;
        $barwidth = 2;

        $image = UImage::getImageFromFile($filename);
        if($image){

            $im = $image;

            $imgw = imagesx($im);
            $imgh = imagesy($im);

            // n = total number or pixels

            $n = $imgw*$imgh;

            $histo = array();
            for ($i=0; $i<256; $i++) {$histo[]=0;}

            for ($i=0; $i<$imgw; $i++)
            {
                for ($j=0; $j<$imgh; $j++)
                {

                    // get the rgb value for current pixel

                    $rgb = imagecolorat($im, $i, $j);

                    // extract each value for r, g, b

                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    // get the Value from the RGB value

                    $V = round(($r + $g + $b) / 3);

                    // add the point to the histogram
                    $histo[$V] += $V / $n;
                    $histo_color[$V] = UImage::_rgb2hex(array($r,$g,$b));
                }
            }

            // find the maximum in the histogram in order to display a normated graph

            $max = 0;
            for ($i=0; $i<256; $i++)
            {
                if ($histo[$i] > $max)
                {
                    $max = $histo[$i];
                }
            }

            $key = array_search ($max, $histo);
            $col = $histo_color[$key];
            return $col;
            /*
            echo "<div style='width: ".(256*$barwidth)."px; border: 1px solid'>";
            for ($i=0; $i<255; $i++)
            {
            $val += $histo[$i];

            $h = ( $histo[$i]/$max )*$maxheight;

            echo "<img src=\"img.gif\" width=\"".$barwidth."\"
            height=\"".$h."\" border=\"0\">";
            }
            echo DIV_E;
            */
        }else{
            return '#FFFFFF';
        }
    }

    /**
    * Creates scaled image with Imagic
    * saves into $thumbnail_file and returns true or error message
    *
    * @param mixed $filename
    */
    private static function _resizeImageImagic($filename, $scaled_file, $max_width = 200, $max_height = 200, $force_type='png'){

        try{
            $image = new \Imagick($filename);
            $dims = array('height' => $image->getImageHeight(), 'width' => $image->getImageWidth());

            // rescale if either dimension is greater than 1000 pixels
            if($dims['height'] > $max_height || $dims['width'] > $max_width){

                // scale by the bigger of height or width
                $scaleHeight = $dims['height'] > $dims['width'] ? $max_width : 0;
                $scaleWidth = $dims['width'] > $dims['height'] ? $max_height : 0;

                $image->scaleImage($scaleWidth, $scaleHeight);// scale image
            }

            // force jpeg output
            if($force_type=='png'){
                $image->setImageType('png');
            }
            if($force_type=='jpg'){
                $image->setImageType('jpeg');

                $image->setImageCompression(\imagick::COMPRESSION_JPEG);
                $image->setImageCompressionQuality(75);
            }

            $success = $image->writeImage($scaled_file);

            $image->destroy();

            return $success;

        }catch(\ImagickException $e){
            return $e->message;
        }
    }


    /**
    * Creates scaled PNG image with native GD php functions
    * saves into $thumbnail_file and returns true or false
    *
    * @param mixed $filename
    */
    private static function _resizeImageGD($src_img, $scaled_file, $max_width = 200, $max_height = 200, $scale_type = 'png'){

        if (!function_exists('imagecreatetruecolor')) {
            USanitize::errorLog('Function not found: imagecreatetruecolor');
            return false;
        }

        $write_func = 'imagepng';
        $image_quality = 9;
        /*
        $image_oriented = false;
        if (!empty($options['auto_orient']) && $this->gd_orient_image(
        $file_path,
        $src_img
        )) {
        $image_oriented = true;
        $src_img = $this->gd_get_image_object(
        $file_path,
        $src_func
        );
        }*/
        $img_width = imagesx($src_img);
        $img_height = imagesy($src_img);
        $scale = min(
            $max_width / $img_width,
            $max_height / $img_height
        );
        if ($scale >= 1) {

            //save into file
            if(!$scale_type || $scale_type == 'png'){

                // retain transparent backgrounds
                imagealphablending($src_img, false);
                imagesavealpha($src_img, true);

                imagepng($src_img, $scaled_file);
            }elseif($scale_type == 'jpg'){
                imagejpeg($src_img, $scaled_file);
            }
            imagedestroy($src_img);
            return true;
        }

        $new_width = $img_width * $scale;
        $new_height = $img_height * $scale;
        $dst_x = 0;
        $dst_y = 0;
        $new_img = imagecreatetruecolor($new_width, $new_height);

        // Handle transparency
        imagecolortransparent($new_img, imagecolorallocate($new_img, 0, 0, 0));
        imagealphablending($new_img, false);
        imagesavealpha($new_img, true);

        $success = imagecopyresampled(
            $new_img,
            $src_img,
            $dst_x,
            $dst_y,
            0,
            0,
            $new_width,
            $new_height,
            $img_width,
            $img_height
        );

        if($success){

            if(!$scale_type || $scale_type == 'png'){
                $success = imagepng($new_img, $scaled_file, $image_quality);
            }elseif($scale_type == 'jpg'){
                $success = imagejpeg($new_img, $scaled_file, $image_quality);
            }
        }

        imagedestroy($src_img);
        if($new_img) {imagedestroy($new_img);}

        return $success;
    }


    //
    //
    //
    private static function _parseColor($param_color){

        if($param_color!=null){

            if(strpos($param_color,'rgb')===0){
                $clr = substr($param_color,4,-1);
                $color_new = explode(',',$clr);
            }else{
                //1st way list($r,$g,$b) = array_map('hexdec',str_split($colorName,2));
                //2d way
                $hexcolor = $param_color;
                $shorthand = (strlen($hexcolor) == 4);
                list($r, $g, $b) = $shorthand? sscanf($hexcolor, "#%1s%1s%1s") : sscanf($hexcolor, "#%2s%2s%2s");
                $color_new = $shorthand?array(hexdec("$r$r"), hexdec("$g$g"), hexdec("$b$b"))
                :array(hexdec($r), hexdec($g), hexdec($b));
            }

        }else{
            $color_new = null; //array(255, 0, 0);
        }
        return $color_new;
    }


    //
    //
    //
    private static function _rgb2hex($rgb) {
        $hex = "#";
        $hex .= str_pad(dechex($rgb[0]), 2, '0', STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[1]), 2, '0', STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[2]), 2, '0', STR_PAD_LEFT);

        return $hex; // returns the hex value including the number sign (#)
    }


}
