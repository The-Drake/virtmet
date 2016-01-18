#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) die('Direct access not permitted');
// This script will output --virtuals and estimated-- Wh in/out and
// self-consumption counters into a meterN compatible format.
// It's based on your household's production and consumption real meters.
// The power values will be averaged during a 5 min period and will lag from 5 min.
// The self-consumption estimation is only valid if the consumption and
// production are on the same phase.
// ln -s /var/www/metern/comapps/eflow.php /usr/bin/eflow
// eflow {whout|whin|selfc} {power|energy}

// Setup your virtual meters identification numbers:
$whinmet  = 5; // Meter Number Prelievi (Whin)
$whoutmet = 6; // Meter Number Immissioni (Whout)
$selfcmet = 7; // Meter Number Autoconsumo (selfc)

// No edit is needed below -----------------------------------------------------
$version = '0.2.6.1/drk';

if (!isset($argv[1],$argv[2])) $argv[1]=null;

if ( $argv[1] != NULL 
     && ($argv[1]== 'whout' || $argv[1]=='whin' || $argv[1]=='selfc')
     && ($argv[2]=='energy' || $argv[2]=='power')) {

    $prodnumlist = array();
    $consnumlist = array();

    $prod=0.0;
    $cons=0.0;
    $val=0.0;

    define('checkaccess', TRUE);
    include(__DIR__ . '/../config/config_main.php');
    include(__DIR__ . '/../scripts/memory.php');

    // Read MeterN meters types and config
    for ($i = 1; $i <= $NUMMETER; $i++) {
        if (file_exists(__DIR__ . "/../config/config_met$i.php")) {
            include(__DIR__ . "/../config/config_met$i.php");
            if (${"PROD$i"} == 1 && ${"TYPE$i"} == 'Elect' && !${"SKIPMONITORING$i"}) $prodnumlist[] = $i;
            if (${"PROD$i"} == 2 && ${"TYPE$i"} == 'Elect' && !${"SKIPMONITORING$i"}) $consnumlist[] = $i;
        }
    }

    if ($argv[2] == 'power') {

	// Read MeterN Livememory
	@$shmid = shmop_open($LIVEMEMORY, 'a', 0, 0);
	if (!empty($shmid)) {
            $data = shmop_read($shmid, 0, 0);
            shmop_close($shmid);
            $memarray = json_decode($data, true);
        }

        $cnt = count($prodnumlist);
        for ($i = 0; $i < $cnt; $i++)
	    if (isset($memarray["${'METNAME'.$prodnumlist[$i]}$prodnumlist[$i]"]))
                $prod += $memarray["${'METNAME'.$prodnumlist[$i]}$prodnumlist[$i]"];

        $cnt = count($consnumlist);
        for ($i = 0; $i < $cnt; $i++)
	    if (isset($memarray["${'METNAME'.$consnumlist[$i]}$consnumlist[$i]"]))
	        $cons += $memarray["${'METNAME'.$consnumlist[$i]}$consnumlist[$i]"];

    } elseif ($argv[2] == 'energy') {

	// Read MeterN Memory
	@$shmid = shmop_open($MEMORY, 'a', 0, 0);
	if (!empty($shmid)) {
            $data = shmop_read($shmid, 0, 0);
	    shmop_close($shmid);
            $memarray = json_decode($data, true);
        }

        $cnt = count($prodnumlist);
        for ($i = 0; $i < $cnt; $i++) 
            if (isset($memarray["Diffcounter$prodnumlist[$i]"]))
                    $prod += $memarray["Diffcounter$prodnumlist[$i]"];

        $cnt = count($consnumlist);
        for ($i = 0; $i < $cnt; $i++)
            if (isset($memarray["Diffcounter$consnumlist[$i]"]))
                    $cons += $memarray["Diffcounter$consnumlist[$i]"];

    }

    $ID = $argv[1];

    // Computations
    if ($ID == 'whout') { // immissioni
        $val = $prod - $cons; if ($val  < 0) $val=0;
        $valID = ${'ID' . $whoutmet};
    } elseif ($ID == 'whin') { // prelievi
        $val = $cons - $prod; if ($val < 0) $val = 0;
        $valID = ${'ID' . $whinmet};
    } elseif ($ID == 'selfc') { // autoconsumo
        $val = ($prod > $cons) ? $cons : $prod;
        $valID = ${'ID' . $selfcmet};
    }

    // Output
    settype($val, 'float');

    if ($argv[2] == 'energy') {
	$val+= $memarray['Totalcounter'.${$ID.'met'}];
	$val = round($val, ${'PRECI' . ${$ID . 'met'}});
	if (isset($argv[3]) && $argv[3] == '--plain') $str = utf8_decode("$val");
        else $str = utf8_decode("$valID($val*Wh)");
    } else {
	$val = round($val, ${'PRECI' . ${$ID . 'met'}});
	if (isset($argv[3]) && $argv[3] == '--plain') $str = utf8_decode("$val");
	else $str = utf8_decode("$valID($val*W)");
    }
    echo "$str\n";

} else {
    die("eflow v$version\n"
        . "Usage: $argv[0] {whout|whin|selfc} {energy|power} [--plain]\n");
}
?>
