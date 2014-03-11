#!/usr/bin/php5
<?php
$BASEDIR = dirname(__FILE__) . '/';
$srcdir = "$BASEDIR/src/";
$outdir = "$BASEDIR/build_output/";
$outfile = "index.php";
//$testdir = "$BASEDIR/test/";

@include $BASEDIR . "build.cfg";

# Get the sources...
#
$files = array();
$d = dir($srcdir);
while ( FALSE !== ($entry = $d->read()) )
{
	if ($entry[0] == '.')
		continue;
	$files[] = $entry;
}
$d->close();

sort($files);

# Assemble the executable file...
#
$outfullname = "$outdir/$outfile";
if (is_file($outfullname)) unlink($outfullname);

$result = '';
foreach ($files as $file)
{
	$module = file("$srcdir/$file");

/*
	// Strip any <php ... php> wrappers...
	$firstline = trim($module[0]); $lastline = trim(end($module));
	if ( ($firstline == "<\x3f" or $firstline = "<\x3fphp")
		and $lastline == "\x3f>")
	{
		array_shift($module);
		array_pop($module);
	}
*/
	$result .= implode('', $module);
}

/*
// Wrap the assembled code into new <php ... php>
$result = "<\x3fphp\n$result\n\x3f>";
*/

file_put_contents($outfullname, $result);


//system("cp '$outfullname' '$testdir'");

?>
