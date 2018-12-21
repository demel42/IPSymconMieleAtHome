# IPSymconMieleAtHome

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.0-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.2-blue.svg)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/126683101/shield?branch=master)](https://github.styleci.io/repos/xxx)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Unter dem Begriff _Miele@Home_ wird von Miele die Vernetzung von entsprechend ausgestatteten Haushaltsgeräten angeboten. Zur Vernetzung dienen verscheidenen Protokolle (WLAN, Z-Wave, ...) die dann, ggfs. über einen Gateway, mit einer Cloud von Miele kommunizieren,
Über diese Clound kann der Benutzer mit der entsprechenden App von Miele den Status der Geräte kontrollieren und in gewissem Umfang steuern.

Unter dem Begriff _Miele@Home-third-party-API_ bietet Mielen einen Zugriff auf diesen Daten an. Zur Zeit (in der Version 1.0) sind nur lesende Zugriffe möglich.

Mit diesem Modul können diese Daten, passend zu den Gerätetypen, in IP⁻Symcon visualisiert werden…

## 2. Voraussetzungen

 - IP-Symcon ab Version 5
 - Miele@Home-Account
 - Zugangsdaten zu Miele@Home-third-party-API

## 3. Installation

### a. Laden des Moduls

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconMieleAtHome.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Miele-Cloud

Es wird ein Account bei _Miele@Home_ benötigt, das macht man am einfachsten über die App oder legt direkt bei Miele ein Benutzerkonto an.
Für den Zugriff der auf die _Miele@Home-third-party-API_ benötigt man zusätzliche Zugriffschlüssel; diese bekommn man, indem man eine (formlose) Mail an _developer@miele.com_ schickt (siehe auch _https://www.miele.com/developer_).

### c. Einrichtung I/O-Moduls

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Miele_ und als Gerät _Miele@Home I/O_ auswählen.
Die geforderten Daten eintragen - wichtig, das Feld _VG-Auswahl_ muss anscheinend dem Land entsprechen, in dem das Gerät betreiebn wird. Dann den Zugriff mit _Zugang prüfen_ testen.

### d. Einrichtung des Konfigurator-Moduls

In IP-Symcon nun unterhalb von _Konfigurator Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Miele_ und als Gerät _Miele@Home Konfigurator_ auswählen.
Dann in den Auswahlbox das gewünschte Gerät auswählen und _Gerät anlegen_ betätigen.
Der Versuch, ein bereits eingerichtetes Gerätes erneut anzulegen, führ nicht zu einer weitren Geräte-Instanz…

### e. Einrichtung des Geräte-Moduls

Eine manuelle Einrichtung eines Geräemoduls ist nicht erforderlich, das erfolgt über den Konfigurator.
In dem Geräte-Modul ist ggfs nur das Abfrage-Intervall anzupassen, die anderen Felder, insbesondere die _Fabrikationsnummer_ (diese ist die Identifikation des Gerätes) und die _Geräte-Typ-ID_ (dies steuert, welche Variablen angelegt werden) müssen unverändert bleiben.

Wichtig: da keine vollständige Dokumentation vorliegt, welche Geräte es gibt bzw. welche Geräte sinnvollerweise welche Variablen füllen, ist die Liste der unterstützten Geräte unvollständig und muss dann im Bedarfsfall erweitert werden.

## 4. Funktionsreferenz

## 5. Konfiguration

### a. I/O-Modul

#### Variablen

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :----------: |
| Benutzer (EMail)          | string   |              | Miele@Home-Konto: Benutzerkennung |
| Passwort                  | string   |              | Miele@Home-Konto: Passwort |
| Client-ID                 | string   |              | Miele@Home API-Zugangsdaten: Client-ID |
| Client-Secret             | string   |              | Miele@Home API-Zugangsdaten: Client-Secret |
| VG-Selector               | string   |              | Bedenutung unklar, muss anscheinend auf dem Wert des Landes stehen, wo das Gerät gekauft/betrieben wird |
| Sprache                   | string   |              | Sprache von Text-Ausgaben der API |

#### Schaltflächen

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :----------: |
| Zugang prüfen                | Prüft, ob die Angabe korrekt sind |

Test access

### b. Konfigurator-Modul

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :----------: |
| Gerät                     |          |              | Auswahlliste der Geräte des angegebenen Miele-Kontos |

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :----------: |
| Gerät anlegen                | Erzeugt/Aktualisiert die Geräte-Instanz des ausgewählte Gerätes |

### c. Geräte-Modul

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :-----------------------: | :-----:  | :----------: | :----------: |
| Geräete-Typ-ID            | integer  |              | wird im Konfigurator gesetzt und darf nicht geändert werden, bestimmte die auszugebenden Felder |
| Geräte-Typ                | string   |              | wird im Konfigurator gesetzt |
| Fabrikationsnummer        | string   |              | wird im Konfigurator gesetzt und darf nicht geändert werden |
| Modell                    | string   |              | wird im Konfigurator gesetzt |
| Update-Intervall          | integer  | 60           | Intervall der Datenabfrage in Sekunden |
|                           |          |              | |
| Code in Text übersetzen   |          |              | Übersetzte Statustexte aus der API ignoreiren und selbst umsetzen |
|  ... Status               | boolean  | false        | |
|  ... Programm             | boolean  | false        | |
|  ... Phase                | boolean  | false        | |
|  ... Trockenstufe         | boolean  | false        | |

| Bezeichnung                  | Beschreibung |
| :--------------------------: | :----------: |
| Daten aktualisieren          | Abfrage der aktuellen Daten |


Bisher unterstützte Gerätetypen

| Geräete-Typ-ID | Geräte-Typ |
| :------------: | :-: |
| 1 | Washmaschine |
| 2 | Wäschetrockner |
| 7 | Geschirrspüler |
| 12 | Backofen |
| 13 | Backofen mit Mikrowelle |
| 21 | Kühl/Gefrier-Kombination |

### Variablenprofile

* Boolean<br>
MieleAtHome.Door

* Integer<br>
MieleAtHome.Duration, MieleAtHome.Temperature, MieleAtHome.SpinningSpeed

## 6. Anhang

GUIDs

- Modul: `{122A97CE-7642-4D77-B656-242B3E08AEA9}`
- Instanzen:
  - MielelAtHomeIO: `{996743FB-1712-47A3-9174-858A08A13523}`
  - MielelAtHomeConfig: `{1381CC46-77BF-4EA7-B954-85A0FDD28997}`
  - MielelAtHomeDevice: `{C2672DE6-E854-40C0-86E0-DE1B6B4C3CAB}`
- Nachrichten:
	- `{D39AEB86-E611-4752-81C7-DBF7E41E79E1}`: an MieleAtHomeConfig, MieleAtHomeDevice
	- `{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}`: an MieleAtHomeIO

Verweise:
- https://www.miele.com/developer/index.html

## 7. Versions-Historie

- 1.2 @ 21.12.2018 13:10<br>
  - Standard-Konstanten verwenden

- 1.1 @ 27.11.2018 17:03<br>
  - optional die Statuscodes selbst übersetzen

- 1.0 @ 04.11.2018 10:49<br>
  Initiale Version
