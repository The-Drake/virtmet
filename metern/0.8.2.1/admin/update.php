<?php
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header("Location: ../");
    exit;
}
include('../scripts/version.php');
define('checkaccess', TRUE);
$url = 'http://123solar.org/metern/latest_version.php';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" >
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" >
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
	document.getElementById('msg').innerHTML = 'You are running <?php echo $VERSION$VERSION2; ?>, ' + lastvers + ' is available '+
	'<br><br><form method=\'POST\' action=\'update2.php\'>'+
	'<input type=\'submit\' name=\'bntsubmit\' value=\'Update\' onclick=\"if(!confirm(\'The update procedure will require to log in\')){return false;}\">'
	'</form><br>';
	} else {
	document.getElementById('status').src = '../images/accept.png';
	document.getElementById('msg').innerHTML = 'Neat ! it\'s up to date';
	}
    },
    error: function(){
    	document.getElementById('status').src = '../images/question.gif';
	document.getElementById('msg').innerHTML = 'Time out: can\'t retreive <?php echo $url; ?>';
    },
    timeout: 3000
});

})
</script>
<link rel="stylesheet" href="../js/jqueryuicss/jquery-ui.css" type="text/css">
</head>
<body>
<table width="95%" height="80%" border="0" cellspacing="0" cellpadding="0" align="center">
  <tr bgcolor="#FFFFFF" height="80"> 
  <td class="cadretopleft" width="128"><img src="../styles/default/images/leaf6432.png" width="64" height="32" alt="meterN"></td>
  <td class="cadretop" align="center"><b>meterN Administration</font></td>
  <td class="cadretopright" width="128" align="right"></td>
  </tr>
  <tr bgcolor="#CCCC66">
<td align=right COLSPAN="3" class="cadre" height="10">
&nbsp;
</td></tr>  
<tr valign="top"> 
    <td COLSPAN="3" class="cadrebot" bgcolor="#d3dae2">
<!-- #BeginEditable "mainbox" -->
<br>
<br>
<div align=center>
<img src='../images/loading.gif' id='status' width=16 height=16>
<span id='msg'>Checking<span>
</div>
<br>
<div align=center><INPUT TYPE='button' onClick="location.href='admin.php'" value='Back'></div>
<!-- #EndEditable -->
</td>
</tr>
</table>
</body>
</html>
