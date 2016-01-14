<?php
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header("Location: ../");
    exit;
}
include('../scripts/version.php');
$url = 'http://123solar.org/metern/latest_version.php';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" >
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>meterN Administration</title>
<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW">
<link rel="stylesheet" href="../styles/default/css/style.css" type="text/css">
<script type="text/javascript" src="../js/jquery.min.js"></script>
<script type="text/javascript" src="../js/jquery-ui.min.js"></script>
<script type='text/javascript'>
$(document).ready(function() 
{
var vers='<?php echo $VERSION; ?>'; 

$.ajax({
    url : '<?php echo $url; ?>',
    dataType: 'json',
    type: 'GET',
    success: function(response){
	json =eval(response);
	lastvers =json['LASTVERSION'];
	
	if (vers!=lastvers) {
	document.getElementById('status').src = '../images/exclamation.png';
	document.getElementById('msg').innerHTML = '<img src=\'../styles/default/images/sqe.gif\'><a href=\'update.php\'>Update</a>';
	} else {
	document.getElementById('status').src = '../images/accept.png';
	document.getElementById('msg').innerHTML = '';
	}
    },
    error: function(){
	document.getElementById('status').src = '../images/question.gif';
    document.getElementById('msg').innerHTML = '';
    },
    timeout: 3000
});

})
</script>
<link rel="stylesheet" href="../js/jqueryuicss/jquery-ui.css" type="text/css">
</head>
<body>
<table width="95%" height="80%" border="0" cellspacing="0" cellpadding="0" align="center">
  <tr bgcolor="#FFFFFF" height="64"> 
  <td class="cadretopleft" width="128"><img src="../styles/default/images/leaf6432.png" width="64" height="32" alt="meterN"></td>
  <td class="cadretop" align="center"><b>meterN Administration</font></td>
  <td class="cadretopright" width="128" align="right"></td>
  </tr>
  <tr bgcolor="#CCCC66">
<td COLSPAN="3" class="cadre" height="10">
&nbsp;
</td></tr>  
<tr valign="top"> 
    <td COLSPAN="3" class="cadrebot" bgcolor="#d3dae2">
<!-- #BeginEditable "mainbox" -->
<br>
<div align=center><b>Welcome <?php
echo $_SERVER["PHP_AUTH_USER"];
?></b></div>
<hr>
<br>&nbsp;
<div align=center><span id='messageSpan'></span></div>
<?php
include('../scripts/version.php');
define('checkaccess', TRUE);
include('../config/config_main.php');
include('../scripts/memory.php');

date_default_timezone_set($DTZ);
$SCRDIR = dirname(__FILE__);

if (!empty($_GET['startstop'])) {
    $startstop = $_GET['startstop'];
} else {
    $startstop = null;
}

if ($startstop == 'start' || $startstop == 'stop') {
    $now = date($DATEFORMAT . ' H:i:s');
    if ($startstop == 'start') {
        $output = shell_exec('ps -ef | grep meterN.php | grep -v grep');
        
        if (is_null($output)) {
            $stringData = "$now\tStarting meterN\n\n";
            if ($DEBUG) {
                $myFile = dirname($SCRDIR) . '/data/metern.err';
                file_put_contents($myFile, $stringData, FILE_APPEND);
                $output = exec('php ../scripts/metern.php >> ../data/metern.err 2>&1 &');
            } else {
                $output = exec('php ../scripts/metern.php > /dev/null 2>&1 &');
            }
                $stringData .= file_get_contents('../data/events.txt');
                file_put_contents('../data/events.txt', $stringData);
        }
    }
    if ($startstop == 'stop') {
        $output     = exec('pkill -f metern.php> /dev/null 2>&1 &');
        $stringData = "$now\tStopping meterN\n\n";
        if ($DEBUG) {
            $myFile = dirname($SCRDIR) . '/data/metern.err';
            file_put_contents($myFile, $stringData, FILE_APPEND);
        }
        $stringData .= file_get_contents('../data/events.txt');
        file_put_contents('../data/events.txt', $stringData);
        @$shmid = shmop_open($LIVEMEMORY, 'a', 0, 0);
        shmop_delete($shmid);
        shmop_close($shmid);
    }
    echo "
<script type='text/javascript'>
  document.getElementById('messageSpan').innerHTML = \"...Please wait...\"; 
  setTimeout(function () {
    window.location.href = 'admin.php?startstop=done';
  }, 1000);
</script>
";
}
echo "
<table border=0 align='center' width='80%'>
<tr><td align='left'>";

if ($startstop != 'start' && $startstop != 'stop') {
    echo "<form action='admin.php' method='GET'>";
    $output = shell_exec('ps -ef | grep metern.php | grep -v grep');
    
    if (is_null($output)) {
        echo "<input type='image' src='../images/off.png' value='' width=80 height=32>
		<input type='hidden' name='startstop' value='start'>";
    } else {
        echo "<input type='image' src='../images/on.png' value='' width=80 height=32 onclick=\"if(!confirm('Stop meterN ?')){return false;}\">
		<input type='hidden' name='startstop' value='stop'>";
    }
    echo "</form>
<br>
<img src='../styles/default/images/sqe.gif'><a href='admin_main.php'>Main configuration</a><br><br>
<img src='../styles/default/images/sqe.gif'><a href='admin_meter.php'>Configure meter(s) and sensor(s) logger(s)</a><br><br>
<img src='../styles/default/images/sqe.gif'><a href='admin_indicator.php'>Configure indicator(s) <font size='-1'>(No logged)</font></a><br><br>
<img src='../styles/default/images/sqe.gif'><a href='admin_layout.php'>Index layout configuration</a><br><br>
<img src='../styles/default/images/sqe.gif'><a href='help.php'>Debugger</a><br><br>
<span id='msg'><span>
<br><br>
</tr></td>
</table>
<form><div align=center>
<INPUT TYPE='button' onClick=\"location.href='../'\" value='Back'>
</div>
</form>
<hr>
<table border=0 cellspacing=0 cellpadding=0 width='100%' align=center>
<tr valign=top><td>&nbsp;</td>
<td width='33%'>
<div align=center><a href='kiva.html'>meterN is free !</a></div>
</td>
<td width='33%' align=right><img src='../images/loading.gif' id='status' width=16 height=16> $VERSION$VERSION2</td>
</tr>
</table>
";
}
?>
          <!-- #EndEditable -->
          </td>
          </tr>
</table>
</body>
</html>
