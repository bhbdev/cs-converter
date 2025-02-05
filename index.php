<?php
/**
 * 
 * @author Beau Bishop
 * @version $Id$
 **/
require_once("aco.class.php");

  if (isset($_REQUEST['sp'])) {
	  $cs_id = filter_var($_REQUEST['sp'], FILTER_SANITIZE_NUMBER_INT);
	  $content = `curl http://www.colorschemer.com/schemes/viewscheme.php\?id=$cs_id`;
	  $lines = explode("\n", $content);
	  $data = array();
	  foreach ($lines as $line) {
		if (preg_match("/smallbar/", $line)) {
			preg_match_all("/<h1>(.*)<\/h1>/",$line,$title);
			$data['title'] = $title[1][0];
		}
		if (preg_match("/class=\"scheme\"/", $line)) {
			preg_match_all("/background-color:(.*?);/",$line,$hexcolors);
			$data['colors'] = array();
			foreach ($hexcolors[1] as $x=>$color) {
				$data['colors']["color".($x+1)] = $color;
			}
		}
	  }	
	  print "<h1>{$data['title']}</h1>";
	  print "<ul>";
	  $hexcolors = array();
	  foreach ($data['colors'] as $x=>$color) {
		print "<li><div class='swatch' style='background:{$color}'>&nbsp;</div> {$color}</li>";
		$hexcolors[] = str_replace('#','',$color);
	  } 
	  print "</ul>";
	  print "<br /><a href='?scheme={$data['title']}&makeaco&hexcodes=".join("|",$hexcolors)."'>Download Photoshop >= CS6 Swatch (.aco)</a>";
	  print "<br /><a href='?scheme={$data['title']}&makease&hexcodes=".join("|",$hexcolors)."'>Download Photoshop <= CS5 Swatch (.ase)</a>";
	  exit;
  }
  
  if (isset($_REQUEST['makease'])) {
	  $scheme = filter_var($_REQUEST['scheme'], FILTER_SANITIZE_STRING);
	  $hexstr = filter_var($_REQUEST['hexcodes'], FILTER_SANITIZE_STRING);
      $hexs = explode("|",$hexstr);
	  $names = array();
	  foreach ($hexs as $hex) $names[] = "color{$hex}";
      mkASE($hexs,$names,"{$scheme}.ase",false);
	  exit;
  }

  if (isset($_REQUEST['makeaco'])) {
	
	$scheme = filter_var($_REQUEST['scheme'], FILTER_SANITIZE_STRING);
	$hexstr = filter_var($_REQUEST['hexcodes'], FILTER_SANITIZE_STRING);
    $hexcodes = explode("|",$hexstr);

	$aco = new acofile($scheme . ".aco");
	
	foreach ($hexcodes as $hex) {
		$color = hex2rgb($hex);
		$aco->add(array("#".($hex) => array($color[0], $color[1], $color[2])));
	}
	$aco->outputAcofile();
	exit;
  }

	

	function hex2rgb($color){
	    $color = str_replace('#', '', $color);
	    if (strlen($color) != 6){ return array(0,0,0); }
	    $rgb = array();
	    for ($x=0;$x<3;$x++){
	        $rgb[$x] = hexdec(substr($color,(2*$x),2));
	    }
	    return $rgb;
	}
  
    /**
    * @desc Make an Adobe Swatch Exchange file
    * @author Chris Williams - For COLOURlovers.com
    * @param array $hexs Hexs must be 6 chars long!
    * @param array $names
    * @param string $fileName
    * @param bool $saveOnServer
    * @license Free, use it as you wish!
    * @return void
    */
    function mkASE($hexs,$names,$fileName,$saveOnServer) {
        define(NUL,chr(0)); # NULL Byte
        define(SOH,chr(1)); # START OF HEADER Byte
        $numHexs = count($hexs);

        $ase = "ASEF" . NUL . SOH . NUL . NUL; # ASE Header
        for ($i=24;$i>=0;$i-=8) {
            $ase .= chr(($numHexs >> $i) & 0xFF); # $numHexs Being the number of swatches, of course
        }
        $ase .= NUL;

        for ($i=0;$i<$numHexs;$i++) {
            $ase .= SOH . NUL . NUL . NUL; # Swatch header
            $ase .= chr((((strlen($names[$i]) + 1) * 2) + 20)) . NUL; # (((num chars in str + 1) * 2) + 20) ... this is more than likely the length of the whole swatch "package"
            $ase .= chr(strlen($names[$i]) + 1) . NUL; # num chars in str + 1

            # Add name of the swatch:
            for ($j=0;$j<strlen($names[$i]);$j++) {
                $ase .= $names[$i]{$j} . NUL;
            }

            # Big endian, single-precision floating point numbers:
            # The precision isn't exact, but the values will round out.
            list($rDec,$gDec,$bDec) = sscanf($hexs[$i],"%2x%2x%2x");
            $r = pack("f",($rDec / 255));
            $g = pack("f",($gDec / 255));
            $b = pack("f",($bDec / 255));

            # We're using RGB here :-)
            $ase .= NUL . "RGB "; # Keep trailing space!
            $ase .= $r{3} . $r{2} . $r{1} . NUL;
            $ase .= $g{3} . $g{2} . $g{1} . NUL;
            $ase .= $b{3} . $b{2} . $b{1} . NUL;
            if (($i + 1) != $numHexs) {
                # Swatch seperator:
                $ase .= NUL . NUL . NUL;
            }
        }
        # Terminate file
        $ase .= NUL . NUL;

        # That's it!

        if ($saveOnServer) {
            $fASE = fopen($fileName,"wb");
            fwrite($fASE,$ase);
            fclose($fASE);
        } else {
            header("Content-Type: force-download");
            header("Content-Disposition: attachment; filename=\"$fileName\"");
            echo $ase;
        }
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>colorschemer converter</title>
	<script type="text/javascript" src="jquery.js"></script>
	<style type="text/css">
	 	body { font-family: Helvetica; font-size: 14px;}
		h1 { text-align:center; }
	 	fieldset { width: 300px;padding: 1em; margin: 0 auto; }
		#scheme { position: relative; width: 500px; margin: 2em auto; border:1px solid #000; padding: 1em; display:none;}
		#scheme h1 { margin: 0 0 1em; background: #ddd;padding: 0.25em 0;}
		ul { list-style: none; }
		ul li { margin-bottom: 1em; width: 150px; height:70px; float: left; text-align:center; }
	 	.swatch { display:inline-block; vertical-align:middle; width: 50px;height:50px; border:1px solid #000; margin: 0 10px; }
	    br { clear:both;}
	</style>
</head>
<body>
<h1>ColorSchemer Converter</h1>
<fieldset>
	<legend>Enter the ID of the ColorSchemer scheme</legend>
	<form id="scheme-form" method="post">
		<input type="text" name="scheme-id" value="" />
		<input type="submit" name="submit-scheme" value="CONVERT" />
	</form>
</fieldset>
<div id="scheme"></div>
<script type="text/javascript">
$(document).ready(function(){
	$('#scheme-form').submit(function(e){
		$('#scheme').hide().load('?sp='+$('input[name=scheme-id]').val()).show();
		return false;
	})
});
</script>
</body>
</html>