<?php
// Credit Louviaux Jean-Marc 2015
include('loadcfg.php');
while (true) { // To infinity ... and beyond!
    for ($metnum = 1; $metnum <= $NUMMETER; $metnum++) { // Meters/Sensors pooling
        ///// Main memory
        $shmid = shmop_open($MEMORY, 'a', 0, 0);
        $size  = shmop_size($shmid);
        shmop_close($shmid);
        
        $shmid        = shmop_open($MEMORY, 'c', 0644, $size);
        $memarraydata = shmop_read($shmid, 0, $size);
        shmop_close($shmid);
        $memarray = json_decode($memarraydata, true);
        
        ///// Live memory
        $shmid = shmop_open($LIVEMEMORY, 'a', 0, 0);
        if (!empty($shmid)) {
        $size  = shmop_size($shmid);
        shmop_close($shmid);
        
        $shmid = shmop_open($LIVEMEMORY, 'c', 0644, $size);
        $data  = shmop_read($shmid, 0, $size);
        shmop_close($shmid);
        $livememarray = json_decode($data, true);
        }
        /////
        
        if (!${'SKIPMONITORING' . $metnum} && ${'LIVEPOOL' . $metnum} != 0) {
            $val = null;
            exec(${'LIVECOMMAND' . $metnum}, $datareturn);
            $datareturn = trim(implode($datareturn));
            $val        = isvalid(${'LID' . $metnum}, $datareturn);
            if (isset($val)) {
                $livememarray['UTC'] = strtotime(date('Ymd H:i:s'));
            } else {
                $val                 = 0;
                $livememarray['UTC'] = '0';
                if ($LOGCOM) {
                    $now        = date($DATEFORMAT . ' H:i:s');
                    $stringData = "$now\tCommunication error with #$metnum\n\n";
                    $stringData .= file_get_contents($DATADIR . '/events.txt');
                    file_put_contents($DATADIR . '/events.txt', $stringData);
                }
            }
        } else {
            $val                 = 0;
            $livememarray['UTC'] = strtotime(date('Ymd H:i:s'));
        }
        
        $livememarray["${'METNAME'.$metnum}$metnum"] = floatval($val); // Live value or state
        
        $minute   = date('i');
        $fiveflag = (bool) $memarray['5minflag'];
        
        // Reading bootstrap (from csv)
        if (!isset($memarray['Totalcounter1']) && $NUMMETER>1) {
	    $output = array();
	    $output = glob($DATADIR . '/csv/*.csv');
	    sort($output);
	    $cnt = count($output);

	    if ($cnt > 0 && file_exists($output[$cnt - 1])) {
	        $file       = file($output[$cnt - 1]); // today
	        $contalines = count($file);

	        if ($contalines > 1) {
	            $prevarray = preg_split("/,/", $file[$contalines - 1]);
	        } elseif ($contalines == 1 && file_exists($output[$cnt - 2])) { // yesterday, only header
	            $file       = file($output[$cnt - 2]);
	            $contalines = count($file);
                    $prevarray = preg_split("/,/", $file[$contalines - 1]);
	        }

                for ($i = 1; $i < count($prevarray); $i++) { // For each meter
                    if (${'TYPE' . $i} != 'Sensor') {
		        $memarray["Totalcounter$i"] = $prevarray[$i];
		    }
	        }
	    }
	}

        if (in_array($minute, $minlist) && !$memarray['5minflag']) { // 5 min jobs
            $memarray['5minflag'] = true;
            $today                = date('Ymd');
            
            for ($i = 1; $i <= $NUMMETER; $i++) { // For each meters
                $datareturn = null;
                $giveup     = 0;
                $lastval    = null;
                
                if (!${'SKIPMONITORING' . $i}) {
                    while (!isset($lastval) && $giveup < 3) { // Try 3 times
                        exec(${'COMMAND' . $i}, $datareturn);
                        $datareturn = trim(implode($datareturn));
                        $lastval    = isvalid(${'ID' . $i}, $datareturn);
                        // echo "metnum=$i, datareturn=$datareturn, lastval=$lastval\n"; 
                        $giveup++;
                    }
                    if ((${'NORESPM' . $i}) && !isset($lastval) && !${'comlost' . $i}) {
                        ${'comlost' . $i} = true;
                        $now              = date($DATEFORMAT . ' H:i:s');
                        $stringData       = "$now\tConnection lost with #$i\n\n";
                        
                        if (!empty(${'EMAIL' . $i})) {
                            mail("${'EMAIL'.$i}", "meterN: ${'METNAME'.$i} Connection lost", $msg, "From: meterN <${'EMAIL'.$i}>");
                        }
                        if (${'PUSHO' . $i} == 1) {
                            $pushover = exec('curl -s -F "title=' . ${'METNAME' . $i} . ' Warning" -F "token=BCZigaCQktVT4zR1xmpZ3iXsmkbm59" -F "user=' . ${'POUKEY' . $i} . '" -F "message=' . $stringData . '" https://api.pushover.net/1/messages.json &');
                        }
                        $stringData .= file_get_contents($DATADIR . '/events.txt');
                        file_put_contents($DATADIR . '/events.txt', $stringData);
                    }
                
                    // Compute diff values (you can get prev values using TotalcounterX - DiffcounterX )
                    if (isset($lastval, $memarray["Totalcounter$i"])) {
      		        $memarray["Diffcounter$i"] = $lastval - $memarray["Totalcounter$i"];  
      		    }
                }
                
                if ($i == 1) {
                    $PCtime      = date('H:i');
                    $stringData5 = "$PCtime";
                }
                $stringData5 .= ",$lastval";
                if (${'TYPE' . $i} != 'Sensor') {
                    $memarray["Totalcounter$i"] = $lastval;

	            $data = json_encode($memarray);
	            if ($data != $memarraydata) { // Reduce write
	                $size  = mb_strlen($data, 'UTF-8');
	                $shmid = shmop_open($MEMORY, 'a', 0, 0);
	                shmop_delete($shmid);
	                shmop_close($shmid);

	                $shmid = shmop_open($MEMORY, 'c', 0644, $size);
	                shmop_write($shmid, $data, 0);
	                shmop_close($shmid);
	            }
                }
            } // For each meters
            
            $stringData5 .= "\r\n";
            
            if (file_exists($DATADIR . "/csv/$today.csv")) {
                file_put_contents($DATADIR . "/csv/$today.csv", $stringData5, FILE_APPEND);
            } else { // Midnight or startup
                $yesterday = date('Ymd', time() - (60 * 60 * 24) + 30); // yesterday
                if ($PCtime == '00:00' && file_exists($DATADIR . "/csv/$yesterday.csv")) {
                    file_put_contents($DATADIR . "/csv/$yesterday.csv", $stringData5, FILE_APPEND);
                }
                
                $stringData = "Time"; // Header line
                for ($i = 1; $i <= $NUMMETER; $i++) {
                    $stringData .= ",${'METNAME'.$i}(${'UNIT'.$i})";
                    if (${'TYPE' . $i} != 'Sensor') {
                        $memarray["Midnight$i"] = $memarray["Totalcounter$i"];
                        ${'comlost' . $i}       = false;
                    }
                }
                $stringData .= "\r\n";
                $stringData .= "$stringData5";
                file_put_contents($DATADIR . "/csv/$today.csv", $stringData, FILE_APPEND);
                
                $csvlist = glob($DATADIR . '/csv/*.csv');
                sort($csvlist);
                $xdays = count($csvlist);
                
                if ($xdays > 1) { // previous day
                    $lines      = file($csvlist[$xdays - 2]);
                    $contalines = count($lines);
                    $csvdate1   = substr($csvlist[$xdays - 2], -12, 8);
                    $year       = (int) substr($csvlist[$xdays - 2], -12, 4); // For new year
                    for ($i = 1; $i <= $NUMMETER; $i++) {
                        $memarray["Mailflag$i"] = false; // clear Mailflag
                        if (${'TYPE' . $i} != 'Sensor') {
                            $val_last  = null;
                            $val_first = null;
                            $j         = 0;
                            while (!isset($val_first)) {
                                $j++;
                                $array     = preg_split('/,/', $lines[$j]);
                                $val_first = isset($array[$i]) ? trim($array[$i]) : '';
                                if ($val_first == '') { // if skipped
                                    $val_first = null;
                                }
                                if ($j == $contalines - 1) {
                                    $val_first = 0; // didn't find any prev. first value
                                }
                            }
                            $j = 0;
                            while (!isset($val_last)) {
                                $j++;
                                $array    = preg_split('/,/', $lines[$contalines - $j]);
                                $val_last = isset($array[$i]) ? trim($array[$i]) : '';
                                if ($val_last == '') {
                                    $val_last = null;
                                }
                                if ($j == $contalines - 1) {
                                    $val_last = 0;
                                }
                            }
                            settype($val_last, 'float');
                            settype($val_first, 'float');
                            
                            if ($val_first <= $val_last) {
                                $val_last -= $val_first;
                            } else { // counter pass over
                                $val_last += ${'PASSO' . $i} - $val_first;
                            }
                            $val_last   = round($val_last, ${'PRECI' . $i});
                            $stringData = "$csvdate1";
                            $stringData .= ",$val_last\r\n";
                            
                            file_put_contents($DATADIR . '/meters/' . $i . ${'METNAME' . $i} . $year . '.csv', $stringData, FILE_APPEND);
                        }
                        // Reports
                        if (${'REPORT' . $i} == 'daily') { // Daily
                            $month = (int) substr($csvdate1, 4, 2);
                            $day   = (int) substr($csvdate1, 6, 2);
                            $adate = date($DATEFORMAT, mktime(0, 0, 0, $month, $day, $year));
                            $msg   = "${'METNAME'.$i} $val_last ${'UNIT'.$i}\r\n";
                            if (!empty(${'EMAIL' . $i})) {
                                mail("${'EMAIL'.$i}", "meterN: $adate report", $msg, "From: meterN <${'EMAIL'.$i}>");
                            }
                        }
                        $adate = date('d');
                        if ($adate == '01' && ${'REPORT' . $i} == 'monthly') { // Monthly
                            $y_year      = date('Y', time() - 60 * 60 * 24); // yesterday
                            $y_month     = date('m', time() - 60 * 60 * 24);
                            $mlines      = file($DATADIR . '/meters/' . $i . ${'METNAME' . $i} . $y_year . '.csv');
                            $mcontalines = count($mlines);
                            $j           = 0;
                            for ($line_num = 0; $line_num < $mcontalines; $line_num++) {
                                $array = preg_split('/,/', $mlines[$line_num]);
                                $month = substr($array[0], 4, 2);
                                if ($month == $y_month) {
                                    $month         = substr($array[0], 4, 2);
                                    $day           = substr($array[0], 6, 2);
                                    $dayname[$j]   = date($DATEFORMAT, mktime(0, 0, 0, $month, $day, $y_year));
                                    $conso_day[$j] = $array[1];
                                    $conso_day[$j] = round($conso_day[$j], ${'PRECI' . $i});
                                    $j++;
                                }
                            }
                            $conso_month[$i] = round(array_sum($conso_day), ${'PRECI' . $i});
                            $cnt             = count($dayname);
                            $msg             = "${'METNAME'.$i}\t (${'UNIT'.$i})\r\n";
                            for ($j = 0; $j < $cnt; $j++) {
                                $msg .= "$dayname[$j]\t";
                                $msg .= "$conso_day[$j]\r\n";
                            }
                            $msg .= "\r\n";
                            $msg .= "$conso_month[$i] ${'UNIT'.$i} on $y_month $y_year\r\n---\r\n";
                            if (!empty(${'EMAIL' . $i})) {
                                mail("${'EMAIL'.$i}", "meterN: ${'METNAME'.$i} Monthly $y_month report", $msg, "From: meterN <${'EMAIL'.$i}>");
                            }
                        }
                        // Reports
                    }
                } // previous day
                // Morning cleanup
                if ($KEEPDDAYS != 0) {
                    if ($xdays > $KEEPDDAYS) {
                        $i = 0;
                        while ($i < $xdays - $KEEPDDAYS) {
                            unlink($csvlist[$i]);
                            $i++;
                        }
                        $adate      = date($DATEFORMAT . ' H:i:s');
                        $stringData = "$adate\tPurging $i detailed csv file(s)\n\n";
                        $stringData .= file_get_contents($DATADIR . '/events.txt');
                        file_put_contents($DATADIR . '/events.txt', $stringData);
                    }
                }
                if ($AMOUNTLOG != 0) {
                    $lines = file('../data/events.txt');
                    $cnt   = count($lines);
                    if ($cnt >= $AMOUNTLOG) {
                        $adate = date($DATEFORMAT . ' H:i:s');
                        array_splice($lines, $AMOUNTLOG);
                        $file2      = fopen('../data/events.txt', 'w');
                        $new_lignes = "$adate\tClean up events log\n\n";
                        fwrite($file2, $new_lignes);
                        fwrite($file2, implode('', $lines));
                        fclose($file2);
                    }
                }
                // End of morning clean up 
            } // End previous day
            
            $lines      = file($DATADIR . "/csv/$today.csv");
            $contalines = count($lines);
            if ($contalines > 2) { // Consumption/production sensor check
                for ($i = 1; $i <= $NUMMETER; $i++) {
                    $mailflag = (bool) $memarray["Mailflag$i"];
                    if ($mailflag == false && !${'SKIPMONITORING' . $i}) {
                        $array     = preg_split('/,/', $lines[1]);
                        $val_first = array_key_exists($i, $array) ? trim($array[$i]) : 0;
                        $array     = preg_split('/,/', $lines[$contalines - 1]);
                        $val_last  = trim($array[$i]);
                        settype($val_first, 'float');
                        settype($val_last, 'float');
                        
                        if (!empty($val_first) && !empty($val_last) && ${'TYPE' . $i} != 'Sensor') { // Meter
                            if ($val_first <= $val_last) {
                                $val_last -= $val_first;
                            } else { // counter pass over
                                $val_last += ${'PASSO' . $i} - $val_first;
                            }
                        }
                        
                        if ($val_last > ${'WARNCONSOD' . $i} && ${'WARNCONSOD' . $i} != 0 && !${'SKIPMONITORING' . $i}) {
                            
                            $memarray["Mailflag$i"] = true;
                            
                            if (${'PROD' . $i} == 1) {
                                $prodconsu = 'production';
                            } elseif (${'PROD' . $i} == 2) {
                                $prodconsu = 'consumption';
                            } else {
                                $prodconsu = null;
                            }
                            $adate      = date($DATEFORMAT . ' H:i:s');
                            $msg        = "$prodconsu reach $val_last ${'UNIT'.$i}";
                            $stringData = "$adate\t${'METNAME'.$i} $prodconsu reach $val_last ${'UNIT'.$i}\n\n";
                            if (!empty(${'EMAIL' . $i})) {
                                mail("${'EMAIL'.$i}", "meterN: ${'METNAME'.$i} $prodconsu warning", $msg, "From: meterN <${'EMAIL'.$i}>");
                            }
                            if (${'PUSHO' . $i} == 1) {
                                $pushover = exec('curl -s -F "title=' . ${'METNAME' . $i} . ' Warning" -F "token=BCZigaCQktVT4zR1xmpZ3iXsmkbm59" -F "user=' . ${'POUKEY' . $i} . '" -F "message=' . $msg . '" https://api.pushover.net/1/messages.json &');
                            }
                            $stringData .= file_get_contents('../data/events.txt');
                            file_put_contents('../data/events.txt', $stringData);
                        }
                    }
                }
            } // Consumption/prod check
        } // 5 min 
        
        if (!in_array($minute, $minlist) && $memarray['5minflag']) { // Run once every 1,6,11,16,..
            $memarray['5minflag'] = false; // Reset 5minflag
        }
        
        $data = json_encode($memarray);
        if ($data != $memarraydata) { // Reduce write
            $size  = mb_strlen($data, 'UTF-8');
            $shmid = shmop_open($MEMORY, 'a', 0, 0);
            shmop_delete($shmid);
            shmop_close($shmid);
            
            $shmid = shmop_open($MEMORY, 'c', 0644, $size);
            shmop_write($shmid, $data, 0);
            shmop_close($shmid);
        }
        
        $data = json_encode($livememarray);
        $size = mb_strlen($data, 'UTF-8');
        
        @$shmid = shmop_open($LIVEMEMORY, 'a', 0, 0);
        if (!empty($shmid)) {
            shmop_delete($shmid);
            shmop_close($shmid);
        }
        
        $shmid = shmop_open($LIVEMEMORY, 'c', 0644, $size);
        shmop_write($shmid, $data, 0);
        shmop_close($shmid);
    } // End meters pooling
    
    if ($NUMIND > 0) { // Indicators
        ///// Indicator memory
        $shmid = shmop_open($ILIVEMEMORY, 'a', 0, 0);
        $size  = shmop_size($shmid);
        shmop_close($shmid);
        
        $shmid = shmop_open($ILIVEMEMORY, 'c', 0644, $size);
        $data  = shmop_read($shmid, 0, $size);
        shmop_close($shmid);
        $livememarray = json_decode($data, true);
        
        for ($i = 1; $i <= $NUMIND; $i++) {
            if (${'INDPOOL' . $i} != 0) {
                $datareturn = exec(${'INDCOMMAND' . $i});
                $datareturn = trim($datareturn);
                $val        = isvalid(${'INDID' . $i}, $datareturn);
                if (!isset($val)) {
                    $val = 0;
                }
            } else {
                $val = 0;
            }
            $livememarray["${'INDNAME'.$i}$i"] = floatval($val); // Live value or state
        }
        
        $data = json_encode($livememarray);
        $size = mb_strlen($data, 'UTF-8');
        
        @$shmid = shmop_open($ILIVEMEMORY, 'a', 0, 0);
        if (!empty($shmid)) {
            shmop_delete($shmid);
            shmop_close($shmid);
        }
        
        $shmid = shmop_open($ILIVEMEMORY, 'c', 0644, $size);
        shmop_write($shmid, $data, 0);
        shmop_close($shmid);
    } // end of indicator
    usleep($DELAY);
} // infinity
?>
