#!/usr/bin/php
<?php
if (isset($_SERVER['REMOTE_ADDR'])) die('Direct access not permitted');
//
// Usage: $argv[0] {virtmeternum} {meternumlisttoadd|0} {meternumlisttosub|0}{energy|power|cost} {F1|F2|F3|F23|0} [--plain]
//
// eg:
//      virtmeter 14 9,11 8 energy 0    -> met14 = met9+met11-met8
//      virtmeter 15 12 0 energy F1     -> met15 = met12 (only if F1)
//      virtmeter 16 12 0 energy F23    -> met16 = met12 (only if F23)
//

// --------- COST PARAMETERS ---------------------------------------------------

$POTENZA = 6; // KW

// ---- $fileTariffa format ------
// DVAL,QS,QP,F1PE,F23PE,S1,S1PE,S2,S2PE,S3,S3PE,S4,S4PE,ACCISA,IVA
// 20151001,44.5340,16.4109,0.06730,0.06186,1800,0.127502,2640,0.144632,4440,0.184452,99999999,0.227122,0.0227,0.10
// ...
//------------------------

// Optional external Tariffa filename
$filenameTariffa = 'tariffa.csv';

// If $filenameTariffa does not exist, following values will be used

// Quota servizi (/anno)
$QS = 44.5340;
// Quota energia (*Kw/anno)
$QP = 16.4109;
// Prezzo Energia
$F1PE  = 0.06730; $F23PE = 0.06186;
// Scaglioni e Prezzo Scaglioni 
$S1 = 1800;       $S2 = 2640;       $S3 = 4440;       $S4 = 9999999;
$S1PE = 0.127502; $S2PE = 0.144632; $S3PE = 0.184452; $S4PE = 0.227122;
// Percentuali Iva e Accisa
$ACCISA = 0.0227; $IVA=0.10;

// -----------------------------------------------------------------------------

$version = '0.1';

// No file edit needed below: just pass the right parameters to the script
$argvMetnum     =1;
$argvMetnumToAdd=2;
$argvMetnumToSub=3;
$argvEnergyPower=4;
$argvFascia     =5;
$argvOption     =6;

$metnumtoadd  = array();
$metnumtosub  = array();

$calltime = time();

if (!isset($argv[$argvMetnum]))      $argv[$argvMetnum]=null;
if (!isset($argv[$argvMetnumToAdd])) $argv[$argvMetnumToAdd]=null;
if (!isset($argv[$argvMetnumToSub])) $argv[$argvMetnumToSub]=null;
if (!isset($argv[$argvEnergyPower])) $argv[$argvEnergyPower]=null;
if (!isset($argv[$argvFascia]))      $argv[$argvFascia]=null;

function isFesta($tsTime) {
  $feste=Array(101, 106, 425, 501, 602, 815, 1101, 1208, 1225, 1226);

  // Pasqua é sempre domenica, controllo Pasquetta
  if (date('Y-m-d', strtotime('+1 day', easter_date())) == date('Y-m-d', $tsTime)) return (true);

  // Controllo le altre feste
  $data100 = (date('n', $tsTime) * 100) + date('d', $tsTime);
  foreach ($feste as $festa) {
    if ($festa == $data100) return (true);
  }
  return false;
}

// Fascia tariffaria (F1, F2, F3)
//
// Le fasce orarie sono:
// F1 (ore di punta):    dalle ore 8.00 alle ore 19.00 dal lunedì al venerdì
// F2 (ore intermedie):  dalle ore 7.00 alle ore 8.00 e dalle ore 19.00 alle
//                       ore 23.00 dal lunedì al venerdì e dalle ore 7.00 alle
//                       ore 23.00 del sabato;
// F3 (ore fuori punta): dalle ore 00.00 alle ore 7.00 e dalle ore 23.00 alle
//                       ore 24.00 dal lunedì al sabato e tutte le ore della
//                       domenica e dei giorni:
//                              1 e 6 gennaio;
//                              luned� dell'Angelo;
//                              25 aprile;
//                              1 maggio;
//                              2 giugno;
//                              15 agosto;
//                              1 novembre;
//                              8, 25 e 26 dicembre.
//
function fascia($tsTime) {
  $giorno = date("w",$tsTime); // 0 (dom) -> 6 (sab)
  $ora    = date('Hi', $tsTime); // 23:35 -> 2335

  // Il confronto orario tiene conto del ritardo di metern ad acquisire il dato
  // e considera un possibile ritardo di misura fino a 60 secondi rispetto alle
  // ore "intere". Le letture al termine della fascia oraria vengono
  // considerate appartenenti alla fascia oraria precedente.
  // La prima lettura nella nuova fascia oraria avviene dopo 5 minuti, quindi
  // risulta sempre nell'intervallo temporale di spettanza ed il suo incremento
  // viene aggiunto naturalmente alla fascia oraria corretta.

  // Festivo o domenica -> Fascia F3
  if (isfesta($tsTime) || $giorno == 0)
      return 'F3';

  // "23:00:00" > "07:00:00" -> Fascia F3
  // c'� un cambio data e l'ordine dei numeri � inverso -> !
  if (!($ora <= "2300" && $ora > "0700"))
      return 'F3';

  // Sabato "07:00:00" > "23:00:00" -> Fascia F2
  if ($giorno == 6 && $ora > "0700" && $ora <= "2300")
      return 'F2';

  // Feriali "07:00:00" > "08:00:00", "19:00:00" > "23:00:00" -> Fascia F2
  if ($ora > "0700" && $ora <= "0800" ||
      $ora > "1900" && $ora <= "2300" )
      return 'F2';

  // Tutto il resto F1
  return 'F1';
}

function isFascia($tsTime, $F) {
  $result = fascia($tsTime);
  if ($F=='0')       return TRUE;
  if ($result == $F) return TRUE;
  if ($F=='F23' && ($result=='F2' || $result=='F3')) return TRUE;
  return FALSE;
}


if ( $argv[$argvMetnum] != NULL
     && $argv[$argvMetnumToAdd] != NULL && $argv[$argvMetnumToSub] != NULL
     && $argv[$argvEnergyPower] != NULL
     && ($argv[$argvEnergyPower]=='energy' || $argv[$argvEnergyPower]=='power'
      || $argv[$argvEnergyPower]=='cost')
     && ($argv[$argvFascia]=='F1' || $argv[$argvFascia]=='F2' || $argv[$argvFascia]=='F3'
         || $argv[$argvFascia]=='F23' || $argv[$argvFascia]=='0')) {

    $metnum = $argv[$argvMetnum];          // Virtual Meter Number
    $fascia = $argv[$argvFascia];

    // Get Meter Numbers to Add into an Array of int
    $metnumtoadd = json_decode('[' . $argv[$argvMetnumToAdd] . ']', true);
    // Get Meter Numbers to Sub into an Array of int
    $metnumtosub = json_decode('[' . $argv[$argvMetnumToSub] . ']', true);

    $val=0.0;
    $toadd=0.0;
    $tosub=0.0;

    $addnumlist = array();
    $subnumlist = array();

    define('checkaccess', TRUE);
    include("../config/config_main.php");
    include("../scripts/memory.php");

    // Read MeterN meters types and config
    for ($i = 1; $i <= $NUMMETER; $i++) {
        if (file_exists("../config/config_met$i.php")) {
          include("../config/config_met$i.php");
          if (in_array ( $i, $metnumtoadd, true) && !${"SKIPMONITORING$i"}) $addnumlist[] = $i;
          if (in_array ( $i, $metnumtosub, true) && !${"SKIPMONITORING$i"}) $subnumlist[] = $i;
        }
    }

    if ($argv[$argvEnergyPower] == 'power' && isFascia($calltime, $fascia)) {

	// Read MeterN Livememory
	@$shmid = shmop_open($LIVEMEMORY, 'a', 0, 0);
	if (!empty($shmid)) {
            $data = shmop_read($shmid, 0, 0);
            shmop_close($shmid);
            $memarray = json_decode($data, true);
        }

        $cnt = count($addnumlist);
        for ($i = 0; $i < $cnt; $i++)
	    if (isset($memarray["${'METNAME'.$addnumlist[$i]}$addnumlist[$i]"]))
                $toadd += $memarray["${'METNAME'.$addnumlist[$i]}$addnumlist[$i]"];

        $cnt = count($subnumlist);
        for ($i = 0; $i < $cnt; $i++)
	    if (isset($memarray["${'METNAME'.$subnumlist[$i]}$subnumlist[$i]"]))
	        $tosub += $memarray["${'METNAME'.$subnumlist[$i]}$subnumlist[$i]"];

    } elseif ($argv[$argvEnergyPower] == 'energy' || $argv[$argvEnergyPower] == 'cost' ) {

	// Read MeterN Memory
	@$shmid = shmop_open($MEMORY, 'a', 0, 0);
	if (!empty($shmid)) {
          $data = shmop_read($shmid, 0, 0);
           shmop_close($shmid);
          $memarray = json_decode($data, true);
        }

        if (isFascia($calltime, $fascia)) {
          $cnt = count($addnumlist);
          for ($i = 0; $i < $cnt; $i++) if (isset($memarray["Diffcounter$addnumlist[$i]"])) $toadd += $memarray["Diffcounter$addnumlist[$i]"];

          $cnt = count($subnumlist);
          for ($i = 0; $i < $cnt; $i++) if (isset($memarray["Diffcounter$subnumlist[$i]"])) $tosub += $memarray["Diffcounter$subnumlist[$i]"];
        }
    }

    $ID = ${"ID$metnum"};

    // Computations
    $val = $toadd - $tosub;
    // Remove check for tolerance compatibility
    //if ($val  < 0) $val=0;

    // Output
    settype($val, 'float');

    if ($argv[$argvEnergyPower] == 'energy') {
	$val+= isset($memarray['Totalcounter'.$metnum]) ? $memarray['Totalcounter'.$metnum] : 0;
	$val = round($val, ${'PRECI' . $metnum});
	if (isset($argv[$argvOption]) && $argv[$argvOption] == '--plain') $str = utf8_decode("$val");
        else $str = utf8_decode("$ID($val*Wh)");
    } elseif ($argv[$argvEnergyPower]=='cost') {

        if ($val != 0) {  
          // Struttura file tariffa.csv
          //
          // DVAL,QS,QP,F1PE,F23PE,S1,S1PE,S2,S2PE,S3,S3PE,S4,S4PE,ACCISA,IVA
          // 20151001,44.5340,16.4109,0.06730,0.06186,1800,0.127502,2640,0.144632,4440,0.184452,99999999,0.227122,0.0227,0.10
          //
          // Se esiste un file tariffa.csv, lo leggo e sovrascrivo i prezzi
          // di default
          //
          if(file_exists($filenameTariffa) && is_readable($filenameTariffa)) {
              $arrayFromCSV =  array_map('str_getcsv', file($filenameTariffa));
              $_header = array_shift($arrayFromCSV);
              array_multisort($arrayFromCSV, SORT_DESC);
              // Ricavo la tariffa in vigore
              for ($i=1; $i< count($arrayFromCSV); $i++) {
                if (date('Ynd')>= $arrayFromCSV[$i][0]) {
                      $_data = $arrayFromCSV[$i];
                      $data=array_combine($_header,$_data);
                      break;
                }
              }
              // Leggo i dati per il calcolo
              foreach ($data as $key => $value) {
                ${$key} = $data[$key];
              }
          }
  
          // Divisori vari
          $yeardays = date("z", strtotime( date('Y') . '-12-31')) + 1;
          $year5min = $yeardays * 24 * 12;
          //$min5in1h = 12;	// 5min in 1h
  
          // Soglia proquota in kwh per 5 minuti
          $PRO5SCAGLIONE1 = $S1 / $year5min;
          $PRO5SCAGLIONE2 = $S2 / $year5min;
          $PRO5SCAGLIONE3 = $S3 / $year5min;
          $PRO5SCAGLIONE4 = $S4 / $year5min;
          //echo "SogliaPro5Scaglione1=$PRO5SCAGLIONE1, SogliaPro5Scaglione2=$PRO5SCAGLIONE2, SogliaPro5Scaglione3=$PRO5SCAGLIONE3", PHP_EOL;
  
          //$tmp = 1/$year5min;
          //echo "year5min=$year5min, $tmp", PHP_EOL;
  
          // Quote fisse
          $QUOTASERVIZI5 = $QS / $year5min;
          $QUOTAPOTENZA5 = $QP * $POTENZA / $year5min;
          //echo "Servizi=$SERVIZI5, QuotaPotenza=$QUOTAPOTENZA5", PHP_EOL;
  
          $CONSUMO5 = $val / 1000;        // Delta consumo in kwh
          // calcolo bolletta (frazione di 5minuti)
  
          // A)  Costi fissi pro quota 5 minuti
          $COSTO5 = $QUOTASERVIZI5 + $QUOTAPOTENZA5 ;
          //echo "Costo5, quota fissa (Servizi + Quotapotenza di $POTENZA Kw: $COSTO5", PHP_EOL;
  
          // B) Quota energia per 5 minuti secondo fascia di tariffa richiesta
          if (fascia($calltime) == 'F1') $QE5 = $CONSUMO5 * $F1PE;
          else $QE5 = $CONSUMO5 * $F23PE;
          //echo "QE5, Quota energia di base ($CONSUMO5 * $FXPE) = $QE5", PHP_EOL;
  
          // C) Calcolo scaglioni
          $COSTO5S1 = ($CONSUMO5 > $PRO5SCAGLIONE1 ? 
                       $PRO5SCAGLIONE1 : $CONSUMO5) * $F1PE ;	// Costo Energia Scaglione1
          //echo "Costo5s1, quota energia, S1 ($CONSUMO5) ($PRO5SCAGLIONE1 * $FS1): $COSTO5S1", PHP_EOL;
          $COSTO5S2 = ($CONSUMO5 > $PRO5SCAGLIONE2 ? $PRO5SCAGLIONE2 - $PRO5SCAGLIONE1 :
                       ($CONSUMO5 > $PRO5SCAGLIONE1 ? $CONSUMO5 - $PRO5SCAGLIONE1 : 0))
                       * $S2PE ;	// Costo Energia Scaglione2
          //echo "Costo5s2, quota energia, S2: $COSTO5S2", PHP_EOL;
          $COSTO5S3 = ($CONSUMO5 > $PRO5SCAGLIONE3 ? $PRO5SCAGLIONE3 - $PRO5SCAGLIONE2 : 
                       ($CONSUMO5 > $PRO5SCAGLIONE2 ? $CONSUMO5 - $PRO5SCAGLIONE2 : 0))
  		     * $S3PE ;	// Costo Energia Scaglione3
          //echo "Costo5s3, quota energia, S3: $COSTO5S3", PHP_EOL;
          $COSTO5S4 = ($CONSUMO5 > $PRO5SCAGLIONE3 ?
                       $CONSUMO5 - $PRO5SCAGLIONE3 : 0 ) * $S4PE ;  // Costo Energia Scaglione4
          //echo "Costo5s4, quota energia, S4: $COSTO5S4", PHP_EOL;
  
          // Totali, accisa e IVA
          $COSTO5PARZIALE = $COSTO5 + $QE5 + $COSTO5S1 + $COSTO5S2 + $COSTO5S3 + $COSTO5S4;
          //echo "Costo5 ($CONSUMO5 kw * 5minuti), senza accise e senza iva= $COSTO5 + $QE5 + $COSTO5S1 + $COSTO5S2 + $COSTO5S3 + $COSTO5S4 = $COSTO5PARZIALE", PHP_EOL;
          $ACCISE5 = $CONSUMO5 * $ACCISA;
          //echo "Accise su $CONSUMO5 kw = $ACCISE5", PHP_EOL;
          $TOTALE5_NOIVA = $COSTO5PARZIALE + $ACCISE5;
          $TOTALE_IVA = $TOTALE5_NOIVA * $IVA;
          //echo "IVA su $COSTO5PARZIALE = $IVA", PHP_EOL;
          $TOTALE5 = $TOTALE5_NOIVA + $TOTALE_IVA;
          //echo "Totale generale = $TOTALE5", PHP_EOL;
  
          // Aggiorno il valore del contatore virtuale
          $val = $TOTALE5;
        }
        
	$val+= isset($memarray['Totalcounter'.$metnum]) ? $memarray['Totalcounter'.$metnum] : 0;
	$val = round($val, ${'PRECI' . $metnum});

	if (isset($argv[$argvOption]) && $argv[$argvOption] == '--plain') $str = utf8_decode("$val");
        else $str = utf8_decode("$ID($val*Euro)");

    } else {
	$val = round($val, ${'PRECI' . $metnum});
	if (isset($argv[$argvOption]) && $argv[$argvOption] == '--plain') $str = utf8_decode("$val");
	else $str = utf8_decode("$ID($val*W)");
    }
    echo "$str\n";

} else {
    die("$argv[0] $version\nUsage: $argv[0] {virtmeternum} {meternumlisttoadd|0} {meternumlisttosub|0}{energy|power|cost} {F1|F2|F3|F23|0} [--plain]\n");
}
?>
