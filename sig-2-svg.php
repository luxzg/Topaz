
--- COPY/PASTE EVERYTHING BELOW TO PHPFIDDLE.ORG ---

<?php

/**
## VERSION HISTORY ##
2019-01-13	- version by ScottG - https://stackoverflow.com/a/54173191/13312932
2020-05-08	- initial version, contained PHP-to-SVG (thanks to @ScottG from StackOverflow!), with couple important fixes, comments, and bonus HTML5 canvas
			(also has companion file with expanded StackOverflow post, explaining whole SigString format, etc.)
2020-05-14	- included fix for cropping canvas (thanks to @potomek from StackOverflow!), and brand new PostScript (PS) and PDF creation block
			(also includes companion "sig2svg.download.php" to force download PDF file)
**/

//				My changes marked with comment, eg. ->	//	fix by LuxZg 08.05.2020.

		//Requires $SigString and accepts optional filename.
		//$SigString has to be UNCOMPRESSED and UNENCRYPTED	//	by LuxZg 08.05.2020.
		//If filename is supplied the image will be written to that file
		//If no filename is supplied the SVG image will be returned as a string which can be echoed out directly

		function sigstring2svg($SigString, $filename = NULL)
		{
			$raw = hex2bin($SigString);	//Convert Hex
			$raw = str_ireplace(array("\r",'\r'),'', $raw);	//	fix by LuxZg 08.05.2020. - hex2bin generated code with \r\n both being used
				//	so after exploding it, the \r would be left, sometimes causing more bugs, but otherwise unseen unless encoded to eg. JSON

//			print the binary format
//			echo '<br><br>First we echo raw string, after hex2bin conversion:<br><br>';
//			echo '<pre>'.$raw.'</pre>';	//	this didn't show \r\n
//			echo '<pre>'.json_encode($raw).'</pre>';	//	this did show \r\n , and after fix now shows just \n, which is now OK for the next step

			$arr = explode(PHP_EOL, $raw);	//Split into array
			if ($arr[1] > 0) { //Check if signature is empty
					$coords = array_slice($arr, 2, $arr[0]);	//Separate off coordinate pairs	//	keep in mind SigString format is: coordinate amount - amount of lines - coordinate pairs - end-points of lines
																								//	also get to know your array_slice format: array name - start of slice - length of slice
					$lines = array_slice($arr, ($arr[0] + 2), $arr[1]);	//Separate off number of coordinates pairs per stroke
					$lines[] = $arr[0];	//	fix by LuxZg - 08.05.2020. - later code needs last coordinate added to array, so last slice/SVG segment isn't ommited by mistake
					$pslines = $lines;	//	addition by LuxZg - 14.05.2020. - just a copy that will be reused later for PostScript lines

//					bunch of echoes below, not needed, except to learn/understand, note that to see \r and/or \n you need to use json_encode, that's why I left both, to debug the error Scott's code had
//					echo '<br><br>Arr[] values:<br><br>';
//					echo '<pre>';
//					print_r(array_values($arr));
//					print_r(json_encode($arr));
//					echo '</pre>';
//					echo '<br><br>Coords[] values:<br><br>';
//					echo '<pre>';
//					print_r(array_values($coords));
//					print_r(json_encode($coords));
//					echo '</pre>';
//					echo '<pre>';
//					echo '<br><br>Lines[] values:<br><br>';
//					print_r(array_values($lines));
//					print_r(json_encode($lines));
//					echo '</pre><br><br>';

					if ($arr[1] == 1) {
							$lines[] = ($arr[0] + 2);	//If there is only 1 line the end has to be marked
						}
					$done = 0;
//					we always start at zero, it's first member of array, first coordinate we use

					foreach ($lines as $line => $linevalue) {
							if ($linevalue > $done) {
									$linelength = $linevalue-$done;	//	fix by LuxZg 08.05.2020. - we need to know where slice ends, so we use the "done" of previous line as our new start
																	//	and we know where we need to end, so length is simple math
//									$strokes[$line] = array_slice($coords, $done, $linevalue);	//Split coordinate pairs into separate strokes	//	end of line is wrong
																								//	it was including too many lines/coordinates in each iteration, again and again, so I fixed it, left this for comparison
									$strokes[$line] = array_slice($coords, $done, $linelength);	//Split coordinate pairs into separate strokes	//	fix by LuxZg 08.05.2020. - end of slice is now length of line, as should be
								}

//							just an echo to see what's actually happening and also why we needed to add that one last point in our lines[] array earlier
//							echo "<br>line = ".$line." , linevalue = ".$linevalue." , done = ".$done." , linelength = ".$linelength."<br>";

							$done = $linevalue;	//	we set new value to $done as next line will start from there
						}

//				I did not touch anything else in this PHP function, from this point below ! SVG drawing code is great!

					//Split X and Y to calculate the maximum and minimum coordinates on both axis
					$xmax = 0;
					$xmin = 999999;
					$ymax = 0;
					$ymin = 999999;
					foreach ($strokes as $stroke => $xycoords) {
							foreach ($xycoords as $xycoord) {
									$xyc = explode(' ', $xycoord);
									$xy[$stroke]['x'][] = $xyc[0];
									if ($xyc[0] > $xmax) $xmax = $xyc[0];
									if ($xyc[0] < $xmin) $xmin = $xyc[0];
									$xy[$stroke]['y'][] = $xyc[1];
									if ($xyc[1] > $ymax) $ymax = $xyc[1];
									if ($xyc[1] < $ymin) $ymin = $xyc[1];
							}
					}
					//Add in 10 pixel border to allow for stroke
					$xmax += 10;
					$xmin -= 10;
					$ymax += 10;
					$ymin -= 10;
					//Calculate the canvas size and offset out anything below the minimum value to trim whitespace from top and left
					$xmax -= $xmin;	//	we will use these xmin/xmax and ymin/ymax for SVG and later for PostScript as well
					$ymax -= $ymin;

					//Iterate through each stroke and each coordinate pair to make the points on the stroke to build each polyline as a string array
					foreach ($xy as $lines => $axis) {
							$polylines[$lines] = '<polyline class="sig" points="';
							foreach ($xy[$lines]['x'] as $point => $val) {
									$x = $xy[$lines]['x'][$point];
									$y = $xy[$lines]['y'][$point];
									$polylines[$lines] .= ($x - $xmin) . ',' . ($y - $ymin) . ' ';
							}
							$polylines[$lines] .= '"/>';
					}

					//Build SVG image string
					$image = '
						<svg id="sig" data-name="sig" xmlns="http://www.w3.org/2000/svg" width="' . $xmax . '" height="' . $ymax . '" viewBox="0 0 ' . $xmax . ' ' . $ymax . '">
							<defs>
								<style>
									.sig {
										fill: none;
										stroke: #000;
										stroke-linecap: round;
										stroke-linejoin: round;
										stroke-width: 4px;
									}
								</style>
							</defs>
							<title>Signature</title>
							<g>
								';	//	seems DOMPDF has issues sometimes with this SVG, so try without <defs> markup and place <style> to SVG root
								foreach ($polylines as $polyline) {
										$image .= $polyline;
								}
								$image .= '
							</g>
						</svg>';

					//If file name is supplied write to file
					if ($filename) {
							try {
									$file = fopen($filename, 'w');
									fwrite($file, $image);
									fclose($file);
									return $filename;
							} catch (Exception $e) {
									return false;
							}
					} else {
							//If file name is not supplied return the SVG image as a string
							return $image;
					}

//					LuxZg - 14.05.2020. - new block of code for PS/PDF creation
//					BONUS! We will create PostScript signature AND a PDF from it!
//						First we create PostScript "header" which describes the page and prepares it for signature
//						leave it unindented so it is unindented in .ps file later
//						note that "%%" are PostScript comments, and are ignored by PDF distillers
$ps = "%!PS
%% set page size according to xmax/ymax and rotate page
<< /PageSize [$xmax $ymax] /Orientation 2 >> setpagedevice
%% mirror image by inverting coordinates and translating it correctly
clippath pathbbox newpath
pop 0 translate pop pop
-1 1 scale
%% translate (move) signature canvas to correct position according to xmin/ymin
-$xmin -$ymin translate
%% start drawing path with signature coordinates/lines
newpath
";

//						just echo check to know your min/max coordinates, this matches what PostScript will contain
//						echo "<br><br>Maximum SVG/PS X = " . $xmax . " , and maximum SVG/PS Y = " . $ymax . "<br><br>";
//						echo "<br><br>Minimum SVG/PS X = " . $xmin . " , and minimum SVG/PS Y = " . $ymin . "<br><br>";

					//	iteration counter, so we know where lines start/stop
					$i = 0;
					//	for all coordinates in the coords array
					//	place our lineto/moveto points with x/y coordinates, same as we do in JavaScript/HTML5 canvas
					foreach ($coords as $dot) {
							//	we split each coordinate to X & Y, can be shortened, this is for readability
							$tocoords = explode(" ", $dot);
							$coordx = $tocoords[0];
							$coordy = $tocoords[1];
//							echo 'Iteration number -> i='.$i.'\n';

							//	if we encounter coord that is mentioned in pslines[] / lines[] array
							//	it means line END, so we make a MOVE instead of drawing line, using moveto that coordinate's x/y
							if(in_array($i, $pslines))
								{
									$ps = $ps . $coordx . " " . $coordy . " " . "moveto\n";
								}
							//	otherwise, we DRAW the line between points on the page, using lineto that coordinate x/y
							else
								{
									$ps = $ps . $coordx . " " . $coordy . " " . "lineto\n";
								}
							//	increase our iteration count for one
							$i = $i+1;
						}

//					and now we add the footer/end of PostScript file, with line width, color, and actual stroke commands
//					again try to keep it unindented
$ps = $ps . "%% set line width and color
2 setlinewidth
0 setgray
%% write the line and show the page
stroke
showpage";

//					echo '<br><br>Putting coordinates to PS file, this is how PS file looks like:<br><br>';
//					echo '<pre>'.$ps.'</pre>';
					// open file for writing; if filename was given in function call, add it as suffix, otherwise it's just "postscript.ps"
					$fp = fopen('/var/www/website/storage/postscript'.$filename.'.ps', 'w');
					//	write the contents of $ps string to $fp file, then close the file
					fwrite($fp, $ps);
					fclose($fp);

					//	call the ps2pdf on the system
					//		this is optional - you can use PostScript as-is in many other ways, but if you want/need PDF then...
					//		ps2pdf needs to be installed
					//		this is part of ghostscript package, available for all major platforms
					//		to install on Ubuntu: apt-get install ghostscript
					//		if success, it is silent, if error, echo will print it out on page
					//		errors are a mess, as ghostscript/postscript are all 3+ decades old
					//		most often if it fails your read/write permissions are wrong, but error won't tell you any hint about the real issue
					//		so make sure that user that is used to run web server and PHP has read/write permissions for your temporary storage folder
					//		command format: ps2pdf <options> input.ps output.ps
					//		if output.pdf file name is ommitted, then input file is used for output, with .pdf extension
					echo exec("ps2pdf -dPDFSETTINGS=/screen /var/www/website/storage/postscript".$filename.".ps /var/www/website/storage/postscript".$filename.".pdf");

					//	provide some way to download file, or display it to user in browser ; if you elected to skip PDF creation, then change it to download .PS file instead
					echo "<a href='sig2svg.download.php?filename=postscript".$filename."'>PDF postscript".$filename.".pdf to download</a>";
					//	This ends server-side PostScript and PDF creation

				} else {
						return "Signature is empty";
				}
		}

//				OK to complete the example I actually use the function

		//	this is my simple example "AP" signature, all decompressed and ready to be put through function
		$sigdec = '3139370D0A340D0A343531203438310D0A343530203438300D0A343530203437390D0A343530203437370D0A343530203437340D0A343531203437300D0A343531203436340D0A343532203435380D0A343534203435300D0A343536203434320D0A343539203433330D0A343633203432330D0A343637203431330D0A343731203430330D0A343735203339320D0A343738203338310D0A343830203336390D0A343832203335370D0A343834203334340D0A343835203333310D0A343835203331380D0A343836203330340D0A343836203239310D0A343836203237370D0A343837203236320D0A343837203234380D0A343837203233330D0A343837203231380D0A343837203230340D0A343839203139300D0A343931203137360D0A343934203136330D0A343937203135300D0A353032203133380D0A353036203132370D0A353130203131370D0A353134203130380D0A353137203130320D0A3531382039390D0A3531392039380D0A3531392039380D0A3532312039360D0A3532312039350D0A3532322039340D0A3532332039330D0A3532342039310D0A3532352039310D0A3532362039310D0A3532372039320D0A3532372039340D0A3532382039370D0A353239203130300D0A353330203130350D0A353332203131320D0A353334203131390D0A353337203132370D0A353430203133350D0A353434203134330D0A353438203135330D0A353534203136330D0A353630203137340D0A353637203138380D0A353735203230310D0A353832203231340D0A353838203232380D0A353934203234320D0A353939203235360D0A363034203237300D0A363039203238340D0A363133203239390D0A363136203331330D0A363139203332360D0A363231203334300D0A363233203335340D0A363234203336380D0A363235203338310D0A363236203339340D0A363237203430360D0A363238203431370D0A363239203432380D0A363330203433380D0A363331203434380D0A363331203435360D0A363331203436330D0A363331203436390D0A363330203437330D0A363330203437370D0A363330203437380D0A363239203437390D0A363238203437390D0A363238203437390D0A363238203437390D0A363238203437390D0A363032203234370D0A363031203234370D0A353939203234390D0A353937203235310D0A353934203235340D0A353930203235360D0A353836203235380D0A353830203236300D0A353733203236300D0A353635203236310D0A353535203236300D0A353434203236300D0A353333203235380D0A353232203235370D0A353131203235350D0A353032203235330D0A343934203235310D0A343837203234390D0A343832203234370D0A343738203234350D0A343735203234330D0A343733203234310D0A343733203234300D0A343733203233390D0A343733203233390D0A343734203233390D0A343734203233390D0A373736203431370D0A373736203431370D0A373735203431380D0A373735203431380D0A373734203431380D0A373733203431360D0A373732203431340D0A373730203431300D0A373639203430360D0A373637203430320D0A373635203339360D0A373632203339300D0A373630203338320D0A373537203337340D0A373533203336350D0A373439203335350D0A373434203334350D0A373430203333350D0A373335203332340D0A373331203331330D0A373237203330320D0A373232203239310D0A373139203238300D0A373135203236390D0A373132203235370D0A373038203234350D0A373035203233340D0A373032203232320D0A363939203231300D0A363937203139390D0A363935203138380D0A363934203137370D0A363934203136370D0A363934203135360D0A363935203134370D0A363936203133380D0A363938203133300D0A373032203132320D0A373036203131350D0A373130203130390D0A373136203130340D0A3732342039390D0A3733332039360D0A3734342039330D0A3735372039320D0A3736392039330D0A3738322039350D0A3739362039370D0A383038203130310D0A383232203130350D0A383334203131300D0A383435203131350D0A383534203132310D0A383632203132380D0A383638203133360D0A383733203134340D0A383736203135320D0A383738203136300D0A383739203136380D0A383739203137370D0A383738203138350D0A383736203139330D0A383733203230310D0A383639203230380D0A383634203231340D0A383539203232300D0A383531203232350D0A383432203232380D0A383330203233300D0A383137203233310D0A383032203233310D0A373837203232390D0A373733203232370D0A373633203232350D0A373538203232330D0A373536203232330D0A373536203232330D0A300D0A34310D0A39330D0A3132300D0A';

//		you can use these echos to see sample SigString
//		echo 'Printing decompressed SigString ; SetSigCompressionMode(0) :<br><br>';
//		echo $sigdec;

		//	Bonus:
		//		if you will need to keep SigString size down, rather use PHP's gzdeflate or anything similar
		//		just don't forget if you later pull the compressed sig data from eg. database, to decompress it before sending it to SVG function
		//	$sigdecgz = gzdeflate($sigdec,9);

//		you can use these echos to see sample GZ-deflated SigString
//		echo 'Printing SigString after GZ-deflate; gzdeflate($sigdec,9) :<br><br>';
//		echo $sigdecgz;

//		print the output of sigstring2svg(), so my sample "AP" SigString, shown in SVG format, using slightly modified Scott's code
//		this will show it as SVG on page, save a SVG to disk, and also create PS file to disk, and optionally convert PS to PDF if ps2pdf is installed
		echo '<br><br>Printing output of sigstring2svg() function, SigString as SVG (of decompressed & unencrypted signature !):<br><br>';
		echo sigstring2svg($sigdec,'sample');
		echo '<br><br>';

?>

OK, done with PHP ... but there is one more bonus for you folks!<br>
I've re-used same PHP code once again to actually draw the signature in HTML5 canvas,<br>
similar as the original SigWeb component does, but without using Topaz APIs/components.<br>
I've re-used the PHP code to do the initial hex2bin and slicing<br>
Tested, works all nice even on combination of Ubuntu / Apache / PHP web server,
with Android phone as client, so no Windows involved.<br>
Use this if you don't need actual files (SVG/PS/PDF) and all you need is to display it in browser<br><br>

<script type="text/javascript">

	//	LuxZg - 14.05.2020.
	//	used the nice script to crop canvas, used as provided, obtained from: https://stackoverflow.com/questions/11796554/automatically-crop-html5-canvas-to-contents/22267731#22267731
	function cropImageFromCanvas(ctx) {
	  var canvas = ctx.canvas,
		w = canvas.width, h = canvas.height,
		pix = {x:[], y:[]},
		imageData = ctx.getImageData(0,0,canvas.width,canvas.height),
		x, y, index;

	  for (y = 0; y < h; y++) {
		for (x = 0; x < w; x++) {
		  index = (y * w + x) * 4;
		  if (imageData.data[index+3] > 0) {
			pix.x.push(x);
			pix.y.push(y);
		  }
		}
	  }
	  pix.x.sort(function(a,b){return a-b});
	  pix.y.sort(function(a,b){return a-b});
	  var n = pix.x.length-1;

	  w = 1 + pix.x[n] - pix.x[0];
	  h = 1 + pix.y[n] - pix.y[0];
	  var cut = ctx.getImageData(pix.x[0], pix.y[0], w, h);

	  canvas.width = w;
	  canvas.height = h;
	  ctx.putImageData(cut, 0, 0);
	}

	//	LuxZg - 08.05.2020.
	function cCanvas()
		{

			<?php

				//	sample signature, this SigString is hardcoded for example, you'd probably pull it from a database on server, hence PHP still makes sense for this step
				//	for this sample I just reuse the string from previous PHP section (SVG/PS/PDF creation)
				$sg = $sigdec;

				//	short version of PHP code used before

				//	convert hex to bin
				$raw = hex2bin($sg);
				//	cleanup double new-line after hex2bin conversion (\r\n)
				$raw = str_ireplace(array("\r",'\r'),'', $raw);
				//	exploding cleaned string to array
				$arr = explode(PHP_EOL, $raw);
				//	slicing coords to it's array
				$coords = array_slice($arr, 2, $arr[0]);
					//	doing json encode to convert PHP array to JS array
					$js_array_a = json_encode($coords);
					//	echoing it as JS
					echo "var coords = ". $js_array_a . ";\n";
				//	slicing line endings to it's array
				$lines = array_slice($arr, ($arr[0] + 2), $arr[1]); //Separate off number of coordinates pairs per stroke
					//	and convert and echo to JSON as JS array for this as well
					$js_array_b = json_encode($lines);
					echo "var lines = ". $js_array_b . ";\n";

				//	server side done

			?>

			//	now short client side JavaScript code

			//	define canvas that has HTML id = "c"; use any id just don't forget to change where needed
			var canvas = document.getElementById("c");
			//	resize canvas temporarily, make it as big as you need for your hardware (signature pad)
			//	this does NOT affect web page, as we will crop it before final output
				canvas.width = 2000;
				canvas.height = 2000;
			//	and canvas' context
			var context = canvas.getContext("2d");
			//	get the coords array length
			var arrayLength = coords.length;
			//	initialize variables used in for loop
			var coordx = '';
			var coordy = '';
			var tocoords = [];

			//	putting coordinates/lines on canvas

			//	for all coordinates in the coords array
			for (var i = 0; i < arrayLength; i++) {
					//	we split each coordinate to X & Y, can be shortened, this is for readability
					tocoords = coords[i].split(" ");
					coordx = tocoords[0];
					coordy = tocoords[1];

					//	if we encounter coord that is mentioned in lines[] array
					//	it means line END, so we make a MOVE instead of drawing line, using moveTo() that coordinate
					if (lines.includes(String(i)))
						{
							context.moveTo(coordx, coordy);
						}
					//	otherwise, we DRAW the line between points on canvas, using lineTo() that coordinate
					else
						{
							context.lineTo(coordx, coordy);
						}
				}

			//	at the end we have to define the color of the signature in the canvas
			context.strokeStyle = "#000000";	//	black
			//	and the thickness of the line, as you feel appropriate
			context.lineWidth = "3";
			//	and tell browser to make our lines into stroke, which is effectively command that shows signature in the canvas
			context.stroke();
			//	as final touch, crop the canvas to the size of signature (remove white space), using the function we borrowed from linked stackoverflow answer
			cropImageFromCanvas(context);
			//	this ends client-side code, and we need just some basic HTML markup to execute it
		}

</script>

<br>
<div style="color:red; font-size:25px;" onclick="javascript:cCanvas()">Click to show signature</div>
<br>
Canvas is below, press button to show signature:
<br>
<canvas name="c" id="c" />
<br>
