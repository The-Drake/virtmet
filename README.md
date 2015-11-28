# virtmet
A Virtual Meter Comapp script for metern
https://github.com/The-Drake/virtmet

## Introduzione

Lo script permette di creare virtual meter (contatori virtuali) per metern basandosi su altri meter (fisici o virtuali). E' pensato per calcolare valori di potenza e di consumo (power, energy) e costi (cost) a partire da uno o più dei meter preesistenti nella configurazione di metern.

Ad esempio:
* può calcolare il consumo totale di un edificio servito da due o più contatori distinti
* può calcolare il consumo di una parte degli apparati per differenza di uno o più contatori (es. consumo domestico = consumo totale - consumo condizionatore)
* può calcolare il costo dei prelievi (anche calcolati con altri script tipo eflow)
* è in grado di conteggiare il consumo o produzione in base alle fasce orarie (F1, F2, F3, F23)
è in grado di calcolare il costo in bolletta a partire dai costi trimestrali in bolletta (es. D2 o D3)

## Prerequisiti

* metern 0.8 o superiore

## Installazione

1. Backup vecchia versione metern.php
2. Copiare la nuova versione del file metern/versione/metern.php in metern/scripts
3. Copiare il file virtmet-F123.php e il file tariffa.csv nella directory metern/comapps
4. chmod +x virtmet-F123.php
5. ln -s /percorsowww/metern/comapps /usr/local/bin/virtmet-F123
6. Se interessa il calcolo della bolletta, modificare nel file virtmet-F123.php i Kw dell'impianto
```
$head -n 40 virtmet-F123.php
#!/usr/bin/php
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
```
6. Se interessa il calcolo della bolletta, Verificare i costi di riferimento, eventualmente aggiornando il file tariffa.csv
```
$cat tariffa.csv
DVAL,QS,QP,F1PE,F23PE,S1,S1PE,S2,S2PE,S3,S3PE,S4,S4PE,ACCISA,IVA
20151001,44.5340,16.4109,0.06730,0.06186,1800,0.127502,2640,0.144632,4440,0.184452,99999999,0.227122,0.0227,0.10[/CODE]
```
7. Configurare il nuovo meter virtuale in metern

/drk