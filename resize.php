<?php

define("BYTE", 1);
define("WORD", BYTE * 2);
define("DWORD", WORD * 2);
define("DATA_TYPE_BYTE", 1);
define("DATA_TYPE_ASCII", 2);
define("DATA_TYPE_SHORT", 3);
define("DATA_TYPE_LONG", 4);
define("DATA_TYPE_RATIONAL", 5);
define("DATA_TYPE_SBYTE", 6);
define("DATA_TYPE_UNDEFINE", 7);
define("DATA_TYPE_SSHORT", 8);
define("DATA_TYPE_SLONG", 9);
define("DATA_TYPE_SRATIONAL", 10);
define("DATA_TYPE_FLOAT", 11);
define("DATA_TYPE_DOUBLE", 12);
define("PHOTOSHOP_TAG", "8649");
define("CLIPPING_PATH_INFO", "07d0");
define("CLIPPING_PATH_NAME", "0bb8");

function addClippingPath($file_name, $file_path, $svg_file_path) {
    global $gimp_command;
	// add the svg path as a layer to the image
	echo sprintf("GIMP: Importing %s into %s with gimp ...%s", $svg_file_path, $file_path, PHP_EOL);
    $final_file_path = sprintf("%s-withPath.tiff", $file_name);
    echo shell_exec(sprintf("%s -f -i -b '(python-add-clipping-path RUN-NONINTERACTIVE \"%s\" \"%s\" \"%s\")' -b '(gimp-quit 0)'", $gimp_command, $file_path, $svg_file_path, $final_file_path)) . PHP_EOL;
	echo sprintf("GIMP: %s created%s", $final_file_path, PHP_EOL);
    return $final_file_path;
}

function createSVGOutline(&$file_name, &$file_path) {
	echo sprintf("Imagemagick: Creating image silhouette for %s%s", $file_path, PHP_EOL);
	$pbm_file_path = sprintf("%s.pbm", $file_name);
    $svg_file_path = sprintf("%s.svg", $file_name);
	
    //exec(sprintf("convert %s -colorspace gray -threshold 1%% -background transparent \( -clone 0 -roll +1+0 -clone 0 -compose difference -composite \) \( -clone 0 -roll +0+1 -clone 0 -compose difference -composite \) -delete 0  -compose screen -composite -quiet %s", $file_path, $pbm_file_path));

	exec(sprintf("convert %s -colorspace gray -threshold 1%% -background transparent -negate -quiet %s", $file_path, $pbm_file_path));

	echo sprintf("Potrace: Creating SVG Outline for %s ...%s", $file_path, PHP_EOL);
    exec(sprintf("potrace %s -o %s -b svg --opaque", $pbm_file_path, $svg_file_path));

	// modify the SVG file to set fill colour of the SVG to none
    $svg = simplexml_load_file($svg_file_path);
    $g = $svg->g;
    $g["fill"] = "none";
    $paths = $svg->g->path;
    $paths[0]["id"] = "Path 1";
    $paths[0]["fill"] = "none";
    $paths[1]["fill"] = "none";
    $svg->asXml($svg_file_path);
	//unlink($pbm_file_path);
    return $svg_file_path;
}

function validateFilenameMetadata($file_base_name, $file_extension, $file_metadata, &$file_metadata_array, $new_width, $new_height, &$file_path, &$file_name_changed) {

	if($file_metadata_array[0]  === "A" && ($new_width > 2400 || $new_height > 2400)) {
        $file_metadata_array[0] = "C";
        $file_name_changed = true;
        $file_metadata = implode("", $file_metadata_array);
        $file_name = sprintf("%s_%s", $file_base_name, $file_metadata);
		echo sprintf("PHP::resizeImage: Changing file_name to %s as new width or new height > 2400px%s", $file_name, PHP_EOL);
	}
	else if($file_metadata_array[0]  === "C" && ($new_width <= 2400 || $new_height <= 2400 && $new_height >= 900)) {
        $file_metadata_array[0] = "A";
		$file_name_changed = true;
		$file_metadata = implode("", $file_metadata_array);
		$file_name = sprintf("%s_%s", $file_base_name, $file_metadata);
		echo sprintf("PHP::resizeImage: Changing file_name to %s as new width or new height <= 2400px%s", $file_name, PHP_EOL);
	}

	$file_path = sprintf("%s.%s", $file_name, $file_extension);
	echo sprintf("PHP::resizeImage: file_path is now %s%s", $file_path, PHP_EOL);
}

function validateFilename($file_name, $file_extension, $file_base_name, &$file_path, &$file_name_changed) {
	if($file_extension !== "tiff") {
		$file_extension = "tiff";
		$file_path = sprintf("%s.%s", $file_name, $file_extension);
		echo sprintf("PHP::resizeImage: Changing filename to %s to correct incorrect file extension%s", $file_path, PHP_EOL);
		$file_name_changed = true;
	}

	// ensure that the filename has leading 0's if the length of the root is < 14
    $file_name_changed = false;

	if(strlen($file_base_name) < 14) {
		$file_base_name = str_pad($file_base_name[0], 14, '0', STR_PAD_LEFT);
		echo sprintf("PHP::resizeImage: Changing file_base_name to %s to pad beginning with 0's if length is < 14%s", $file_base_name, PHP_EOL);
		$file_name_changed = true;
	}

    return $file_name_changed;
}

function binA2decA($arr, $start, $len) {
    $arr = array_slice($arr, $start, $len);
	$str = implode("", $arr);
    return unpack("C*", $str, 0);
}

function str2binA($str) {
    $str = str_split($str);
	return $str 
		? array_map(function($c) {
			return decbin(ord($c));
		}, $str)
		: [];
}

function binA2str($arr, $start, $len) {
    $ints = binA2decA($arr, $start, $len);
    return $ints 
		? implode("", array_map(function ($a) {
			return chr($a);
		}, $ints)) 
		: "-1";
}

function binA2hex($arr, $start, $len) {
    return padHex(dechex(binA2dec($arr, $start, $len)));
}

function binA2dec($arr, $start, $len) {
    $ints = binA2decA($arr, $start, $len);
	$dec = 0;
	
    if ($ints) {
        for ($x = $len; $x > 0; $x--) {
            $dec += $x > 1 ? ($ints[$x] * 256 * $x) : $ints[$x];
        }
    }

    return $dec;
}

function padHex($hex) {
    return str_pad($hex, strlen($hex) + (strlen($hex) % 2), 0, STR_PAD_LEFT);
}

function hex2binA($hex) {
    return str_split(hex2bin(padHex($hex)));
}

function num2binA($str) {
    return hex2binA(dechex(intval($str)));
}

function strToBinary($s) { 
    $ret = "";
    $n = strlen($s); 
  
    for ($i = 0; $i < $n; $i++) { 
        // convert each char to ASCII value 
        $val = ord($s[$i]); 
  
        // Convert ASCII value to binary 
        $bin = "";

        while ($val > 0) { 
            ($val % 2)
				? $bin .= '1' 
				: $bin .= '0'; 
                           
            $val= floor($val / 2); 
        } 

        for ($x = strlen($bin) - 1; $x >= 0; $x--) {
            //echo $bin[$x];
			$ret .= $bin[$x];
        }
		
        $ret .= " ";
        //echo " ";
    }

    return rtrim($ret, " ");
} 

function readTiff($b_file_data) {
    $response = [];
	
	$arr_file_data = str_split($b_file_data);
    $ifd_offset = 0;
	$endianness = binA2hex($arr_file_data, $ifd_offset++, 1) . binA2hex($arr_file_data, $ifd_offset++, 1) . binA2hex($arr_file_data, $ifd_offset++, 1) . binA2hex($arr_file_data, $ifd_offset++, 1) === "49492a00" ? "LITTLE" : "BIG";
    echo(sprintf("PHP::readTiff: endianness: %s%s", $endianness, PHP_EOL));
	$ifd_number = 0;
    $ifd_offset_hex = binA2hex($arr_file_data, $ifd_offset, DWORD);
	$ifd_offset += DWORD;
	
	if($ifd_offset_hex !== "08") {
		$ifd_offset = hexdec($ifd_offset_hex);
	}
	
	while (empty($response) && $ifd_offset !== 0) {
		echo(sprintf("PHP::readTiff: IFD #%d Offset: %d%s", $ifd_number, $ifd_offset, PHP_EOL));
        $ifd_tag_entry_count = unpack('v*', substr($b_file_data, $ifd_offset, WORD))[1];
        $ifd_offset += 2;
		$ifd_tags = array_fill(0, $ifd_tag_entry_count, null);
		echo(sprintf("PHP::readTiff: Reading %d tags%s", $ifd_tag_entry_count, PHP_EOL));

		for ($ifd_tag = 0; $ifd_tag < $ifd_tag_entry_count; $ifd_tag++) {
            $TagId = unpack("v*", substr($b_file_data, $ifd_offset, WORD))[1];
            //$DataType = unpack('v*', substr($b_file_data, ($ifd_offset += WORD), WORD))[1];
            $DataCount =  unpack('v*', substr($b_file_data, ($ifd_offset += WORD), DWORD))[1];
            $DataOffset =  unpack('V*', substr($b_file_data, ($ifd_offset += DWORD), DWORD))[1];

			/*$ifd_tags[$ifd_tag] = (object) [
				"TagId" => dechex($TagId),
				"DataType" => $DataType,
				"DataCount" => $DataCount,
				"DataOffset" => $DataOffset,
			];*/

            $ifd_offset += DWORD;

			if(dechex($TagId) === "8649") {
                $response["location"] = $DataOffset;
                $response["length"] = $DataCount;
                break;
			}
		}

        $ifd_offset = $DataOffset;
		$ifd_number++;
	}

    return $response;
}

function manageClippingPath($file_path, $svg_path = null) {
	$response = "";
    $image_resources_block = false;
	$file_size = filesize($file_path);
	$file_handle = fopen($file_path, "rb");
	$b_file_data = fread($file_handle, $file_size);
	fclose($file_handle);

    $image_resources_block = readTiff($b_file_data);
	
	if (!empty($image_resources_block)) {
		echo sprintf("PHP::manageClippingPath: original length is %d%s", $image_resources_block["length"], PHP_EOL);
		$image_resources = substr($b_file_data, $image_resources_block["location"], $image_resources_block["length"]);
		$signature_start = 0;
		$data_size_length = DWORD;
        $modified_length = $image_resources_block["length"];
		
		while($signature_start = strpos($image_resources, "8BIM", $signature_start + 1)) {
            $signature = substr($image_resources, $signature_start, DWORD);

			$identifier_start = $signature_start + DWORD;
			$identifier = padHex(dechex(unpack("n*", substr($image_resources, $identifier_start, WORD))[1])); // BIG ENDIAN

			// name is pascal-style encoded.  first byte is length of the name that follows
            $name_size_start = $identifier_start + WORD;
            $name_size = unpack("C*", substr($image_resources, $name_size_start, BYTE));
			
            if ($identifier === CLIPPING_PATH_INFO || $identifier === CLIPPING_PATH_NAME) {
				echo sprintf("PHP::manageClippingPath: 8BIM [signature @ %d-%d]: '%s'%s", $signature_start, $identifier_start - 1, $signature, PHP_EOL);
				echo sprintf("PHP::manageClippingPath: 8BIM [identifier @ %d-%d]: 0x%s%s", $identifier_start, $name_size_start - 1, $identifier, PHP_EOL);

                if (count($name_size) > 0 && $name_size[1] > 0) {
                    $name_start = $name_size_start + BYTE;
                    $name_length = $name_size[1];
                    $name = substr($image_resources, $name_start, $name_length);
					$data_size_start = $name_size_start + BYTE + $name_length;
					echo sprintf("PHP::manageClippingPath: 8BIM [name_size @ %d-%d]: %d%s", $name_size_start, $name_size_start, $name_length, PHP_EOL);
                    echo sprintf("PHP::manageClippingPath: 8BIM [name @ %d-%d]: '%s'%s", $name_start, $data_size_start - 1, $name, PHP_EOL);
                    $name_must_be = "Path 1";

                    if ($name !== $name_must_be) {
                        $name_length_must_be = strlen($name_must_be);
                        $modified_length += ($name_length_must_be - $name_length);
                        $image_resources = substr_replace($image_resources, pack("C*", $name_length_must_be), $name_size_start, BYTE);
                        $image_resources = sprintf("%s%s%s", substr($image_resources, 0, $name_start), $name_must_be, substr($image_resources, $name_start + $name_length));
						$data_size_start = $name_size_start + BYTE + $name_length_must_be;
						$name = substr($image_resources, $name_start, $name_length_must_be);
                        echo sprintf("PHP::manageClippingPath: modified 8BIM [name:length @ %d-%d]: %d%s", $name_size_start, $name_size_start + $name_start - 1, $name_length_must_be, PHP_EOL);
                        echo sprintf("PHP::manageClippingPath: modified 8BIM [name @ %d-%d]: '%s'%s", $name_start, $data_size_start - 1, $name_must_be, PHP_EOL);

						if($identifier === CLIPPING_PATH_INFO) {
							$data_size_start = $name_size_start + BYTE + $name_length_must_be;
							$data_size = unpack("v*", substr($image_resources, $data_size_start, $data_size_length))[1];
							echo sprintf("PHP::manageClippingPath: 8BIM [data:length @ %d-%d]: %d%s", $data_size_start, $data_size_start + $data_size_length - 1, $data_size, PHP_EOL);
						}
                    }
					else {
						if($identifier === CLIPPING_PATH_INFO) {
							$data_size_start = $name_size_start + BYTE + $name_length;
							$data_size = unpack("v*", substr($image_resources, $data_size_start, $data_size_length))[1];
							echo sprintf("PHP::manageClippingPath: 8BIM [data:length @ %d-%d]: %d%s", $data_size_start, $data_size_start + $data_size_length - 1, $data_size, PHP_EOL);
						}
					}

                }
				else {
                    echo sprintf("PHP::manageClippingPath: Name not found!%s", PHP_EOL);
				}
            }
			else {
                $name_length = 0;
                $name = "";
			}
		}
		echo sprintf("PHP::manageClippingPath: modified_length is %d%s", $modified_length, PHP_EOL);

		if($svg_path) {
			$svg_file_size = filesize($svg_path);
			$svg_file_handle = fopen($svg_path, "rb");
			$svg_file_data = fread($svg_file_handle, $svg_file_size);
			fclose($svg_file_handle);


		}
	}
	else {
        echo sprintf("PHP::manageClippingPath: No ImageResources block in this file.", PHP_EOL);
	}

	return $response;
}

function resizeImage($file_path) {
	echo sprintf("PHP::resizeImage: Resizing %s ...%s", $file_path, PHP_EOL);
	$file_parts_array = explode(".", $file_path);
	$file_name = $file_parts_array[0];
	$file_extension = $file_parts_array[1];
	$file_parts_array = explode("_", $file_name);
    $file_base_name = $file_parts_array[0];
    $file_metadata = $file_parts_array[1];
	$file_metadata_array = str_split($file_metadata);

	echo sprintf("Imagemagick: Trimming and making background transparent %s", PHP_EOL);
	exec(sprintf('convert %s -fuzz 0.1%% -define trim:percent-background=80%% -trim +repage -background transparent -flatten -alpha set -quiet %s', $file_path, $file_path));
	$original_image = new Imagick($file_path);
	$original_image->trimImage(0.01);
    $original_image->setImagePage(0, 0, 0, 0);
	echo sprintf("Imagick: original image dimensions: %dx%d%s", $original_image->getImageWidth(), $original_image->getImageHeight(), PHP_EOL);
	$width = $original_image->getImageWidth();
	$height = $original_image->getImageHeight();
	echo sprintf("Imagick: Working image dimensions (after trim): %dx%d%s", $width, $height, PHP_EOL);
	// Take the short side and increase the canvas with a 2,5% (of the pixels of the shortest side), where the image will stay
	// in the center. So a portrait picture will have an additional margin on bottom and top, a landscape on the left and right
	// then, change the aspect ratio of the canvas (not image) dimensions (horz / verz) to 2:3

	if ($height > $width) {
		// image is portrait, short side is width
		// add margin
		$new_height = $height * 1.05;
		// adjust aspect ratio
		$new_width = $new_height / 3 * 2;
		echo sprintf("PHP::resizeImage: Calculated canvas dimensions (portrait): %dx%d%s", $new_width, $new_height, PHP_EOL);
		
		if ($new_width < $width) {
			echo "PHP::resizeImage: Adjusting for narrow canvas" . PHP_EOL;
			$new_width = $width * 1.05;
			$new_height = $new_width / 2 * 3;
			echo sprintf("PHP::resizeImage: Calculated canvas dimensions (portrait): %dx%d%s", $new_width, $new_height, PHP_EOL);
		}
	} 
	else // image is landscape, short side is height
	{
		// add margin
		$new_width = $width * 1.05;
		// adjust aspect ratio
		$new_height = $new_width / 3 * 2;
		echo sprintf("PHP::resizeImage: Calculated canvas dimensions (landscape): %dx%d%s", $new_width, $new_height, PHP_EOL);

	if ($new_height < $height) {
			echo "PHP::resizeImage: Adjusting for short canvas" . PHP_EOL;
			$new_height = $height * 1.05;
			$new_width = $new_height / 2 * 3;
			echo sprintf("PHP::resizeImage: Calculated canvas dimensions (landscape): %dx%d%s", $new_width, $new_height, PHP_EOL);
		}
	}

	$left_margin = ($new_width - $width) / 2;
	echo sprintf("PHP::resizeImage: Left margin: %d%s", $left_margin, PHP_EOL);
	$top_margin = ($new_height - $height) / 2;
	echo sprintf("PHP::resizeImage: Top margin: %d%s", $top_margin, PHP_EOL);
	$modified_image = new Imagick($file_path);
	echo sprintf("Imagick: Creating image on new canvas%s", PHP_EOL);
	$new_image = new Imagick();
	$new_image->newPseudoImage($new_width, $new_height, "canvas:transparent");
	$new_image->setImageUnits(imagick::RESOLUTION_PIXELSPERINCH);
	$new_image->setImageResolution(300, 300);

	// this action removes any guides and clipping paths if present
	$new_image->compositeImage($modified_image, imagick::COMPOSITE_SRC, $left_margin, $top_margin);

	// modify ICC Profile to 'Adobe RGB (1998)' -> this needs the following file in the main folder
	$profile = file_get_contents("Adobe RGB (1998) Profile.icc");
	$new_image->profileImage("icc", $profile);
	$new_image->transformImageColorspace(imagick::COLORSPACE_SRGB);
	$new_image->setImageDepth(8);

	// save file as TIFF with image compression LZW, with transparency
	$new_image->setFormat("tiff");
	$new_image->setCompression(imagick::COMPRESSION_LZW);

	// ensure that the filename is formatted correctly
	validateFilename($file_name, $file_extension, $file_base_name, $file_path, $file_name_changed);

	// ensure that the file name metadata is correct for the image size once it's been resized
    validateFilenameMetadata($file_base_name, $file_extension, $file_metadata, $file_metadata_array, $new_width, $new_height, $file_path, $file_name_changed);

	$new_image->writeImage($file_path);
	echo sprintf("Imagick: Resized image %s created%s", $file_path, PHP_EOL);

	if($file_name_changed) {
        //unlink($original_file_path);
	}

	// create and svg outline of the image
    $svg_file_path = createSVGOutline($file_name, $file_path);

	// add the clipping path from the svg file to the tiff file
    $final_file_path = addClippingPath($file_name, $file_path, $svg_file_path);

	// manage the clipping path
    manageClippingPath($final_file_path);

	// remove tmp files created by imagemagick
	foreach(scandir("/tmp/") as $tmp_file) {
		if(substr($tmp_file, 0,7) === "magic-") {
			unlink(sprintf("/tmp/%s", $tmp_file));
		}
	}

    return $final_file_path;
}

global $source_path, $gimp_command;

$gimp_command = "/Applications/GIMP.app/Contents/MacOS/GIMP"; //"gimp";

if(empty($argv[1])) {
	$exit = false;
	echo sprintf("Scanning started (will wake up every 60 seconds and will produce no output if nothing is found) ...%s", PHP_EOL);
		
	while ($exit !== true) {
		$source_path = "/media/picture_share/workflow/convert_todo";
		$destination_path = "/media/picture_share/workflow/convert_done/";
		$folders = scandir($source_path);
		
		if(!empty($folders)) {
			$glns = array_filter($folders, function ($folder) {
			return preg_match("/^[0-9]+$/", $folder) && substr($folder, 0, 1) !== ".";
			});
		
			foreach ($glns as $gln) {
				$source_file_path = sprintf("%s/%s", $source_path, $gln);
				$destination_file_path = sprintf("%s/%s", $destination_path, $gln);
				
				if(is_dir($source_file_path)) {
					// scan the source path
					// and return only those files that begin with 13 digits
					$files = array_filter((array)scandir($source_file_path), function ($file) {
						// filter out anything less than 13 characters in length and that do not begin with 13 digits
						return strlen($file) >= 13 && preg_match("/^[0-9]+$/", substr($file, 0, 13));
					});

					if (!empty($files)) {
						foreach ($files as $file) {
							exec(sprintf("rsync --remove-source-files -azvr %s/%s .", $source_file_path, $file));    
							$filename = resizeImage($file);
							
							if (!is_dir($destination_file_path)) {
								mkdir($destination_file_path);
								chown($destination_file_path, "localadmin");
								chgrp($destination_file_path, "localadmin");
								chmod($destination_file_path, 0777);
							}
							exec(sprintf("rsync -azvr --remove-source-files %s %s/", $filename, $destination_file_path));
						}
					}

					exec(sprintf("rm -fr %s", $source_file_path));
					echo sprintf("Converted all files in %s%s", $gln, PHP_EOL);
				}
				echo sprintf("Converted all files in this batch%s",PHP_EOL);
			}
			sleep(60);
		}
	}
}
else {
    //resizeImage("clippath/04027800410705_A1N1.tiff");//$argv[1]);
    $file_name = "clippath/04027800410705_C1N1";
    $file_path = "clippath/04027800410705_C1N1.tiff";
	$final_file_path = "clippath/08715271100244_A1N1-original.tiff";
	$svg_file_path = "clippath/04027800410705_C1N1.svg";
	//$svg_file_path = createSVGOutline($file_name, $file_path);
    //$final_file_path = addClippingPath($file_name, $file_path, $svg_file_path);
	//manageClippingPath($final_file_path);

	manageClippingPath($file_path, $svg_file_path);
}
