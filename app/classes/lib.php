<?php


/**
 * Recursively creates chmodded directories. Returns true on success,
 * and false on failure.
 *
 * NB! Directories are created with permission 777 - worldwriteable -
 * unless you have set 'chmod_dir' to 0XYZ in the advanced config.
 *
 * @param string $name
 * @return boolean
 */
function makeDir($name) {


    // if it exists, just return.
    if (file_exists($name)) {
        return true;
    }

    // If more than one level, try parent first..
    // If creating parent fails, we can abort immediately.
    if (dirname($name) != ".") {
        $success = makeDir(dirname($name));
        if (!$success) {
            return false;
        }
    }

    if (empty($mode)) {
        $mode = '0777';
    }
    $mode_dec = octdec($mode);

    $oldumask = umask(0);
    $success = @mkdir ($name, $mode_dec);
    @chmod ($name, $mode_dec);
    umask($oldumask);

    return $success;

}

/** 
 * generate a CSRF-like token, to use in GET requests for stuff that ought to be POST-ed forms.
 *
 * @return string $token
 */
function getToken() {
   $seed = $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . $_COOKIE['PHPSESSID'];
   $token = substr(md5($seed), 0, 8);
   return $token;
}


/**
 * Check if a given token matches the current (correct) CSRF-like token
 * 
 * @param string $token
 *
 * @return bool
 */
function checkToken($token="") {
    global $app;

    if (empty($token)) { 
        $token = $app['request']->get('token');
    }

    if ($token === getToken()) {
        return true;
    } else {
        $app['session']->setFlash('error', "The security token was incorrect. Please try again.");
        return false;
    }

}

/**
 * Clean posted data. Convert tabs to spaces (primarily for yaml) and
 * stripslashes when magic quotes are turned on. 
 *
 * @param mixed $var
 * @return string
 */
function cleanPostedData($var) {
    
    if (is_array($var)) {
    
        foreach($var as $key => $value) {
            $var[$key] = cleanPostedData($value);
        }
        
    } else if (is_string($var)) {
    
        $var = str_replace("\t", "    ", $var);
        
        // Ah, the joys of \"magic quotes\"!
        if (get_magic_quotes_gpc()) {
            $var = stripslashes($var);
        }          
        
    }
    
    return $var;
    
}



function clearCache() {
    
    $result = array(
        'successfiles' => 0,
        'failedfiles' => 0,
        'successfolders' => 0,
        'failedfolders' => 0,
        'log' => ''
    );
      
    clearCacheHelper('', $result);
    
    return $result;   
    
}


function clearCacheHelper($additional, &$result) {
    
    $basefolder = __DIR__."/../cache/";
    
    $currentfolder = realpath($basefolder."/".$additional);

    if (!file_exists($currentfolder)) {
        $result['log'] .= "Folder $currentfolder doesn't exist.<br>";
        return;
    }
    
    $d = dir($currentfolder);

    while (false !== ($entry = $d->read())) {
       
       if ($entry == "." || $entry == ".." || $entry == "index.html" ) {
           continue;
       }
       
       if (is_file($currentfolder."/".$entry)) {
           if (is_writable($currentfolder."/".$entry) && unlink($currentfolder."/".$entry)) {
               $result['successfiles']++;
           } else {
               $result['failedfiles']++;
           }          
       }
       
       if (is_dir($currentfolder."/".$entry)) {
          
           clearCacheHelper($additional."/".$entry, $result);

           if (@rmdir($currentfolder."/".$entry)) {
               $result['successfolders']++;
           } else {
               $result['failedfolders']++;
           }


       }
              
       
    }
    
    $d->close();
    
}

function findFiles($term, $extensions="") {
    
    if (is_string($extensions)) {
        $extensions = explode(",", $extensions);
    }
    
    $files = array();
    
    findFilesHelper('', $files, strtolower($term), $extensions);
    
    // Sort the array, and only keep the values, not the keys. 
    natcasesort($files);
    $files = array_values($files);
    
    return $files;
    
}

function findFilesHelper($additional, &$files, $term="", $extensions=array()) {
    
    $basefolder = __DIR__."/../../files/";
    
    $currentfolder = realpath($basefolder."/".$additional);

    $d = dir($currentfolder);
    
    $ignored = array(".", "..", ".DS_Store", ".gitignore", ".htaccess");
    
    while (false !== ($entry = $d->read())) {
        
        if (in_array($entry, $ignored) || substr($entry, 0, 2) == "._" ) { continue; }
        
        if (is_file($currentfolder."/".$entry) && is_readable($currentfolder."/".$entry)) {        
            
            // Check for 'term'..
            if (!empty($term) && (strpos(strtolower($currentfolder."/".$entry), $term) === false)) {
                continue; // skip this one..
            }
            
            // Check for correct extensions.. 
            if (!empty($extensions) && !in_array(getExtension($entry), $extensions)) {
                continue; // Skip files without correct extension..
            }
            
            if (!empty($additional)) { 
                $filename = $additional . "/" . $entry; 
            } else {
                $filename = $entry;
            }
            
            $files[] = $filename;       
        }
        
        if (is_dir($currentfolder."/".$entry)) {
            findFilesHelper($additional."/".$entry, $files, $term, $extensions);
        }
        
        
    }
        
    $d->close();    
    
}


function getFilePermissions($filename) {
        
    $perms = fileperms($filename);
    
    if (($perms & 0xC000) == 0xC000) {
        $info = 's'; // Socket
    } elseif (($perms & 0xA000) == 0xA000) {
        $info = 'l'; // Symbolic Link
    } elseif (($perms & 0x8000) == 0x8000) {
        $info = '-'; // Regular
    } elseif (($perms & 0x6000) == 0x6000) {
        $info = 'b'; // Block special
    } elseif (($perms & 0x4000) == 0x4000) {
        $info = 'd'; // Directory
    } elseif (($perms & 0x2000) == 0x2000) {
        $info = 'c'; // Character special
    } elseif (($perms & 0x1000) == 0x1000) {
        $info = 'p'; // FIFO pipe
    } else {
        $info = 'u'; // Unknown
    }
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));
    
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));
    
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));
    
    return $info;    
    
}

// http://pastebin.com/iKky8Vtu
// http://www.php.net/manual/en/function.fileperms.php#98437
function mkperms($perms, $return_as_string = false, $filename = '') {
    $perms = explode(',', $perms);
    $generated = array('u'=>array(),'g'=>array(),'o'=>array());
    if(!empty($filename)) {
        $fperms = substr(decoct(fileperms($filename)), 3); // Credits to jchris dot fillionr at kitware dot com
       // Fill array $generated
        $fperms = str_split($fperms);
        $fperms['u'] = $fperms[0]; unset($fperms[0]);
        $fperms['g'] = $fperms[1]; unset($fperms[1]);
        $fperms['o'] = $fperms[2]; unset($fperms[2]);
        foreach($fperms as $key=>$fperm) {
            if($fperm >= 4) {
               $generated[$key]['r'] = true;
               $fperm -= 4;
            }
            if($fperm >= 2) {
               $generated[$key]['w'] = true;
               $fperm -= 2;
            }
            if($fperm >= 1) {
               $generated[$key]['x'] = true;
               $fperm--;
            } 
        }
    }
    foreach($perms as $perm) {
         if(!preg_match('#^([ugo]*)([\+=-])([rwx]+|[\-])$#i', $perm, $matches)) {
             trigger_error('Wrong input format for mkperms'); return 0644;
             // Wrong format => generate default 
         }
         $targets = str_split($matches[1]);
         $addrem = $matches[2];
         $perms_ = str_split($matches[3]);
         $fromTheLoop = 0; // To make sure we clear it only once for direct affectation
         foreach($targets as $target) {
                 foreach($perms_ as $perms__) {
                     if($addrem == '=') {
                         if(!$fromTheLoop) {
                             unset($generated[$target]['r']);
                             unset($generated[$target]['w']);
                             unset($generated[$target]['x']);
                         }
                         $fromTheLoop++;
                         $addrem = '+';
                     }
                     if($perms__ == '-') {
                         unset($generated[$target]['r']);
                         unset($generated[$target]['w']);
                         unset($generated[$target]['x']);
                     } else {
                         if($addrem == '+') {
                             $generated[$target][$perms__] = true;
                         } elseif($addrem == '-') {
                             unset($generated[$target][$perms__]);
                         } elseif($addrem == '=') {
                             
                         }
                     }
                 }
         }
    }
    $generated_chars    = array(0, 0, 0);
    $corresponding      = array('u'=>0, 'g'=>1, 'o'=>2);
    $correspondingperms = array('r'=>4, 'w'=>2, 'x'=>1);

    foreach($generated as $key=>$generated_) {
        foreach($generated_ as $generated__=>$useless) {
            $generated_chars[$corresponding[$key]] += $correspondingperms[$generated__];
        }
    }
    if($return_as_string) return implode($generated_chars);
 else return base_convert(implode($generated_chars), 8, 10);
}


/**
 * Ensures that a path has no trailing slash
 *
 * @param string $path
 * @return string
 */
function stripTrailingSlash($path) {
    if(substr($path,-1,1) == "/") {
        $path = substr($path,0,-1);
    }
    return $path;   
}

/**
 * Gets current Unix timestamp (in seconds) with microseconds, as a float.
 *
 * @return float
 */
function getMicrotime(){
    list($usec, $sec) = explode(" ",microtime());
    return ((float)$usec + (float)$sec);
}

/**
 * Calculates time that was needed for execution.
 *
 * @param integer $precision§
 * @return string
 */
function timeTaken($precision = 2) {
    global $starttime;
    $endtime = getMicrotime();
    $time_taken = $endtime - $starttime;
    $time_taken= number_format($time_taken, $precision); 
    
    return $time_taken;

}


/**
 * Get the amount of used memory, if memory_get_usage is defined.
 *
 * @return string
 */
function getMem() {

    if (function_exists('memory_get_usage')) {
        $mem = memory_get_usage();
        return formatFilesize($mem);
    } else {
        return "unknown";
    }
}



/**
 * Get the maximum amount of used memory, if memory_get_usage is defined.
 *
 * @return string
 */
function getMaxMem() {

    if (function_exists('memory_get_peak_usage')) {
        $mem = memory_get_peak_usage();
        return formatFilesize($mem);
    } else {
        return "unknown";
    }
}



/**
 * Format a filesize like '10.3 kb' or '2.5 mb'
 *
 * @param integer $size
 * @return string
 */
function formatFilesize($size) {

    if ($size > 1024*1024 ) {
        return sprintf("%0.2f mb", ($size/1024/1024));
    } else if ($size > 1024 ) {
        return sprintf("%0.2f kb", ($size/1024));
    } else {
        return $size." b";
    }

}


/**
 * Makes a random key with the specified length.
 *
 * @param int $length
 * @return string
 */
function makeKey($length) {

    $seed = "0123456789abcdefghijklmnopqrstuvwxyz";
    $len = strlen($seed);
    $key = "";

    for ($i=0;$i<$length;$i++) {
        $key .= $seed[ rand(0,$len-1) ];
    }

    return $key;

}


/**
 * Gets the extension (if any) of a filename.
 *
 * @param string $filename
 * @return string
 */
function getExtension($filename) {
    $pos=strrpos($filename, ".");
    if ($pos === false) {
        return "";
    } else {
        $ext=substr($filename, $pos+1);
        return $ext;
    }
}




/**
 * Returns a "safe" version of the given string - basically only US-ASCII and
 * numbers. Needed because filenames and titles and such, can't use all characters.
 *
 * @param string $str
 * @param boolean $strict
 * @return string
 */
function safeString($str, $strict=false, $extrachars="") {

    // replace UTF-8 non ISO-8859-1 first
    $str = strtr($str, array(
        "\xC3\x80"=>'A', "\xC3\x81"=>'A', "\xC3\x82"=>'A', "\xC3\x83"=>'A',
        "\xC3\x84"=>'A', "\xC3\x85"=>'A', "\xC3\x87"=>'C', "\xC3\x88"=>'E',
        "\xC3\x89"=>'E', "\xC3\x8A"=>'E', "\xC3\x8B"=>'E', "\xC3\x8C"=>'I',
        "\xC3\x8D"=>'I', "\xC3\x8E"=>'I', "\xC3\x8F"=>'I', "\xC3\x90"=>'D',
        "\xC3\x91"=>'N', "\xC3\x92"=>'O', "\xC3\x93"=>'O', "\xC3\x94"=>'O',
        "\xC3\x95"=>'O', "\xC3\x96"=>'O', "\xC3\x97"=>'x', "\xC3\x98"=>'O',
        "\xC3\x99"=>'U', "\xC3\x9A"=>'U', "\xC3\x9B"=>'U', "\xC3\x9C"=>'U',
        "\xC3\x9D"=>'Y', "\xC3\xA0"=>'a', "\xC3\xA1"=>'a', "\xC3\xA2"=>'a',
        "\xC3\xA3"=>'a', "\xC3\xA4"=>'a', "\xC3\xA5"=>'a', "\xC3\xA7"=>'c',
        "\xC3\xA8"=>'e', "\xC3\xA9"=>'e', "\xC3\xAA"=>'e', "\xC3\xAB"=>'e',
        "\xC3\xAC"=>'i', "\xC3\xAD"=>'i', "\xC3\xAE"=>'i', "\xC3\xAF"=>'i',
        "\xC3\xB1"=>'n', "\xC3\xB2"=>'o', "\xC3\xB3"=>'o', "\xC3\xB4"=>'o',
        "\xC3\xB5"=>'o', "\xC3\xB6"=>'o', "\xC3\xB8"=>'o', "\xC3\xB9"=>'u',
        "\xC3\xBA"=>'u', "\xC3\xBB"=>'u', "\xC3\xBC"=>'u', "\xC3\xBD"=>'y',
        "\xC3\xBF"=>'y', "\xC4\x80"=>'A', "\xC4\x81"=>'a', "\xC4\x82"=>'A',
        "\xC4\x83"=>'a', "\xC4\x84"=>'A', "\xC4\x85"=>'a', "\xC4\x86"=>'C',
        "\xC4\x87"=>'c', "\xC4\x88"=>'C', "\xC4\x89"=>'c', "\xC4\x8A"=>'C',
        "\xC4\x8B"=>'c', "\xC4\x8C"=>'C', "\xC4\x8D"=>'c', "\xC4\x8E"=>'D',
        "\xC4\x8F"=>'d', "\xC4\x90"=>'D', "\xC4\x91"=>'d', "\xC4\x92"=>'E',
        "\xC4\x93"=>'e', "\xC4\x94"=>'E', "\xC4\x95"=>'e', "\xC4\x96"=>'E',
        "\xC4\x97"=>'e', "\xC4\x98"=>'E', "\xC4\x99"=>'e', "\xC4\x9A"=>'E',
        "\xC4\x9B"=>'e', "\xC4\x9C"=>'G', "\xC4\x9D"=>'g', "\xC4\x9E"=>'G',
        "\xC4\x9F"=>'g', "\xC4\xA0"=>'G', "\xC4\xA1"=>'g', "\xC4\xA2"=>'G',
        "\xC4\xA3"=>'g', "\xC4\xA4"=>'H', "\xC4\xA5"=>'h', "\xC4\xA6"=>'H',
        "\xC4\xA7"=>'h', "\xC4\xA8"=>'I', "\xC4\xA9"=>'i', "\xC4\xAA"=>'I',
        "\xC4\xAB"=>'i', "\xC4\xAC"=>'I', "\xC4\xAD"=>'i', "\xC4\xAE"=>'I',
        "\xC4\xAF"=>'i', "\xC4\xB0"=>'I', "\xC4\xB1"=>'i', "\xC4\xB4"=>'J',
        "\xC4\xB5"=>'j', "\xC4\xB6"=>'K', "\xC4\xB7"=>'k', "\xC4\xB8"=>'k',
        "\xC4\xB9"=>'L', "\xC4\xBA"=>'l', "\xC4\xBB"=>'L', "\xC4\xBC"=>'l',
        "\xC4\xBD"=>'L', "\xC4\xBE"=>'l', "\xC4\xBF"=>'L', "\xC5\x80"=>'l',
        "\xC5\x81"=>'L', "\xC5\x82"=>'l', "\xC5\x83"=>'N', "\xC5\x84"=>'n',
        "\xC5\x85"=>'N', "\xC5\x86"=>'n', "\xC5\x87"=>'N', "\xC5\x88"=>'n',
        "\xC5\x89"=>'n', "\xC5\x8A"=>'N', "\xC5\x8B"=>'n', "\xC5\x8C"=>'O',
        "\xC5\x8D"=>'o', "\xC5\x8E"=>'O', "\xC5\x8F"=>'o', "\xC5\x90"=>'O',
        "\xC5\x91"=>'o', "\xC5\x94"=>'R', "\xC5\x95"=>'r', "\xC5\x96"=>'R',
        "\xC5\x97"=>'r', "\xC5\x98"=>'R', "\xC5\x99"=>'r', "\xC5\x9A"=>'S',
        "\xC5\x9B"=>'s', "\xC5\x9C"=>'S', "\xC5\x9D"=>'s', "\xC5\x9E"=>'S',
        "\xC5\x9F"=>'s', "\xC5\xA0"=>'S', "\xC5\xA1"=>'s', "\xC5\xA2"=>'T',
        "\xC5\xA3"=>'t', "\xC5\xA4"=>'T', "\xC5\xA5"=>'t', "\xC5\xA6"=>'T',
        "\xC5\xA7"=>'t', "\xC5\xA8"=>'U', "\xC5\xA9"=>'u', "\xC5\xAA"=>'U',
        "\xC5\xAB"=>'u', "\xC5\xAC"=>'U', "\xC5\xAD"=>'u', "\xC5\xAE"=>'U',
        "\xC5\xAF"=>'u', "\xC5\xB0"=>'U', "\xC5\xB1"=>'u', "\xC5\xB2"=>'U',
        "\xC5\xB3"=>'u', "\xC5\xB4"=>'W', "\xC5\xB5"=>'w', "\xC5\xB6"=>'Y',
        "\xC5\xB7"=>'y', "\xC5\xB8"=>'Y', "\xC5\xB9"=>'Z', "\xC5\xBA"=>'z',
        "\xC5\xBB"=>'Z', "\xC5\xBC"=>'z', "\xC5\xBD"=>'Z', "\xC5\xBE"=>'z',
        ));

    // utf8_decode assumes that the input is ISO-8859-1 characters encoded
    // with UTF-8. This is OK since we want US-ASCII in the end.
    $str = trim(utf8_decode($str));

    $str = strtr($str, array("\xC4"=>"Ae", "\xC6"=>"AE", "\xD6"=>"Oe", "\xDC"=>"Ue", "\xDE"=>"TH",
        "\xDF"=>"ss", "\xE4"=>"ae", "\xE6"=>"ae", "\xF6"=>"oe", "\xFC"=>"ue", "\xFE"=>"th"));

    $str=str_replace("&amp;", "", $str);

    $delim = '/';
    if ($extrachars != "") {
        $extrachars = preg_quote($extrachars, $delim);
    }
    if ($strict) {
        $str = strtolower(str_replace(" ", "-", $str));
        $regex = "[^a-zA-Z0-9_".$extrachars."-]";
    } else {
        $regex = "[^a-zA-Z0-9 _.,".$extrachars."-]";
    }

    $str = preg_replace("$delim$regex$delim", "", $str);

    return $str;
}



/**
 * Modify a string, so that we can use it for slugs. Like
 * safeString, but using hyphens instead of underscores.
 *
 * @param string $str
 * @param string $type
 * @return string
 */
function makeSlug($str) {

    $str = safeString($str);

    $str = str_replace(" ", "-", $str);
    $str = strtolower(preg_replace("/[^a-zA-Z0-9_-]/i", "", $str));
    $str = preg_replace("/[-]+/i", "-", $str);

    $str = substr($str,0,64); // 64 chars ought to be long enough.

    return $str;

}

/**
 * Make a simple array consisting of key=>value pairs, that can be used
 * in select-boxes in forms.
 *
 * @param array $array
 * @param string $key
 * @param string $value
 */
function makeValuepairs($array, $key, $value) {

        $temp_array = array();

        if (is_array($array)) {
                foreach($array as $item) {
                        if (empty($key)) {
                            $temp_array[] = $item[$value];
                        } else {
                            $temp_array[$item[$key]] = $item[$value];
                        }

                }
        }

        return $temp_array;

}



/**
 * Trim a text to a given length, taking html entities into account.
 *
 * Formerly we first removed entities (using unentify), cut the text at the
 * wanted length and then added the entities again (using entify). This caused
 * lot of problems so now we are using a trick from
 * http://www.greywyvern.com/code/php/htmlwrap.phps
 * where entities are replaced by the ACK (006) ASCII symbol, the text cut and
 * then the entities reinserted.
 *
 * @param string $str string to trim
 * @param int $length position where to trim
 * @param boolean $nbsp whether to replace spaces by &nbsp; entities
 * @param boolean $hellip whether to add … at the end
 *
 * @return string trimmed string
 */
function trimText($str, $length, $nbsp=false, $hellip=true, $striptags=true) {

    if ($striptags) {
        $str = strip_tags($str);
    }

    $str = trim($str);

    // Use the ACK (006) ASCII symbol to replace all HTML entities temporarily
    $str = str_replace("\x06", "", $str);
    preg_match_all("/&([a-z\d]{2,7}|#\d{2,5});/i", $str, $ents);
    $str = preg_replace("/&([a-z\d]{2,7}|#\d{2,5});/i", "\x06", $str);

    if (function_exists('mb_strwidth') ) {
        if (mb_strwidth($str)>$length) {
            $str = mb_strimwidth($str,0,$length+1, '', 'UTF-8');
            if ($hellip) {
                $str .= '…';
            }
        }
    } else {
        if (strlen($str)>$length) {
            $str = substr($str,0,$length+1);
            if ($hellip) {
                $str .= '…';
            }
        }
    }

    if ($nbsp==true) {
        $str=str_replace(" ", "&nbsp;", $str);
    }

    $str=str_replace("http://", "", $str);

    // Put captured HTML entities back into the string
    foreach ($ents[0] as $ent) {
        $str = preg_replace("/\x06/", $ent, $str, 1);
    }

    return $str;

}

/**
 * parse the used .twig templates from the Twig Loader object, using
 * regular expressions. 
 * We use this for showing them in the debug toolbar. 
 *
 * @param object $obj
 *
 */
function hackislyParseRegexTemplates($obj) {
    
    $str = print_r($obj, true);
    
    preg_match_all('/(\/[a-z0-9_\/-]+\.twig)/i', $str, $matches);
    
    $templates = array();
    
    foreach($matches[1] as $match) {
        $templates[] = basename(dirname($match)) . "/" . basename($match);
    }
    
    return $templates;
    
}

function getConfig() {
    
    $config = array();
    
    // Read the config
    $yamlparser = new Symfony\Component\Yaml\Parser();
    $config['general'] = $yamlparser->parse(file_get_contents(__DIR__.'/../config/config.yml'));
    $config['taxonomy'] = $yamlparser->parse(file_get_contents(__DIR__.'/../config/taxonomy.yml'));
    $tempcontenttypes = $yamlparser->parse(file_get_contents(__DIR__.'/../config/contenttypes.yml'));
    $config['menu'] = $yamlparser->parse(file_get_contents(__DIR__.'/../config/menu.yml'));

    // TODO: If no config files can be found, get them from bolt.cm/files/default/

    // echo "<pre>\n" . util::var_dump($config['menu'], true) . "</pre>\n";
    
    // Assume some sensible defaults for some options
    $defaultconfig = array(
        'sitename' => 'Default Bolt site',
        'homepage' => 'page/*',
        'homepage_template' => 'index.twig',
        'recordsperpage' => 10,
        'recordsperdashboardwidget' => 5,
        'debug' => false,
        'strict_variables' => false,
        'theme' => "default",
        'debug_compressjs' => true,
        'debug_compresscss' => true,
        'listing_template' => 'listing.twig',
        'listing_records' => '5',
        'wysiwyg_images' => false,
        'wysiwyg_tables' => false,
        'wysiwyg_embed' => false,
        'wysiwyg_fontcolor' => false,
        'wysiwyg_align' => false

    );
    $config['general'] = array_merge($defaultconfig, $config['general']);

    // TODO: Think about what to do with these..
    /*
    # Date and Time formats
    shortdate: j M ’ye
    longdate: l j F Y
    shorttime: H:i
    longtime: H:i:s
    fulldatetime: Y-m-d H:i:s
    */

    // Clean up taxonomies
    foreach( $config['taxonomy'] as $key => $value ) {
        if (!isset($config['taxonomy'][$key]['name'])) {
            $config['taxonomy'][$key]['name'] = ucwords($config['taxonomy'][$key]['slug']);
        }
        if (!isset($config['taxonomy'][$key]['singular_name'])) {
            $config['taxonomy'][$key]['singular_name'] = ucwords($config['taxonomy'][$key]['singular_slug']);
        }
    }

    // Clean up contenttypes
    $config['contenttypes'] = array();
    foreach($tempcontenttypes as $temp ) {
        if (!isset($temp['slug'])) {
            $temp['slug'] = makeSlug($temp['name']);
        }
        if (!isset($temp['singular_slug'])) {
            $temp['singular_slug'] = makeSlug($temp['singular_name']);
        }
        $config['contenttypes'][ $temp['slug'] ] = $temp;
    }

    // Get the script's filename, but _without_ SCRIPT_FILENAME
    $scripturi = str_replace("#".dirname($_SERVER['SCRIPT_NAME']), '', "#".$_SERVER['REQUEST_URI']);

    // I don't think we can set Twig's path in runtime, so we have to resort to hackishness to set the path..
    // If the request URI starts with '/bolt' in the URL, we assume we're in the Backend.. Yeah.. Awesome..
    if (strpos($scripturi, "bolt") !== false ) {
        $config['twigpath'] = __DIR__.'/../view';
    } else {
        $themepath = __DIR__.'/../../theme/'. basename($config['general']['theme']);
        $config['twigpath'] = array($themepath, __DIR__.'/../view');
    }

    return $config;


}


function getDBOptions($config) {

    $configdb = $config['general']['database'];

    if (isset($configdb['driver']) && ( $configdb['driver'] == "pdo_sqlite" || $configdb['driver'] == "sqlite" ) ) {

        $basename = isset($configdb['databasename']) ? basename($configdb['databasename']) : "bolt";
        if (getExtension($basename)!="db") { $basename .= ".db"; };

        $dboptions = array(
            'driver' => 'pdo_sqlite',
            'path' => __DIR__ . "/../database/" . $basename
        );

    } else {
        // Assume we configured it correctly. Yeehaa!

        $driver = (isset($configdb['driver']) ? $configdb['driver'] : 'pdo_mysql');
        if ($driver == "mysql" || $driver == "mysqli") { $driver = 'pdo_mysql'; }
        if ($driver == "postgres" || $driver == "postgresql") { $driver = 'pdo_postgres'; }

        $dboptions = array(
            'driver'    => $driver,
            'host'      => (isset($configdb['host']) ? $configdb['host'] : 'localhost'),
            'dbname'    => $configdb['databasename'],
            'user'      => $configdb['username'],
            'password'  => $configdb['password'],
            'port'      => (isset($configdb['port']) ? $configdb['port'] : '3306'),
        );

    }

    //echo "<pre>\n" . util::var_dump($dboptions, true) . "</pre>\n";

    return $dboptions;

}


function getPaths($config) {

    // Set the root
    $path_prefix = dirname($_SERVER['PHP_SELF'])."/";
    $path_prefix = str_replace("//", "/", str_replace("\\", "/", $path_prefix));
    if (empty($path_prefix)) { $path_prefix = "/"; }

    $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';

    // Set the paths
    $paths = array(
        'hostname' => $_SERVER['HTTP_HOST'],
        'root' => $path_prefix,
        'rootpath' => realpath(__DIR__ . "/../../"),
        'theme' => $path_prefix . "theme/" . $config['general']['theme'] . "/",
        'themepath' => realpath(__DIR__ . "/../../theme/" . $config['general']['theme']),
        'app' => $path_prefix . "app/",
        'apppath' => realpath(__DIR__ . "/.."),
        'bolt' => $path_prefix . "bolt/",
        'async' => $path_prefix . "async/",
        'files' => $path_prefix . "files/",
        'filespath' => realpath(__DIR__ . "/../../files"),
    );

    $paths['url'] = sprintf("%s://%s%s", $protocol, $paths['hostname'], $paths['root']);

    return $paths;

}

/**
 *
 * Simple wrapper for $app['url_generator']->generate()
 *
 * @param string $path
 * @param array $param
 * @param string $add
 * @return string
 */
function path($path, $param=array(), $add='') {
    global $app;

    if (!empty($add) && $add[0]!="?") {
        $add = "?" . $add;
    }

    if (empty($param)) {
        $param = array();
    }

    return $app['url_generator']->generate($path, $param). $add;

}


/**
 *
 * Simple wrapper for $app->redirect($app['url_generator']->generate());
 *
 * @param string $path
 * @param array $param
 * @param string $add
 * @return string
 */
function redirect($path, $param=array(), $add='') {
    global $app;

    return $app->redirect(path($path, $param, $add));

}


/**
 *
 * Helper function to insert some HTML into the head section of an HTML page.
 *
 * @param string $tag
 * @param string $html
 * @return string
 */
function insertAfterMeta($tag, $html)
{

    // first, attempt ot insert it after the last meta tag, matching indentation..

    if (preg_match_all("~^([ \t]+)<meta (.*)~mi", $html, $matches)) {
        //echo "<pre>\n" . util::var_dump($matches, true) . "</pre>\n";

        // matches[0] has some elements, the last index is -1, because zero indexed.
        $last = count($matches[0])-1;
        $replacement = sprintf("%s\n%s%s", $matches[0][$last], $matches[1][$last], $tag);
        $html = str_replace($matches[0][$last], $replacement, $html);

    } elseif (preg_match("~^([ \t]+)</head~mi", $html, $matches)) {

        //echo "<pre>\n" . util::var_dump($matches, true) . "</pre>\n";
        // Try to insert it just before </head>
        $replacement = sprintf("%s\t%s\n%s", $matches[1], $tag, $matches[0]);
        $html = str_replace($matches[0], $replacement, $html);

    } else {

        // Since we're serving tag soup, just append it.
        $html .= $tag;

    }

    return $html;

}

/**
 * If debug is enabled this function handles the errors and warnings
 *
 * @param integer $errno
 * @param string $errmsg
 * @param string $filename
 * @param integer $linenum
 * @param array $vars
 */
function userErrorHandler ($errno, $errmsg, $filename, $linenum, $vars) {
    global $app;

    $replevel = error_reporting();
    if( ( $errno & $replevel ) != $errno )
    {
        // we shall remain quiet.
        return;
    }

    // define an assoc array of error string
    // in reality the only entries we should
    // consider are 2,8,256,512 and 1024
    $errortype = array (
        1    => "Error",
        2    => "Warning",
        4    => "Parsing Error",
        8    => "Notice",
        16   => "Core Error",
        32   => "Core Warning",
        64   => "Compile Error",
        128  => "Compile Warning",
        256  => "User Error",
        512  => "User Warning",
        1024 => "User Notice",
        2048 => "Strict",
        4096 => "Recoverable Error",
        8192 => "Deprecated"

    );

    $root = dirname($_SERVER['DOCUMENT_ROOT']);
    $filename = str_replace($root, "", $filename);

    $err = sprintf("<b>PHP-%s</b>: %s.", $errortype[$errno], $errmsg);

    if($app['config']['general']['developer_notices']) {
        echo "<p><strong>$err</strong>, $filename, $linenum</p>";
    }

    $app['log']->errorhandler($err, $filename, $linenum);

}


/**
 * Apparently, some servers don't have fnmatch. Define it here, for those who don't have it.
 *
 * @see http://www.php.net/manual/en/function.fnmatch.php#100207
 *
 */
if (!function_exists('fnmatch')) {
    define('FNM_PATHNAME', 1);
    define('FNM_NOESCAPE', 2);
    define('FNM_PERIOD', 4);
    define('FNM_CASEFOLD', 16);

    /**
     * Match filename against a pattern
     *
     * @param string $pattern
     * @param string $string
     * @param int $flags
     * @return bool
     */
    function fnmatch($pattern, $string, $flags = 0) {
        return pcre_fnmatch($pattern, $string, $flags);
    }
}

/**
 * Helper function for fnmatch() - Match filename against a pattern
 *
 * @param string $pattern
 * @param string $string
 * @param int $flags
 * @return bool
 */
function pcre_fnmatch($pattern, $string, $flags = 0) {
    $modifiers = null;
    $transforms = array(
        '\*'    => '.*',
        '\?'    => '.',
        '\[\!'    => '[^',
        '\['    => '[',
        '\]'    => ']',
        '\.'    => '\.',
        '\\'    => '\\\\'
    );

    // Forward slash in string must be in pattern:
    if ($flags & FNM_PATHNAME) {
        $transforms['\*'] = '[^/]*';
    }

    // Back slash should not be escaped:
    if ($flags & FNM_NOESCAPE) {
        unset($transforms['\\']);
    }

    // Perform case insensitive match:
    if ($flags & FNM_CASEFOLD) {
        $modifiers .= 'i';
    }

    // Period at start must be the same as pattern:
    if ($flags & FNM_PERIOD) {
        if (strpos($string, '.') === 0 && strpos($pattern, '.') !== 0) return false;
    }

    $pattern = '#^'
        . strtr(preg_quote($pattern, '#'), $transforms)
        . '$#'
        . $modifiers;

    return (boolean)preg_match($pattern, $string);
}
