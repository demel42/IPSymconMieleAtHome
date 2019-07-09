# IPSymconINSTAR
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)](https://www.symcon.de/forum/threads/38222-IP-Symcon-5-0-verf%C3%BCgbar)


Modul für IP-Symcon ab Version 5.x. Ermöglicht die Kommunikation mit einer [INSTAR](https://www.instar.de/ "INSTAR") Kamera.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)  
6. [Anhang](#6-anhang)  

## 1. Funktionsumfang

Mit dem Modul lassen sich Befehle an eine [INSTAR](https://www.instar.de/ "INSTAR") Kamera aus IP-Symcon senden und die Events einer [INSTAR](https://www.instar.de/ "INSTAR") Kamera in IP-Symcon (ab Version 5) empfangen. 

### Befehle an INSTAR senden:  

 - Steuerung ( hoch, runter, links, rechts, stop) 
 - Position setzten und anfahren
 - Kamera Einstellungen (Kontrast, Helligkeit, Farbe)

### Status Rückmeldung:  

 - Bild Anzeige
 - Benachrichtung von [INSTAR](https://www.instar.de/ "INSTAR") an IP-Symcon bei einem Event
 - Email Benachrichtigung bei Event	
  

## 2. Voraussetzungen

 - IP-Symcon 5.x
 - [INSTAR](https://www.instar.de/ "INSTAR") Kamera
 - der Master Branch ist für die aktuelle IP-Symcon Version ausgelegt.
 - bei IP-Symcon Versionen kleiner 5.1 ist der Branch _5.0_ zu wählen

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://<IP-Symcon IP>:3777/console/_ öffnen. 


Anschließend oben rechts auf das Symbol für den Modulstore (IP-Symcon > 5.1) klicken

![Store](img/store_icon.png?raw=true "open store")

Im Suchfeld nun

```
INSTAR
```  

eingeben

![Store](img/module_store_search.png?raw=true "module search")

und schließend das Modul auswählen und auf _Installieren_

![Store](img/install.png?raw=true "install")

drücken.


#### Alternatives Installieren über Modules Instanz (IP-Symcon < 5.1)

Die Webconsole von IP-Symcon mit _http://<IP-Symcon IP>:3777/console/_ öffnen. 

Anschließend den Objektbaum _Öffnen_.

![Objektbaum](img/objektbaum.png?raw=true "Objektbaum")	

Die Instanz _'Modules'_ unterhalb von Kerninstanzen im Objektbaum von IP-Symcon (>=Ver. 5.x) mit einem Doppelklick öffnen und das  _Plus_ Zeichen drücken.

![Modules](img/Modules.png?raw=true "Modules")	

![Plus](img/plus.png?raw=true "Plus")	

![ModulURL](img/add_module.png?raw=true "Add Module")
 
Im Feld die folgende URL eintragen und mit _OK_ bestätigen:

```
https://github.com/Wolbolar/IPSymconInstar
```  
	        
Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_    

Es wird im Standard der Zweig (Branch) _master_ geladen, dieser enthält aktuelle Änderungen und Anpassungen.
Nur der Zweig _master_ wird aktuell gehalten.

![Master](img/master.png?raw=true "master") 

Sollte eine ältere Version von IP-Symcon die kleiner ist als Version 5.1 eingesetzt werden, ist auf das Zahnrad rechts in der Liste zu klicken.
Es öffnet sich ein weiteres Fenster,

![SelectBranch](img/select_branch.png?raw=true "select branch") 

hier kann man auf einen anderen Zweig wechseln, für ältere Versionen kleiner als 5.1 ist hier
_Old-Version_ auszuwählen. 

### b. Einrichtung in IPS


Bevor die eigentliche Instanz angelegt wird, muss eine Kategorien an einer gewünschten Stelle im Objektbaum angelegt werden.
In diese Kategorie werden dann später vom Modul bei einem Event oder bei einer Bewegungserkennung oder manueller Aufforderung,
jeweils ein Bild zum Zeitpunkt des Events abgelegt.
Wir legen also eine Kategorie an der gewünschten Position im Objektbaum an (_Rechtsklick -> Objekt hinzufügen -> Kategorie_) und benennen diese z.B. mit
den Namen _Besucherhistorie_ .
	
In IP-Symcon nun _Instanz hinzufügen_ (_Rechtsklick -> Objekt hinzufügen -> Instanz_) auswählen unter der Kategorie, unter der man die INSTAR Kamera hinzufügen will,
und _INSTAR_ auswählen.

![SelectInstance](img/instanz.png?raw=true "select instance") 
 
Im Konfigurationsformular ist zunächst das passende Kamera Modell von INSTAR auszuwählen.


## 4. Funktionsreferenz

### INSTAR:

Die IP Adresse der INSTAR Kamera sowie Username sowie Passwort von INSTAR sind anzugeben.
Es wird bei jedem Event eine Mitteilung an IP-Symcon gesendet.
Mit Hilfe eines Ereignisses was bei Variablenaktualisierung greift können dann in IP-Symcon weitere Aktionen
ausgelöst werden. Das Livebild kann in IP-Symcon eingesehen werden sowie die Historie der letzten Bilder.

#### Anwendungsbeispiele

##### Auslösen eines Alarms durch ein Ereigniss (nur Full HD Modelle)

##### Aktivieren der Nachtsicht abhängig von einem externen Sensor

##### Zeitgesteuertes Anfahren einer Position mit einem Wochenplan

##### Zeitgesteuertes aktivieren der Bewegungserkennungsbereiche

##### Anpassen der Empfindlichkeit der Bewegungserkennung abhängig von Tag oder Nacht 
	
##### Zeitgesteuertes Einstellen der Alarmbereiche

##### Definierte Position bei einem Ereignis anfahren

##### Länge eines Aufnahmevideos bestimmen

##### Suchen einer Lärmquelle bei Audioerkennung



## 5. Konfiguration:

### INSTAR:

| Eigenschaft | Typ     | Standardwert | Funktion                                  |
| :---------: | :-----: | :----------: | :---------------------------------------: |
| IPSIP       | string  |              | IP Adresse IP-Symcon                      |
| Host        | string  |              | IP Adresse INSTAR                         |
| User        | string  |              | INSTAR User                               |
| Password    | string  |              | INSTAR Passwort                           |
| picturelimit| integer |    20        | Limit an abgelegten Bildern               |






## 6. Anhang

###  a. Funktionen:

#### INSTAR:

`INSTAR_GetInfo(integer $InstanceID)`

Information der INSTAR Kamera auslesen

`INSTAR_Right(integer $InstanceID)`

Rechtsbewegung der Kamera

`INSTAR_Left(integer $InstanceID)`

Linkssbewegung der Kamera

`INSTAR_Up(integer $InstanceID)`

Hochbewegung der Kamera

`INSTAR_Down(integer $InstanceID)`

Runterbewegung der Kamera

`INSTAR_Stop(integer $InstanceID)`

Stoppt die Bewegung der Kamera

   



###  b. GUIDs und Datenaustausch:

#### INSTAR:

GUID: `{3E0686DD-A9FC-308D-35ED-71E251F5F7FB}` 