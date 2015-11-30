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

### 1. Aggiornamento file metern.php

Utilizzare il percorso adeguato per la versione di metern installata, es. per
la versione 0.8:

https://raw.githubusercontent.com/The-Drake/virtmet/master/metern/0.8/scripts/metern.php

```
$ cd [<directoryweb>/]metern/scripts
$ mv metern.php metern.php.old
$ wget https://raw.githubusercontent.com/The-Drake/virtmet/master/metern/0.8/scripts/metern.php
```

### 2. Installazione script virtmet

```
$ cd [<directoryweb>/]metern/comapps
$ wget https://raw.githubusercontent.com/The-Drake/virtmet/master/virtmet-F123.php
$ sudo chmod +x virtmet-F123.php
$ sudo ln -s /<percorsowww>/metern/comapps/virtmet-F123.php /usr/local/bin/virtmet-F123
```

### 3. Configurazione calcolo costi in bolletta

Modificare il file virtmet-F123.php: nella variabile *$POTENZA*, impostare i Kw
garantiti dall'impianto:

```
// --------- COST PARAMETERS ---------------------------------------------------
$POTENZA = 6; // KW
```

E' possibile impostare i costi delle componenti della bolletta modificando lo script, ma non è consigliato. Il file di tariffe esterno, essendo dotato di data di validità è in grado di passare da un set di costi all'altro in automatico. 

```
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
```

Per garantire una continuità nel calcolo delle tariffe, utilizzare il file esterno della tariffa adatta al proprio contratto ed aggiornarlo in base alle disposizioni dell'AAEG. Questo file può contenere i dati di tutti i trimestri pubblicati dall'AAEG impostando nel primo campo la data di validità DVAL (primo giorno di inizio validità del set di costi).

Alcune tariffe precompilate sono o saranno disponibili qui: 
https://github.com/The-Drake/virtmet/tree/master/tariffe

```
// ---- $fileTariffa format ------
// DVAL,QS,QP,F1PE,F23PE,S1,S1PE,S2,S2PE,S3,S3PE,S4,S4PE,ACCISA,IVA
// 20151001,44.5340,16.4109,0.06730,0.06186,1800,0.127502,2640,0.144632,4440,0.184452,99999999,0.227122,0.0227,0.10
// ...
//--------------------------------
```
Nell'esempio viene utilizzata la tariffa di default (D3)

### 4. Configurazioni meter virtuale per il consumo secondo fascia (F1,F2,F3 o F23) in metern

La configurazione viene fatta collegandosi alla pagina di amministrazine di  metern:

http://ipmetern/metern/admin.php

Nell'esempio che segue, il meter vituale 13 prenderà il valore dell'energia dal meter 11 e accumulerà i dati solo per la fascia F1.

#### Main configuration
* Aumentare il numero di meter di 1 poter aggiungere e configurare il nuovo meter virtuale
* save config
* back

#### Configure meter(s) and sensor(s) logger(s)
* Selezionare il nuovo meter (13) in alto nella pagina
* Impostare:
 * Name (es. Prelievi F1)
 * Meterid (es. F1)
 * Command "virtmet-F123 13 11 0 energy F1"
 * Unit (Wh)
 * Precision (2)
 * Live Pooling ID (F1)
 * Live command "virtmet-F123 13 11 0 power F1"
 * Unit W  

### 5. Configurazioni meter virtuale per calcolo del costo in bolletta secondo fascia (F1,F2,F3 o F23)

La configurazione viene fatta collegandosi alla pagina di amministrazine di  metern:

http://ipmetern/metern/admin.php

Nell'esempio che segue, il meter vituale 16 prenderà il valore dell'energia dal meter 11 e calcolerà il costo secondo la fascia corrente per un conteggio totale della bolletta.

#### Main configuration
* Aumentare il numero di meter di 1 poter aggiungere e configurare il nuovo meter virtuale
* save config
* back

#### Configure meter(s) and sensor(s) logger(s)
* Selezionare il nuovo meter 16 in alto nella pagina
* Impostare:
 * Name (es. Bolletta)
 * Meterid (es. BLT)
 * Command "virtmet-F123 16 11 0 cost 0"
 * Unit (Euro)
 * Precision (6) - Attenzione utilizzare un numero di decimali sufficienti perchè il costo di soli 5 minuti di consumi può essere un numero decimale molto piccolo.
 
.. to be continued

/drk