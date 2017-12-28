<?php

/*
 * phpMyEdit - instant MySQL table editor and code generator
 *
 * phpMyEditSetup.php - interactive table configuration utility (setup)
 * ____________________________________________________________
 *
 * Copyright (c) 1999-2002 John McCreesh <jpmcc@users.sourceforge.net>
 * Copyright (c) 2001-2002 Jim Kraai <jkraai@users.sourceforge.net>
 * Versions 5.0 and higher developed by Ondrej Jombik <nepto@php.net>
 * Copyright (c) 2002-2006 Platon Group, http://platon.sk/
 * All rights reserved.
 *
 * See README file for more information about this software.
 * See COPYING file for license information.
 *
 * Download the latest version from
 * http://platon.sk/projects/phpMyEdit/
 */

/* $Platon: phpMyEdit/phpMyEditSetup.php,v 1.50 2007-09-16 12:57:07 nepto Exp $ */

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
            "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=us-ascii">
	<title>phpMyEdit Setup</title>
	<style type="text/css">
	<!--
		body  { font-family: "Verdana", "Arial", "Sans-Serif"; text-align: left }
		h1    { color: #004d9c; font-size: 13pt; font-weight: bold }
		h2    { color: #004d9c; font-size: 11pt; font-weight: bold }
		h3    { color: #004d9c; font-size: 11pt; }
		p     { color: #004d9c; font-size: 9pt; }
		table { border: 1px solid #004d9c; border-collapse: collapse; border-spacing: 0px; }
		td    { border: 1px solid; padding: 3px; color: #004d9c; font-size: 9pt; }
		hr
		{
		height: 1px;
		background-color: #000000;
		color: #000000;
		border: solid #000000 0;
		padding: 0;
		margin: 0;
		border-top-width: 1px;
		}
	-->
	</style>
</head>
<body bgcolor="white">

<?php

if (! defined('PHP_EOL')) {
	define('PHP_EOL', strtoupper(substr(PHP_OS, 0, 3) == 'WIN') ? "\r\n"
			: strtoupper(substr(PHP_OS, 0, 3) == 'MAC') ? "\r" : "\n");
}

$hn = @$_POST['hn'];
$un = @$_POST['un'];
$pw = @$_POST['pw'];
if (isset($_POST['db'])) $db = @$_POST['db'];
if (isset($_POST['tb'])) $tb = @$_POST['tb'];
$id = @$_POST['id'];
$submit        = @$_POST['submit'];
$options       = @$_POST['options'];
$baseFilename  = @$_POST['baseFilename'];
$pageTitle     = @$_POST['pageTitle'];
$pageHeader    = @$_POST['pageHeader'];
$HTMLissues    = @$_POST['HTMLissues'];
$CSSstylesheet = @$_POST['CSSstylesheet'];

$phpExtension = '.php';
if (isset($baseFilename) && $baseFilename != '') {
	$phpFile = $baseFilename.$phpExtension;
	//$contentFile = $baseFilename.'Content.inc';
	$contentFile = $baseFilename.'.php';
} elseif (isset($tb)) {
	$phpFile = $tb.$phpExtension;
	//$contentFile = $tb.'Content.inc';
	$contentFile = $tb.'.php';
} else {
	$phpFile = 'index'.$phpExtension;
	//$contentFile = 'Content.inc';
	$contentFile = 'phpMyEdit-content.php';
}

$buffer = '';

function echo_html($myString)
{
 // in PHP 5.4 the default encoding used by htmlspecialchars() was changed.
  echo htmlspecialchars($myString, ENT_COMPAT, 'ISO-8859-1',true);
}

function echo_buffer($x)
{
	global $buffer;
	$buffer .= $x.PHP_EOL;
}

#:#####################################:#
#:#  Function:   check_constraints    #:#
#:#  Parameters: tb=table name        #:#
#:#              fd=field name        #:#
#:#  return:     lookup default for   #:#
#:#              said constraint      #:#
#:#              or null if no        #:#
#:#              constraint is found. #:#
#:#  Contributed by Wade Ryan,        #:#
#:#                 20060906          #:#
#:#####################################:#
function check_constraints($dbl, $tb,$fd)
{
  $query    = "show create table $tb";
  $result   = $dbl->query($query);
  $ct = $result->fetch();
  $tableDef = preg_split('/\n/',$ct[1]);
  //print_r($tableDef);

  $constraint_arg="";
  while (list($key,$val) = each($tableDef)) {
    $words=preg_split("/[\s'`()]+/", $val);
    if ($words[1] == "CONSTRAINT" && $words[6]=="REFERENCES") {
      if ($words[5]==$fd) {
        $constraint_arg="  'values' => array(\n".
                        "    'table'  => '$words[7]',\n".
                        "    'column' => '$words[8]'\n".
                        "  ),\n";
      }

    }
  }
  //print_r("constraint_arg $constraint_arg");
  return $constraint_arg;
}

function get_versions()
{
	$ret_ar  = array();
	$dirname = dirname(__FILE__);
	foreach (array(
				'current' => __FILE__,
				'setup'   => "$dirname/phpMyEditSetup.php",
				'core'    => "$dirname/phpMyEdit.class.php",
				'version' => "$dirname/doc/VERSION")
			as $type => $file) {
		if (@file_exists($file) && @is_readable($file)) {
			if (($f = fopen($file, 'r')) == false) {
				continue;
			}
			$str = trim(fread($f, 4096));
			if (strpos($str, ' ') === false && strlen($str) < 10) {
				$ret_ar[$type] = $str;
			} else if (preg_match('|\$'.'Platon:\s+\S+,v\s+(\d+.\d+)\s+|', $str, $matches)) {
				$ret_ar[$type] = $matches[1];
			}
			fclose($f);
		}
	}
	return $ret_ar;
}
/**
 * From https://www.sitepoint.com/community/u/Czaries helpful comment on how to do this
	* 	 *	Parse PDO-produced column type
	* 	 	 *	[internal function]
	* 	 	 	 */
function parseColumnType($colType)
{
	$colInfo = array();
	$colParts = explode(" ", $colType);
	if($fparen = strpos($colParts[0], "("))
	{
		$colInfo['type'] = substr($colParts[0], 0, $fparen);
		$colInfo['pdoType'] = '';
		$colInfo['length']  = str_replace(")", "", substr($colParts[0], $fparen+1));
		$colInfo['attributes'] = isset($colParts[1]) ? $colParts[1] : NULL;
	}
	else
	{
		$colInfo['type'] = $colParts[0];
	}

	// PDO Bind types
	$pdoBindTypes = array(
		'char' => PDO::PARAM_STR,
		'int' => PDO::PARAM_INT,
		'bool' => PDO::PARAM_BOOL,
		'date' => PDO::PARAM_STR,
		'time' => PDO::PARAM_INT,
		'text' => PDO::PARAM_STR,
		'blob' => PDO::PARAM_LOB,
		'binary' => PDO::PARAM_LOB
	);

	$pdoType = '';
	foreach($pdoBindTypes as $pKey => $pType)
	{
		if(strpos(' '.strtolower($colInfo['type']).' ', $pKey)) {
			$colInfo['pdoType'] = $pType;
			break;
		} else {
			$colInfo['pdoType'] = PDO::PARAM_STR;
		}
	}

	return $colInfo;
}
								//
$self = basename($_SERVER['PHP_SELF']);
function sql_connect($hn,$db,$un,$pw)
{
		try {
			$dsn = "mysql:host=" . $hn;
			if (isset($db)&& strlen($db)>1)
				$dsn .= ";dbname=" . $db;
			$dbh = @ini_get('allow_persistent')
				? new PDO($dsn, $un, $pw, array( PDO::ATTR_PERSISTENT => true )) // @mysql_pconnect($this->hn, $this->un, $this->pw)
				: new PDO($dsn, $un, $pw); // @mysql_connect($this->hn, $this->un, $this->pw);

			// select error-mode: [ERRMODE_SILENT | ERRMODE_WARNING | ERRMODE_EXCEPTION]
			$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			// select fetch-mode: [FETCH_ASSOC | FETCH_CLASS | FETCH_OBJ]
			// $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		} // try/catch
		catch(PDOException $e) {
			die("sql_connect PDO($dsn,$un,$pw) ". $e->getMessage());
			$dbh = null;
		} // try/catch
		return $dbh;
}
if (isset($hn) && isset($un) && isset($pw))
	$dbl  = sql_connect($hn,$db, $un, $pw);
if (strlen($db)<1)
		unset($db);
if ((!isset($dbl)) or empty($submit)) {
	echo '<h1>Please log in to your MySQL database</h1>';
	if (!empty($submit)) {
		echo '<h2>Sorry - login failed - please try again</h2>'.PHP_EOL;
	}
	if (! isset($hn)) {
		$hn = 'localhost';
	}
	if (! isset($un)) 
		$un = 'username';
	if (! isset($pw))
		$pw = 'password';
	echo '
		<form action="'.htmlspecialchars($self).'" method="POST">
		<table border="1" cellpadding="1" cellspacing="0" summary="Login form">
		<tr>
		<td>Hostname:</td>
		<td><input type="text" name="hn" value="'.htmlspecialchars($hn).'"></td>
		</tr><tr>
		<td>Username:</td>
		<td><input type="text" name="un" value="'.htmlspecialchars($un).'"></td>
		</tr><tr>
		<td>Password:</td>
		<td><input type="text" name="pw" value="'.htmlspecialchars($pw).'"></td>
		</tr><tr>
		<td>Database:</td>
        <td><input type="text" name="db" value="'.htmlspecialchars($db).'"></td>
		</tr><tr>
		<td>Table:</td>
		<td><input type="text" name="tb" value="'.htmlspecialchars($tb).'"></td>
		</tr>
		</table><br>
		<input type="submit" name="submit" value="Submit">
		</form>'.PHP_EOL;
} else if (! isset($db)) {

	echo '<h1>Please select a database</h1>
		<form action="'.htmlspecialchars($self).'" method="POST">
		<input type="hidden" name="hn" value="'.htmlspecialchars($hn).'">
		<input type="hidden" name="un" value="'.htmlspecialchars($un).'">
		<input type="hidden" name="pw" value="'.htmlspecialchars($pw).'">
		<table border="1" cellpadding="1" cellspacing="1" summary="Database selection">'.PHP_EOL;
	$dbs = $dbl->query( 'SHOW DATABASES' );

	while( ( $db = $dbs->fetchColumn( 0 ) ) !== false ){
		$checked = ! strcasecmp($un, $db) ? ' checked' : '';
		$db = htmlspecialchars($db);
		echo '<tr><td><input'.$checked.' type="radio" name="db" value="'.$db.'"></td><td>'.$db.'</td></tr>'.PHP_EOL;
	}
	echo '</table><br>
		<input type="submit" name="submit" value="Submit">
		<input type="submit" name="cancel" value="Cancel">
		</form>'.PHP_EOL;
} else if (!isset($tb)) {
	echo '<h1>Please select a table from database: '.htmlspecialchars($db).'</h1>
		<form action="'.htmlspecialchars($self).'" method="POST">
		<input type="hidden" name="hn" value="'.htmlspecialchars($hn).'">
		<input type="hidden" name="un" value="'.htmlspecialchars($un).'">
		<input type="hidden" name="pw" value="'.htmlspecialchars($pw).'">
		<input type="hidden" name="db" value="'.htmlspecialchars($db).'">
		<table border="1" cellpadding="1" cellspacing="1" summary="Table selection">'.PHP_EOL;
	$tbs = $dbl->query('SHOW TABLES');
	$checked = ' checked';
	while( ($tb = $tbs->fetchColumn(0))!==false)	{
		$tb = htmlspecialchars($tb);
		echo '<tr><td><input'.$checked.' type="radio" name="tb" value="'.$tb.'"></td><td>'.$tb.'</td></tr>'.PHP_EOL;
		$checked = '';
	}
	echo '</table><br>
		<input type="submit" name="submit" value="Submit">
		<input type="submit" name="cancel" value="Cancel">
		</form>'.PHP_EOL;
} else if (!isset($id)) {
	echo '  <h1>Please select an identifier from table: '.htmlspecialchars($tb).'</h1>
		<p>
		This field will be used in change, view, copy and delete operations.<br>
		The field should be numeric and must uniquely identify a record.
		</p>
		<p>
		Please note, that there were problems reported by phpMyEdit users
		regarding using MySQL reserved word as unique key name (the example for
				this is "key" name). Thus we recommend you to use another name
		of unique key. Usage of "id" or "ID" should be safe and good idea.
		</p>
		<form action="'.htmlspecialchars($self).'" method="POST">
		<input type="hidden" name="hn" value="'.htmlspecialchars($hn).'">
		<input type="hidden" name="un" value="'.htmlspecialchars($un).'">
		<input type="hidden" name="pw" value="'.htmlspecialchars($pw).'">
		<input type="hidden" name="db" value="'.htmlspecialchars($db).'">
		<input type="hidden" name="tb" value="'.htmlspecialchars($tb).'">
		<table border="1" cellpadding="1" cellspacing="1" summary="Key selection">'.PHP_EOL;
//		<tr><td><input type="radio" name="id" value="">
	//		<td><i>None</i></td><td><i>No id field required</i></td></tr>
	$tb_desc = $dbl->query("DESCRIBE $tb");
	while( ($ff = $tb_desc->fetch())!==false)	{
		$checked = $ff["Key"]=="PRI" ? ' checked' : '';
		echo '<tr><td><input',$checked,' type="radio" name="id" value="',htmlspecialchars($ff[0]),'"></td>';
		echo '<td>',htmlspecialchars($ff[0]),'</td>';
		echo '<td>',htmlspecialchars($ff["Type"]),'</td>';
	}
	echo '</table><br>
		<input type="submit" name="submit" value="Submit">
		<input type="submit" name="cancel" value="Cancel">
		</form>'.PHP_EOL;

} else if (!isset($options)) {
	echo '<h1>Please select additional options</h1>
		<form action="'.htmlspecialchars($self).'" method="POST">
		<input type="hidden" name="hn" value="'.htmlspecialchars($hn).'">
		<input type="hidden" name="un" value="'.htmlspecialchars($un).'">
		<input type="hidden" name="pw" value="'.htmlspecialchars($pw).'">
		<input type="hidden" name="db" value="'.htmlspecialchars($db).'">
		<input type="hidden" name="tb" value="'.htmlspecialchars($tb).'">
		<input type="hidden" name="id" value="'.htmlspecialchars($id).'">
		<table border="1" cellpadding="1" cellspacing="1" summary="Additional options">
		<tr><td>Base filename</td><td><input type="text" name=baseFilename value ="'.htmlspecialchars($tb).'"></td></tr>
		<tr><td>Page title</td><td><input type="text" name=pageTitle value ="'.htmlspecialchars($tb).'"></td></tr>
		<tr><td>Page header</td><td><input type="checkbox" name=pageHeader></td></tr>
		<tr><td>HTML header &amp; footer</td><td><input type="checkbox" name=HTMLissues></td></tr>
		<tr><td>CSS basic stylesheet</td><td><input checked type="checkbox" name=CSSstylesheet></td></tr>
		</table><br>
		<input type="submit" name="submit" value="Submit">
		<input type="submit" name="cancel" value="Cancel">
		<input type="hidden" name="options" value="1">
		</form>'.PHP_EOL;
} else {
	echo '<h1>Here is your phpMyEdit calling program</h1>'.PHP_EOL;
	echo '<h2>You may now copy and paste it into your PHP editor</h2>'.PHP_EOL;
	if ($pageHeader) {
		echo_buffer('<h3>'.$pageTitle.'</h3>');
	}
	$versions    = '';
	$versions_ar = get_versions();
	foreach (array(
				'version' => 'phpMyEdit version:',
				'core'    => 'phpMyEdit.class.php core class:',
				'setup'   => 'phpMyEditSetup.php script:',
				'current' => 'generating setup script:')
			as $type => $desc) {
		$version = isset($versions_ar[$type]) ? $versions_ar[$type] : 'unknown';
		$versions .= sprintf("\n *  %36s %s", $desc, $version);
	}
	echo_buffer("<?php

/*
 * IMPORTANT NOTE: This generated file contains only a subset of huge amount
 * of options that can be used with phpMyEdit. To get information about all
 * features offered by phpMyEdit, check official documentation. It is available
 * online and also for download on phpMyEdit project management page:
 *
 * http://platon.sk/projects/main_page.php?project_id=5
 *
 * This file was generated by:
 *$versions
 */

// MySQL host name, user name, password, database, and table
\$opts['hn'] = '$hn';
\$opts['un'] = '$un';
\$opts['pw'] = '$pw';
\$opts['db'] = '$db';
\$opts['tb'] = '$tb';

// Name of field which is the unique key
\$opts['key'] = '$id';

// Type of key field (int/real/string/date etc.)");

	if ($id == '') {
		echo_buffer("\$opts['key_type'] = '';");
	} else {
		$tb_desc = $dbl->query("DESCRIBE $tb");
		while( ($fd = $tb_desc->fetch())!==false)	{
			if ($fd == $id) {
				echo_buffer("\$opts['key_type'] = '".@mysql_field_type($fds, $j)."';");
				break;
			}
		}
	}
	echo_buffer("
// Sorting field(s)
\$opts['sort_field'] = array('$id');

// Number of records to display on the screen
// Value of -1 lists all records in a table
\$opts['inc'] = 15;

// Options you wish to give the users
// A - add,  C - change, P - copy, V - view, D - delete,
// F - filter, I - initial sort suppressed
\$opts['options'] = 'ACPVDF';

// Number of lines to display on multiple selection filters
\$opts['multiple'] = '4';

// Navigation style: B - buttons (default), T - text links, G - graphic links
// Buttons position: U - up, D - down (default)
\$opts['navigation'] = 'DB';

// Display special page elements
\$opts['display'] = array(
	'form'  => true,
	'query' => true,
	'sort'  => true,
	'time'  => true,
	'tabs'  => true
);

// Set default prefixes for variables
\$opts['js']['prefix']               = 'PME_js_';
\$opts['dhtml']['prefix']            = 'PME_dhtml_';
\$opts['cgi']['prefix']['operation'] = 'PME_op_';
\$opts['cgi']['prefix']['sys']       = 'PME_sys_';
\$opts['cgi']['prefix']['data']      = 'PME_data_';

/* Get the user's default language and use it if possible or you can
   specify particular one you want to use. Refer to official documentation
   for list of available languages. */
\$opts['language'] = \$_SERVER['HTTP_ACCEPT_LANGUAGE'] . '-UTF8';

/* Table-level filter capability. If set, it is included in the WHERE clause
   of any generated SELECT statement in SQL query. This gives you ability to
   work only with subset of data from table.

\$opts['filters'] = \"column1 like '%11%' AND column2<17\";
\$opts['filters'] = \"section_id = 9\";
\$opts['filters'] = \"PMEtable0.sessions_count > 200\";
*/

/* Field definitions
   
Fields will be displayed left to right on the screen in the order in which they
appear in generated list. Here are some most used field options documented.

['name'] is the title used for column headings, etc.;
['maxlen'] maximum length to display add/edit/search input boxes
['trimlen'] maximum length of string content to display in row listing
['width'] is an optional display width specification for the column
          e.g.  ['width'] = '100px';
['mask'] a string that is used by sprintf() to format field output
['sort'] true or false; means the users may sort the display on this column
['strip_tags'] true or false; whether to strip tags from content
['nowrap'] true or false; whether this field should get a NOWRAP
['select'] T - text, N - numeric, D - drop-down, M - multiple selection
['options'] optional parameter to control whether a field is displayed
  L - list, F - filter, A - add, C - change, P - copy, D - delete, V - view
            Another flags are:
            R - indicates that a field is read only
            W - indicates that a field is a password field
            H - indicates that a field is to be hidden and marked as hidden
['URL'] is used to make a field 'clickable' in the display
        e.g.: 'mailto:\$value', 'http://\$value' or '\$page?stuff';
['URLtarget']  HTML target link specification (for example: _blank)
['textarea']['rows'] and/or ['textarea']['cols']
  specifies a textarea is to be used to give multi-line input
  e.g. ['textarea']['rows'] = 5; ['textarea']['cols'] = 10
['values'] restricts user input to the specified constants,
           e.g. ['values'] = array('A','B','C') or ['values'] = range(1,99)
['values']['table'] and ['values']['column'] restricts user input
  to the values found in the specified column of another table
['values']['description'] = 'desc_column'
  The optional ['values']['description'] field allows the value(s) displayed
  to the user to be different to those in the ['values']['column'] field.
  This is useful for giving more meaning to column values. Multiple
  descriptions fields are also possible. Check documentation for this.
*/
");
	$dbl->exec("use $db");
	$tb_desc = $dbl->query("DESCRIBE $tb");
	while(($fds = $tb_desc->fetch()) !== false) {
		$ts_cnt  = 0;
		$fd = $fds[0]; // name
		//$fm = mysql_fetch_field($fds,$k);
		$fn = strtr($fd, '_-.', '   ');
		$fn = preg_replace('/(^| +)id( +|$)/', '\\1ID\\2', $fn); // uppercase IDs
		$fn = ucfirst($fn);
		$row = $fds;
		echo_buffer('$opts[\'fdd\'][\''.$fd.'\'] = array('); // )
		echo_buffer("  'name'     => '".str_replace('\'','\\\'',$fn)."',");
		$auto_increment = strstr($row[5], 'auto_increment') ? 1 : 0;
		if (substr($row[1],0,3) == 'set') {
			echo_buffer("  'select'   => 'M',");
		} else {
			echo_buffer("  'select'   => 'T',");
		}
//		print_r("<br>$fd<br>$fn<br>");
//		print_r("row<br>");
//		print_r($row[1]);
//		print_r("endrow<br>");
		if ($auto_increment) {
			echo_buffer("  'options'  => 'AVCPDR', // auto increment");
		}
		// timestamps are read-only
		else if ($row[1] == 'timestamp') {
			if ($ts_cnt > 0) {
				echo_buffer("  'options'  => 'AVCPD',");
			} else { // first timestamp
				echo_buffer("  'options'  => 'AVCPDR', // updated automatically (MySQL feature)");
			}
			$ts_cnt++;
		}
		$ml = 0;
		{
			$pml = parseColumnType($row[1]);
			foreach($pml as $i => $j)	{
				if ($i=='length')
					$ml = $j;
			}
		echo_buffer("  'maxlen'   => ".$ml);
		}
		//@mysql_field_len($fds,$k).',');
		// blobs -> textarea
		if ($row[1] == 'blob') {
			echo_buffer("  'textarea' => array(");
			echo_buffer("    'rows' => 5,");
			echo_buffer("    'cols' => 50),");
		}
		// SETs and ENUMs get special treatment
		if ((substr($row[1],0,3) == 'set' || substr($row[1],0,4) == 'enum')
				&& ! (($pos = strpos($row[1], '(')) === false)) {
			$indent = str_repeat(' ', 18);
			$outstr = substr($row[1], $pos + 2, -2);
			$outstr = explode("','", $outstr);
			$outstr = str_replace("''", "'",  $outstr);
			$outstr = str_replace('"', '\\"', $outstr);
			$outstr = implode('",'.PHP_EOL.$indent.'"', $outstr);
			echo_buffer("  'values'   => array(".PHP_EOL.$indent.'"'.$outstr.'"),');
		}
		// automatic support for Default values
		if ($row[4] != '' && $row[4] != 'NULL') {
			echo_buffer("  'default'  => '".$row[4]."',");
		} else if ($auto_increment) {
			echo_buffer("  'default'  => '0',");
		}
		// check for table constraints
		$outstr = check_constraints($dbl, $tb, $fd);
		if ($outstr != '') {
			echo_buffer($outstr);
		}
		echo_buffer("  'sort'     => true");
		//echo_buffer("  'nowrap'   => false,");
		echo_buffer(');');
	}

	echo_buffer("
// Now important call to phpMyEdit
require_once 'phpMyEdit.class.php';
new phpMyEdit(\$opts);

?>
");

	$css_directive = <<<END
<style type="text/css">
	hr.pme-hr		     { border: 0px solid; padding: 0px; margin: 0px; border-top-width: 1px; height: 1px; }
	table.pme-main 	     { border: #004d9c 1px solid; border-collapse: collapse; border-spacing: 0px; width: 100%; }
	table.pme-navigation { border: #004d9c 0px solid; border-collapse: collapse; border-spacing: 0px; width: 100%; }
	td.pme-navigation-0, td.pme-navigation-1 { white-space: nowrap; }
	th.pme-header	     { border: #004d9c 1px solid; padding: 4px; background: #add8e6; }
	td.pme-key-0, td.pme-value-0, td.pme-help-0, td.pme-navigation-0, td.pme-cell-0,
	td.pme-key-1, td.pme-value-1, td.pme-help-0, td.pme-navigation-1, td.pme-cell-1,
	td.pme-sortinfo, td.pme-filter { border: #004d9c 1px solid; padding: 3px; }
	td.pme-buttons { text-align: left;   }
	td.pme-message { text-align: center; }
	td.pme-stats   { text-align: right;  }
</style>
END;
	if (! $CSSstylesheet) {
		$css_directive = '';
	}

	if ($HTMLissues) {
		$buffer = <<<END
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>$pageTitle</title>
$css_directive
</head>
<body>
$buffer
</body>
</html>
END;
	} else if ($CSSstylesheet) {
		$buffer = $css_directive . $buffer;
	}
	// write the content include file
	echo 'Trying to write content file to: <b>'.'./'.$contentFile.'</b><br>'.PHP_EOL;
	$filehandle = @fopen('./'.$contentFile, 'w+');
	if ($filehandle) {
		fwrite($filehandle, $buffer);
		flush();
		fclose($filehandle);
		echo 'phpMyEdit content file written successfully<br>';
	} else {
		echo 'phpMyEdit content file was NOT written due to inssufficient privileges.<br>';
		echo 'Please copy and paste content listed below to <i>'.'./'.$contentFile.'</i> file.';
	}
	echo '<br><hr>';
	echo '<pre>';
	echo_html($buffer);
	echo '</pre><hr>'.PHP_EOL;
}

?>

</body>
</html>

