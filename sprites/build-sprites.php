<?php

/*

This script requires the Yaml component of the Symfony framework, which requires Pear to install.

Getting pear installed under wamp:
http://phphints.wordpress.com/2008/08/26/installing-pear-package-manager-on-wamp/

But first, you might need to download the go-pear.phar script:
http://pear.php.net/go-pear.phar

And then set the environment variable:
PHP_PEAR_SYSCONF_DIR=C:\wamp\bin\php\php5.3.0

Once pear's working, install Yaml:
http://symfony.com/components

Basically:
pear channel-discover pear.symfony.com
pear install symfony2/Yaml

*/

spl_autoload_register();

use Symfony\Component\Yaml\Yaml;

define('CACHE_DIR', sys_get_temp_dir() . "/build-sprites-cache");

array_shift($argv);

if (count($argv) < 1) {
    printf("Input file name required\n");
    exit(1);
}
$inputFile = array_shift($argv);

if (! file_exists($inputFile)) {
    printf("Input file '%s' not found\n", $inputFile);
    exit(1);
}

printf("Reading input from '%s'.\n", $inputFile);
$inputData = Yaml::parse($inputFile);
if (! $inputData) exit(1);

$classes = array();
if (isset($inputData['classes'])) {
    foreach ($inputData['classes'] as $cls) {
        $keys = array_keys($cls);
        $id = $keys[0];
        $cls = $cls[$id];
        $classes[$id] = $cls;
    }
}

$buffers = isset($inputData['buffers']) ? $inputData['buffers'] : array();
$sprites = isset($inputData['sprites']) ? $inputData['sprites'] : array();

$spriteWidth = (int)$inputData['width'];
$spriteHeight = (int)$inputData['height'];
$spriteColumns = (int)$inputData['columns'];
$spriteColumns = (count($sprites) < $spriteColumns) ? count($sprites) : $spriteColumns;
$spriteRows = (int)ceil(count($sprites) / $spriteColumns);
$imageWidth = $spriteColumns * $spriteWidth;
$imageHeight = $spriteRows * $spriteHeight;
$jsonTags = isset($inputData['tags']) ? $inputData['tags'] : array();

$outputImageFile = isset($inputData['pngOut']) ? $inputData['pngOut'] : false;
$outputFTLFile = isset($inputData['ftlOut']) ? $inputData['ftlOut'] : false;
$ftlVar = isset($inputData['ftlVar']) ? $inputData['ftlVar'] : false;

if ((! $outputImageFile) || (! $outputFTLFile) || (! $ftlVar)) {
    $pos = strrpos($inputFile, '.');
    if (! $pos)
        $outputBasepath = $inputFile;
    else
        $outputBasepath = substr($inputFile, 0, $pos);
    $outputBasename = basename($outputBasepath);
    if (! $outputImageFile)
        $outputImageFile = $outputBasepath . '.png';
    if (! $outputFTLFile)
        $outputFTLFile = $outputBasepath . '.ftl';
    if (! $ftlVar)
        $ftlVar = $outputBasename;
}

if ($outputFTLFile === '-') $outputFTLFile = false;

$cache = array();

printf("Output image will be stored in '%s'.\n", $outputImageFile);
if (! $outputFTLFile)
    print("No FTL will be stored.\n");
else
    printf("Output FTL will be stored in '%s'.\n", $outputFTLFile);
printf("Found %s classes.\n", count($classes));
printf("Found %s buffers.\n", count($buffers));
printf("Found %s sprites.\n", count($sprites));
printf("Sprite width: %s\n", $spriteWidth);
printf("Sprite height: %s\n", $spriteHeight);
printf("Sprite columns: %s\n", $spriteColumns);
printf("Sprite rows: %s\n", $spriteRows);
printf("Image size: %sx%s\n", $imageWidth, $imageHeight);

$image = createImage($imageWidth, $imageHeight);

$finishedBuffers = array();
foreach ($buffers as $buffer) {
    $keys = array_keys($buffer);
    $id = $keys[0];
    $buffer = $buffer[$id];
    
    printf("Building buffer %s...\n", $id);
    
    $bufferImage = buildSprite($buffer);
    if (! $bufferImage) {
        printf("Skipping empty buffer %s.\n", $id);
        continue;
    }
    $finishedBuffers[$id] = $bufferImage;
}
$buffers = $finishedBuffers;

$row = 0;
$column = -1;
$json = array();
foreach ($sprites as $sprite) {
    $column++;
    if ($column === $spriteColumns) {
        $column = 0;
        $row++;
    }
    
    $keys = array_keys($sprite);
    $id = $keys[0];
    $sprite = $sprite[$id];
    
    printf("Building sprite %s...\n", $id);
    
    $spriteImage = buildSprite($sprite);
    
    $tags = array($column, $row);
    foreach ($jsonTags as $tag)
        $tags[] = isset($sprite[$tag]) ? $sprite[$tag] : '';
    $json[$id] = $tags;
    
    if (! $spriteImage) {
        printf("Skipping empty sprite %s.\n", $id);
        continue;
    }
    
//imagepng($spriteImage, 'foo2.png');

    $spriteImage = xImageResize($spriteImage, $spriteWidth, $spriteHeight);
    imagecopy($image, $spriteImage, $column * $spriteWidth, $row * $spriteHeight, 0, 0, $spriteWidth, $spriteHeight);
    imagedestroy($spriteImage);
}

printf("Saving image...\n");
imagepng($image, $outputImageFile);
imagedestroy($image);

if ($outputFTLFile) {
    printf("Saving FTL...\n");
    $ftl = sprintf("<#assign %s = %s>\n", $ftlVar, json_encode($json));
    $ftl .= sprintf("<#assign %sMeta = {\n", $ftlVar);
    $ftl .= sprintf("  \"width\": %s,\n", $spriteWidth);
    $ftl .= sprintf("  \"height\": %s\n", $spriteHeight);
    $ftl .= "}>\n";
    file_put_contents($outputFTLFile, $ftl);
}

print("Done.\n");

exit();

function buildSprite($src) {
    global $spriteWidth, $spriteHeight;
    
    if (! $src) return false;
    
    if (is_string($src))
        return loadSource($src);
        
    if (is_array($src)) {
    
        if (array_key_exists(0, $src)) {
            $image = createImage($spriteWidth, $spriteHeight);
            foreach ($src as $subSrc) {
                $subImage = buildSprite($subSrc);
                if (! $subImage) continue;
                $subImage = xImageResize($subImage, $spriteWidth, $spriteHeight);
                $action = (is_array($subSrc) && isset($subSrc['action'])) ? strtolower($subSrc['action']) : 'copy';
                switch ($action) {
                    case 'copy':
                        xImageCopy($image, $subImage);
                        break;
//                    case 'blend':
//                        imagecopymerge($image, $subImage, 0, 0, 0, 0, $spriteWidth, $spriteHeight, 100);
//                        break;
                    case 'mask':
                        xImageMask($image, $subImage);
                        break;
                    default:
                        printf("Unknown action '%s'.\n", $action);
                        exit(1);
                }
                imagedestroy($subImage);
            }
            return $image;
        }
        
        $src = applyClass($src);
        
        if (! array_key_exists('src', $src)) return false;
        
        $subSrc = $src['src'];
        if (is_string($subSrc) && array_key_exists('baseSrc', $src))
            $subSrc = $src['baseSrc'] . $subSrc;
        $image = buildSprite($subSrc);
        if ((isset($src['x']) || isset($src['sx'])) &&
            (isset($src['y']) || isset($src['sy'])) &&
            isset($src['width']) && isset($src['height'])) {
            $width = abs($src['width']);
            $height = abs($src['height']);
            if ($width <= 1) $width = (int)($width * imagesx($image));
            if ($height <= 1) $height = (int)($height * imagesy($image));
            $x = isset($src['sx']) ? (int)($src['sx'] * $width) : $src['x'];
            $y = isset($src['sy']) ? (int)($src['sy'] * $height) : $src['y'];
            if (($x != 0) && ($x >= -1) && ($x <= 1)) $x = (int)($x * imagesx($image));
            if (($y != 0) && ($y >= -1) && ($y <= 1)) $y = (int)($y * imagesy($image));
            if ($x < 0) {
                $width += $x;
                $x = 0;
            }
            if ($y < 0) {
                $height += $y;
                $y = 0;
            }
            if (($x + $width) > imagesx($image))
                $width = imagesx($image) - $x;
            if (($y + $height) > imagesy($image))
                $height = imagesy($image) - $y;
            $newImage = createImage($width, $height);
            imagecopy($newImage, $image, 0, 0, $x, $y, $width, $height);
            imagedestroy($image);
            $image = $newImage;
        }
        
        if (array_key_exists('process', $src)) {
            if (! is_array($src['process']))
                $src['process'] = preg_split('/\s+/', $src['process']);
            foreach ($src['process'] as $func) {
                $image = process($func, $image);
                if (! $image) return;
            }
        }
        
        if (array_key_exists('dst', $src)) {
            $dst = $src['dst'];
            if (is_array($dst)) {
                $width = isset($dst['width']) ? abs($dst['width']) : $spriteWidth;
                $height = isset($dst['height']) ? abs($dst['height']) : $spriteHeight;
                $top = isset($dst['top']) ? abs($dst['top']) : 0;
                $left = isset($dst['left']) ? abs($dst['left']) : 0;
                $bottom = isset($dst['bottom']) ? abs($dst['bottom']) : 0;
                $right = isset($dst['right']) ? abs($dst['right']) : 0;
                
                if ($width <= 1) $width = (int)($width * $spriteWidth);
                if ($height <= 1) $height = (int)($height * $spriteHeight);
                if ($top <= 1) $top = (int)($top * $height);
                if ($left <= 1) $left = (int)($left * $width);
                if ($bottom <= 1) $bottom = (int)($bottom * $height);
                if ($right <= 1) $right = (int)($right * $width);
                
                $newImage = createImage($width, $height);
                xImageCopyRetainAR($newImage, $image, $left, $top, $width - $left - $right, $height - $top - $bottom);
                imagedestroy($image);
                $image = $newImage;
            }
        }
        
        $image = xImageResize($image, $spriteWidth, $spriteHeight);
        return $image;
    }
    return false;
}

function xImageCopyRetainAR($dst, $src, $dstX, $dstY, $dstWidth, $dstHeight) {
    $srcWidth = imagesx($src);
    $srcHeight = imagesy($src);
    $srcAR = $srcWidth / $srcHeight;
    $dstAR = $dstWidth / $dstHeight;
    
    if ($dstAR > $srcAR) {
        // source is narrower
        $orgWidth = $dstWidth;
        $dstWidth = (int)($srcAR * $dstHeight);
        $dstX += (int)(($orgWidth - $dstWidth) / 2);
    } else if ($dstAR < $srcAR) {
        // source is wider
        $orgHeight = $dstHeight;
        $dstHeight = (int)($dstWidth / $srcAR);
        $dstY += (int)(($orgHeight - $dstHeight) / 2);
    }
    imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
}

function xImageResize($image, $width, $height, $destroy = true) {
    if (($width === imagesx($image)) && ($height === imagesy($image))) return $image;
    $newImage = createImage($width, $height);
    xImageCopyRetainAR($newImage, $image, 0, 0, $width, $height);
    if ($destroy) imagedestroy($image);
    return $newImage;
}

function xImageDump($image) {
    $width = imagesx($image);
    $height = imagesy($image);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            print($color['alpha'] . ' ');
        }
    }
}

function xImageCopy($image, $src) {
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Resize src if necessary
    if (($width != imagesx($src)) || ($height != imagesy($src))) {
        printf("Resizing source to match target...\n");
        $temp = createImage($width, $height);
        imagecopyresampled($temp, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
        $src = $temp;
        $destroy = true;
    } else
        $destroy = false;

    printf("Copying...\n");
    imagealphablending($image, false);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorsforindex($src, imagecolorat($src, $x, $y));
            if ($color['alpha'] == 127) continue;
            imagesetpixel($image, $x, $y, imagecolorallocatealpha($image,
                $color['red'], $color['green'], $color['blue'], $color['alpha']));
        }
    }
    if ($destroy) imagedestroy($src);
}

function xImageMask($image, $mask) {
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Resize mask if necessary
    if (($width != imagesx($mask)) || ($height != imagesy($mask))) {
        printf("Resizing mask to match target...\n");
        $temp = createImage($width, $height);
        imagecopyresampled($temp, $mask, 0, 0, 0, 0, $width, $height, imagesx($mask), imagesy($mask));
        $mask = $temp;
        $destroy = true;
    } else
        $destroy = false;

    printf("Masking...\n");
    imagealphablending($image, false);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
            if ($color['alpha'] == 127)
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, 0, 0, 0, 127));
            else {
                $gray = (($color['red'] + $color['green'] + $color['blue']) / 3) / 255;
                $alpha = $color['alpha'];
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                if ($color['alpha'] == 127) continue;
                $color['red'] = (int)($color['red'] * $gray);
                $color['green'] = (int)($color['green'] * $gray);
                $color['blue'] = (int)($color['blue'] * $gray);
                $color['alpha'] = $alpha;
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image,
                    $color['red'], $color['green'], $color['blue'], $color['alpha']));
            }
        }
    }
    if ($destroy) imagedestroy($mask);
}

function applyClass($arr) {
    global $classes;
    if (! array_key_exists('class', $arr)) return $arr;
    if (! is_array($arr['class']))
        $arr['class'] = preg_split('/\s+/', $arr['class']);
    foreach ($arr['class'] as $class) {
        if (! array_key_exists($class, $classes)) {
            printf("Class '%s' not found.\n", $class);
            exit(1);
        }
        $arr = array_merge(applyClass($classes[$class]), $arr);
        
        if (array_key_exists('baseSrc', $arr) && (strpos($arr['baseSrc'], '%baseSrc%') !== false))
            $arr['baseSrc'] = str_replace('%baseSrc%', $classes[$class]['baseSrc'], $arr['baseSrc']);
            
    }
    return $arr;
}

function loadSource($src) {
    global $cache, $buffers, $spriteWidth, $spriteHeight;
    
    $pos = strrpos($src, '!');
    if ($pos) {
        $frame = (int)substr($src, $pos + 1);
        $src = substr($src, 0, $pos);
    } else
        $frame = false;
    $contentIsImage = false;
    
    if (isset($cache[$src]))
        $content = $cache[$src];
    else if (preg_match('/^buffer:/', $src)) {
        $name = trim(substr($src, 7));
        if (isset($buffers[$name])) {
            $content = $buffers[$name];
            $contentIsImage = true;
        } else {
            printf("Unknown buffer '%s'.\n", $name);
            exit(1);
        }
    } else if (preg_match('/^solid:/', $src)) {
        $color = array_map('trim', explode(',', substr($src, 6)));
        $content = createImage($spriteWidth, $spriteHeight);
        $color = imagecolorallocatealpha($content, $color[0], $color[1], $color[2], $color[3]);
        imagefill($content, 0, 0, $color);
        $contentIsImage = true;
        
    } else {
        $paths = explode('|', $src);
        $image = false;
        $file = array_shift($paths);
        if (preg_match('/^http:/', $file)) {
            if (! is_dir(CACHE_DIR)) {
                printf("Creating cache directory %s...\n", CACHE_DIR);
                if (! mkdir(CACHE_DIR, 0777, true)) exit(1);
            }
            $cacheName = CACHE_DIR . '/' . md5($file);
            if (file_exists($cacheName)) {
                $content = file_get_contents($cacheName);
                if (! $content) exit(1);
            } else {
                printf("Downloading '%s'...\n", $file);
                $content = file_get_contents($file);
                if (! $content) exit(1);
                file_put_contents($cacheName, $content);
            }
            $file = false;
        } else {
            $matches = array();
            while (preg_match('/%(\w+)%/', $file, $matches, PREG_OFFSET_CAPTURE)) {
                $file = substr($file, 0, $matches[0][1]) . getenv($matches[1][0]) . substr($file, $matches[0][1] + strlen($matches[0][0]));
            }
            printf("Reading '%s'...\n", $file);
            $content = file_get_contents($file);
            if (! $content) exit(1);
        }
        $isTmp = false;
        while (count($paths)) {
            $path = array_shift($paths);
            printf("Unzipping entry '%s'...\n", $path);
            if (! $file) {
                $file = tempnam(sys_get_temp_dir(), 'tmp-');
                $isTmp = true;
                file_put_contents($file, $content);
                $content = false;
            }
            $zip = new ZipArchive();
            $res = $zip->open($file);
            if ($res !== true) {
                printf("Unable to open zip file '%s': %s\n", $file, $res);
                exit(1);
            }
            $stream = $zip->getStream($path);
            if (! $stream) {
                printf("Unable to read zip entry '%s' from '%s'.\n", $path, $file);
                exit(1);
            }
            $content = '';
            while (! feof($stream))
                $content .= fread($stream, 2);
            fclose($stream);
            $zip->close();
            if ($isTmp) unlink($file);
            $file = false;
            $isTmp = false;
        }
    }
    if ($contentIsImage) {
        ob_start();
        imagepng($content);
        $content = ob_get_contents();
        ob_end_clean();
        $contentIsImage = false;
    }
    $cache[$src] = $content;
    if ($frame !== false) {
        printf("Extracting frame %s...\n", $frame);
        $cacheName = $src . '-frames';
        if (! isset($cache[$cacheName])) {
            $decoder = new GIFDecoder($content);
            $frames = $decoder->GIFGetFrames();
            $cache[$cacheName] = $frames;
        } else
            $frames = $cache[$cacheName];
        if (! isset($frames[$frame])) {
            printf("Frame %s not found in '%s'\n", $frame, $src);
            exit(1);
        }
        $content = $frames[$frame];
    }
    $image = imagecreatefromstring($content);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    return $image;
}

function process($func, $image) {
    switch ($func) {
        case 'hflip':
            $w = imagesx($image);
            $h = imagesy($image);
            $new = createImage($w, $h);
            imagecopyresampled($new, $image, 0, 0, $w - 1, 0, $w, $h, -$w, $h);
            imagedestroy($image);
            return $new;
        case 'vflip':
            $w = imagesx($image);
            $h = imagesy($image);
            $new = createImage($w, $h);
            imagecopyresampled($new, $image, 0, 0, 0, $h - 1, $w, $h, $w, -$h);
            imagedestroy($image);
            return $new;
        default:
            printf("Unknown process function '%s'.\n", $func);
            exit(1);
    }
}

function createImage($width, $height) {
    $image = imagecreatetruecolor($width, $height);
    if (! $image) {
        printf("Unable to create %sx%s image.\n", $width, $height);
        exit(1);
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
//    imagealphablending($image, true);
//    $back = imagecolorallocate($image, 0, 0, 0);
//    imagefilledrectangle($image, 0, 0, $width, $height, $back);
//    imagecolortransparent($image, $back);

    return $image;
}



class GIFDecoder {
    var $GIF_TransparentR = - 1;
    var $GIF_TransparentG = - 1;
    var $GIF_TransparentB = - 1;
    var $GIF_TransparentI = 0;
    var $GIF_buffer = array();
    var $GIF_arrays = array();
    var $GIF_delays = array();
    var $GIF_dispos = array();
    var $GIF_stream = "";
    var $GIF_string = "";
    var $GIF_bfseek = 0;
    var $GIF_anloop = 0;
    var $GIF_screen = array();
    var $GIF_global = array();
    var $GIF_sorted;
    var $GIF_colorS;
    var $GIF_colorC;
    var $GIF_colorF;

    function GIFDecoder($GIF_pointer) {
        $this->GIF_stream = $GIF_pointer;
        GIFDecoder :: GIFGetByte(6);
        GIFDecoder :: GIFGetByte(7);
        $this->GIF_screen = $this->GIF_buffer;
        $this->GIF_colorF = $this->GIF_buffer[4] & 0x80 ? 1 : 0;
        $this->GIF_sorted = $this->GIF_buffer[4] & 0x08 ? 1 : 0;
        $this->GIF_colorC = $this->GIF_buffer[4] & 0x07;
        $this->GIF_colorS = 2 << $this->GIF_colorC;
        if($this->GIF_colorF == 1) {
            GIFDecoder :: GIFGetByte(3 * $this->GIF_colorS);
            $this->GIF_global = $this->GIF_buffer;
        }
        for($cycle = 1; $cycle;) {
            if(GIFDecoder :: GIFGetByte(1)) {
                switch($this->GIF_buffer[0]) {
                    case 0x21 :
                        GIFDecoder :: GIFReadExtensions();
                        break;
                    case 0x2C :
                        GIFDecoder :: GIFReadDescriptor();
                        break;
                    case 0x3B :
                        $cycle = 0;
                        break;
                }
            }
            else{
                $cycle = 0;
            }
        }
    }

    function GIFReadExtensions() {
        GIFDecoder :: GIFGetByte(1);
        if($this->GIF_buffer[0] == 0xff) {
            for(;;) {
                GIFDecoder :: GIFGetByte(1);
                if(($u = $this->GIF_buffer[0]) == 0x00) {
                    break;
                }
                GIFDecoder :: GIFGetByte($u);
                if($u == 0x03) {
                    $this->GIF_anloop = ($this->GIF_buffer[1] | $this->GIF_buffer[2] << 8);
                }
            }
        }
        else{
            for(;;) {
                GIFDecoder :: GIFGetByte(1);
                if(($u = $this->GIF_buffer[0]) == 0x00) {
                    break;
                }
                GIFDecoder :: GIFGetByte($u);
                if($u == 0x04) {
                    if($this->GIF_buffer[3] & 0x80) {
                        $this->GIF_dispos[] = ($this->GIF_buffer[0] >> 2) - 1;
                    }
                    else{
                        $this->GIF_dispos[] = ($this->GIF_buffer[0] >> 2) - 0;
                    }
                    $this->GIF_delays[] = ($this->GIF_buffer[1] | $this->GIF_buffer[2] << 8);
                    if($this->GIF_buffer[3]) {
                        $this->GIF_TransparentI = $this->GIF_buffer[3];
                    }
                }
            }
        }
    }

    function GIFReadDescriptor() {
        $GIF_screen = array();
        GIFDecoder :: GIFGetByte(9);
        $GIF_screen = $this->GIF_buffer;
        $GIF_colorF = $this->GIF_buffer[8] & 0x80 ? 1 : 0;
        if($GIF_colorF) {
            $GIF_code = $this->GIF_buffer[8] & 0x07;
            $GIF_sort = $this->GIF_buffer[8] & 0x20 ? 1 : 0;
        }
        else{
            $GIF_code = $this->GIF_colorC;
            $GIF_sort = $this->GIF_sorted;
        }
        $GIF_size = 2 << $GIF_code;
        $this->GIF_screen[4] &= 0x70;
        $this->GIF_screen[4] |= 0x80;
        $this->GIF_screen[4] |= $GIF_code;
        if($GIF_sort) {
            $this->GIF_screen[4] |= 0x08;
        }
        if($this->GIF_TransparentI) {
            $this->GIF_string = "GIF89a";
        }
        else{
            $this->GIF_string = "GIF87a";
        }
        GIFDecoder :: GIFPutByte($this->GIF_screen);
        if($GIF_colorF == 1) {
            GIFDecoder :: GIFGetByte(3 * $GIF_size);
            if($this->GIF_TransparentI) {
                $this->GIF_TransparentR = $this->GIF_buffer[3 * $this->GIF_TransparentI + 0];
                $this->GIF_TransparentG = $this->GIF_buffer[3 * $this->GIF_TransparentI + 1];
                $this->GIF_TransparentB = $this->GIF_buffer[3 * $this->GIF_TransparentI + 2];
            }
            GIFDecoder :: GIFPutByte($this->GIF_buffer);
        }
        else{
            if($this->GIF_TransparentI) {
                $this->GIF_TransparentR = $this->GIF_global[3 * $this->GIF_TransparentI + 0];
                $this->GIF_TransparentG = $this->GIF_global[3 * $this->GIF_TransparentI + 1];
                $this->GIF_TransparentB = $this->GIF_global[3 * $this->GIF_TransparentI + 2];
            }
            GIFDecoder :: GIFPutByte($this->GIF_global);
        }
        if($this->GIF_TransparentI) {
            $this->GIF_string .= "!\xF9\x04\x1\x0\x0".chr($this->GIF_TransparentI)."\x0";
        }
        $this->GIF_string .= chr(0x2C);
        $GIF_screen[8] &= 0x40;
        GIFDecoder :: GIFPutByte($GIF_screen);
        GIFDecoder :: GIFGetByte(1);
        GIFDecoder :: GIFPutByte($this->GIF_buffer);
        for(;;) {
            GIFDecoder :: GIFGetByte(1);
            GIFDecoder :: GIFPutByte($this->GIF_buffer);
            if(($u = $this->GIF_buffer[0]) == 0x00) {
                break;
            }
            GIFDecoder :: GIFGetByte($u);
            GIFDecoder :: GIFPutByte($this->GIF_buffer);
        }
        $this->GIF_string .= chr(0x3B);
        $this->GIF_arrays[] = $this->GIF_string;
    }

    function GIFGetByte($len) {
        $this->GIF_buffer = array();
        for($i = 0; $i < $len; $i++) {
            if($this->GIF_bfseek > strlen($this->GIF_stream)) {
                return 0;
            }
            $this->GIF_buffer[] = ord($this->GIF_stream {
                $this->GIF_bfseek++
            }
            );
        }
        return 1;
    }

    function GIFPutByte($bytes) {
        foreach($bytes as $byte) {
            $this->GIF_string .= chr($byte);
        }
    }

    function GIFGetFrames() {
        return ($this->GIF_arrays);
    }

    function GIFGetDelays() {
        return ($this->GIF_delays);
    }

    function GIFGetLoop() {
        return ($this->GIF_anloop);
    }

    function GIFGetDisposal() {
        return ($this->GIF_dispos);
    }

    function GIFGetTransparentR() {
        return ($this->GIF_TransparentR);
    }

    function GIFGetTransparentG() {
        return ($this->GIF_TransparentG);
    }

    function GIFGetTransparentB() {
        return ($this->GIF_TransparentB);
    }
}
