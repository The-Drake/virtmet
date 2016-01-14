<?php
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header("Location: ../");
    exit;
}

include('../scripts/version.php');
include('../scripts/memory.php');

function convert($size)
{
    $unit = array(
        'b',
        'kb',
        'mb',
        'gb',
        'tb',
        'pb'
    );
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" >
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>meterN Debug</title>
<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
<link rel="stylesheet" href="../styles/default/css/style.css" type="text/css">
<script type="text/javascript" src="../js/jquery.min.js"></script>
<script type="text/javascript" src="../js/jquery-ui.min.js"></script>
<link rel="stylesheet" href="../js/jqueryuicss/jquery-ui.css" type="text/css">
</head>
<body>
<table width="95%" height="80%" border="0" cellspacing="0" cellpadding="0" align="center">
  <tr bgcolor="#FFFFFF" height="64"> 
  <td class="cadretopleft" width="128"><img src="../styles/default/images/leaf6432.png" width="64" height="32" alt="meterN"></td>
  <td class="cadretop" align="center"><b>meterN Debug</font></td>
  <td class="cadretopright" width="128" align="right"></td>
  </tr>
  <tr bgcolor="#CCCC66">
<td COLSPAN="3" class="cadre" height="10">
&nbsp;
</td></tr>  
<tr valign="top"> 
    <td COLSPAN="3" class="cadrebot" bgcolor="#d3dae2">
<!-- #BeginEditable "mainbox" -->
<?php
echo "
<br>
<table border=1 width='95%' border=0 cellspacing=0 cellpadding=5 align='center'>
<tr><td COLSPAN='2'>
<b>$VERSION$VERSION2</b> Memory Usage: ";
echo memory_get_usage();
echo 'bytes - ';
echo convert(memory_get_usage(true));
echo "</tr></td>
<tr><td COLSPAN='2'>
<div align='left'><img src='../images/exclamation.png' width='16' height='16' border='0'> To get log error file, the debug mode have to be enable in the main configuration</div>
<textarea style='resize: none;background-color: #DCDCDC' cols='100' rows='10'>";
if (file_exists('../data/metern.err')) {
    $lines = file('../data/metern.err');
    foreach ($lines as $line_num => $line) {
        echo "$line";
    }
}
echo "</textarea>
</td></tr>
<tr><td valign='top'><b>Hardware and communication apps. rights :</b><br>";

$datareturn = shell_exec('whoami');
echo "You are using as user : <b>$datareturn</b>, it belong to those groups: ";
$datareturn = shell_exec("groups $datareturn");
echo "<b>$datareturn</b><br>
</td>
<td valign='top'>Usually there is problem to access the communication ports such as /dev/ttyUSB* or /dev/ttyACM* as http user. <br>
Check what is your webserver's user. It's often 'http' but it might be 'www' or something else. <br>
The peripherals are usually owned by the uucp group, add your user: usermod -a -G uucp http<br>
Then verifiy with : groups http.<br>
<br>You also need to grant the access to your com. apps. Locate with 'whereis mycomapp' and chmod a+x /pathto/mycomapp.py.<br>
<br>Then you may need to restart php and your webserver. (e.g. systemctl restart php-fpm and systemctl restart nginx)
</td></tr>
<tr><td COLSPAN='2'>";

@$shmid = shmop_open($LIVEMEMORY, 'a', 0, 0);
if (!empty($shmid)) {
    $size = shmop_size($shmid);
    shmop_close($shmid);
    $shmid     = shmop_open($LIVEMEMORY, 'c', 0644, $size);
    $data      = shmop_read($shmid, 0, $size);
    $livearray = json_decode($data, true);
    shmop_close($shmid);
} else {
    $livearray[0] = 'empty';
}

@$shmid = shmop_open($ILIVEMEMORY, 'a', 0, 0);
if (!empty($shmid)) {
    $size = shmop_size($shmid);
    shmop_close($shmid);
    $shmid     = shmop_open($ILIVEMEMORY, 'c', 0644, $size);
    $data      = shmop_read($shmid, 0, $size);
    $ilivearray = json_decode($data, true);
    shmop_close($shmid);
} else {
    $ilivearray[0] = 'empty';
}

@$shmid = shmop_open($MEMORY, 'a', 0, 0);
if (!empty($shmid)) {
    $size = shmop_size($shmid);
    shmop_close($shmid);
    $shmid    = shmop_open($MEMORY, 'c', 0644, $size);
    $data     = shmop_read($shmid, 0, $size);
    $memarray = json_decode($data, true);
    shmop_close($shmid);
} else {
    $memarray[0] = 'empty';
}
echo "Live (#$LIVEMEMORY): ";
print_r($livearray);
echo "<br>Indicators (#$ILIVEMEMORY): ";
print_r($ilivearray);
echo "<br>Memory (#$MEMORY): ";
print_r($memarray);
echo "</tr></td>
</table>";
?>
<br>&nbsp;
<div align=center><INPUT TYPE='button' onClick="location.href='admin.php'" value='Back'></div>
<br>&nbsp;
<!-- #EndEditable -->
</td>
</tr>
</table>
<br>&nbsp;
</body>
</html>
