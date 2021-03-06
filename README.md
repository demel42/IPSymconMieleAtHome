# IPSymconMieleAtHome

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.3+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

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

Unter dem Begriff _Miele@Home_ wird von Miele die Vernetzung von entsprechend ausgestatteten Haushaltsgeräten angeboten. Zur Vernetzung dienen verschiedene Protokolle (WLAN, Z-Wave, ...) die dann, ggfs. über einen Gateway, mit der Cloud von Miele kommunizieren,
Über diese Cloud kann der Benutzer mit der entsprechenden App von Miele den Status der Geräte kontrollieren und in gewissem Umfang steuern.

Unter dem Begriff _Miele@Home-third-party-API_ bietet Miele einen Zugriff auf diesen Daten an. Neben dem Abruf von Daten ist auch eine gewisse Steuerung möglich.

Mit diesem Modul können diese Daten, passend zu den Gerätetypen, in IP-Symcon visualisiert werden und die vorhandenen Steuerungsmöglichkeiten genutzt werden.

## 2. Voraussetzungen

 - IP-Symcon ab Version 5.3
 - Miele@Home-Account
 - entweder IP-Symcon Connect oder Zugangsdaten zu Miele@Home-third-party-API

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://\<IP-Symcon IP\>:3777/console/_ öffnen.

Anschließend oben rechts auf das Symbol für den Modulstore (IP-Symcon > 5.1) klicken

![Store](docs/de/img/store_icon.png?raw=true "open store")

Im Suchfeld nun _Miele_ eingeben, das Modul auswählen und auf _Installieren_ drücken.

#### Alternatives Installieren über Modules Instanz (IP-Symcon < 5.1)

Die Webconsole von IP-Symcon mit _http://\<IP-Symcon IP\>:3777/console/_ aufrufen.

Anschließend den Objektbaum _öffnen_.

![Objektbaum](docs/de/img/objektbaum.png?raw=true "Objektbaum")

Die Instanz _Modules_ unterhalb von Kerninstanzen im Objektbaum von IP-Symcon mit einem Doppelklick öffnen und das  _Plus_ Zeichen drücken.

![Modules](docs/de/img/Modules.png?raw=true "Modules")

![Plus](docs/de/img/plus.png?raw=true "Plus")

![ModulURL](docs/de/img/add_module.png?raw=true "Add Module")

Im Feld die folgende URL eintragen und mit _OK_ bestätigen:

```
https://github.com/demel42/IPSymconMieleAtHome.git
```

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_.

Es wird im Standard der Zweig (Branch) _master_ geladen, dieser enthält aktuelle Änderungen und Anpassungen.
Nur der Zweig _master_ wird aktuell gehalten.

![Master](docs/de/img/master.png?raw=true "master")

Sollte eine ältere Version von IP-Symcon abs 5.0 eingesetzt werden, ist auf das Zahnrad rechts in der Liste zu klicken.
Es öffnet sich ein weiteres Fenster,

![SelectBranch](docs/de/img/select_branch.png?raw=true "select branch")

hier kann man auf einen anderen Zweig wechseln, für ältere Versionen sind entsprechende Zweige auszuwählen.

### b. Miele-Cloud

Es wird ein Account bei _Miele@Home_ benötigt, das macht man am einfachsten über die App oder legt direkt bei Miele ein Benutzerkonto an.

Um Zugriff auf die Miele@Home Daten zu bekommen, gibt es zwei Möglichkeiten.

#### Zugriff mit Miele@Home Benutzerdaten über IP-Symcon Connect

Hierzu wird ein aktives IP-Symcon Connect benötigt und den normalen Miele@Home Benutzernamen und Passwort.

#### Zugriff als Entwickler mit eigenem Entwicklerschlüssel

Für den Zugriff der auf die _Miele@Home-third-party-API_ benötigt man zusätzliche Zugriffschlüssel; diese bekommn man, indem man eine (formlose) Mail an _developer@miele.com_ schickt (siehe auch _https://www.miele.com/developer_).

### c. Einrichtung I/O-Moduls

In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Miele_ und als Gerät _Miele@Home I/O_ auswählen.

Im Konfigurationsformular nun den gewünschten Zugang wählen, entweder als Nutzer über IP-Symcon Connect oder als Entwickler mit eigenem Entwicklerschlüssel

#### Zugriff mit Miele@Home Benutzerdaten über IP-Symcon Connect

Hierzu auf _**Registrieren**_ drücken. Es öffnet sich ein Browserfenster mit der Anmeldeseite von Miele.

![OAUTH1](docs/de/img/oauth_1.png?raw=true "oauth 1")

Auf der Anmeldeseite von Miele wird der Miele Benutzername (E-Mail) und das Passwort eingetragen. Das passende Land ist auszuwählen.

nachdem die Authentifizierung von IP-Symcon bei Miele@Home erfolgreich war erscheint folgende Meldung.

![OAUTH2](docs/de/img/oauth_2.png?raw=true "oauth 2")

Weiterhin muss man die Erlaubnis zum Zugriff auf die Miele-Geräte erteilen.

![ZUGRIFF_ERTEILEN](docs/de/img/zugriff_erteilen.png?raw=true "zugriff erteilen")

Das Browser Fenster kann nun geschlossen werden und wieder zu IP-Symcon zurückgekeht werden.

#### Zugriff als Entwickler mit eigenem Entwicklerschlüssel

Die geforderten Daten eintragen - wichtig, das Feld _VG-Auswahl_ muss anscheinend dem Land entsprechen, in dem das Gerät betrieben wird. Dann den Zugriff mit _Zugang prüfen_ testen.

### d. Einrichtung des Konfigurator-Moduls

In IP-Symcon nun unterhalb von _Konfigurator Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Miele_ und als Gerät _Miele@Home Konfigurator_ auswählen.
In der Liste nun das zu erstellende Gerät auswählen, das man erstellen will. Der Konfigurator legt dann eine Geräte Instanz an.

### e. Einrichtung des Geräte-Moduls

Eine manuelle Einrichtung eines Gerätemoduls ist nicht erforderlich, das erfolgt über den Konfigurator.
In dem Geräte-Modul ist ggfs nur das Abfrage-Intervall anzupassen, die anderen Felder, insbesondere die _Seriennummer_ (diese ist die Identifikation des Gerätes) und die _Geräte-Typ-ID_ (diese steuert, welche Variablen angelegt werden) müssen unverändert bleiben.

Wichtig: da keine vollständige Dokumentation vorliegt, welche Geräte es gibt bzw. welche Geräte sinnvollerweise welche Variablen füllen, ist die Liste der unterstützten Geräte unvollständig und muss dann im Bedarfsfall erweitert werden.

## 4. Funktionsreferenz

siehe https://www.miele.com/developer/swagger-ui/put_additional_info.html

`boolean MieleAtHome_Start(integer $InstanzID)`<br>

`boolean MieleAtHome_Stop(integer $InstanzID)`<br>

`boolean MieleAtHome_Pause(integer $InstanzID)`<br>

`boolean MieleAtHome_StartSuperfreezing(integer $InstanzID)`<br>

`boolean MieleAtHome_StopSuperfreezing(integer $InstanzID)`<br>

`boolean MieleAtHome_StartSupercooling(integer $InstanzID)`<br>

`boolean MieleAtHome_StopSupercooling(integer $InstanzID)`<br>

`boolean MieleAtHome_LightEnable(integer $InstanzID)`<br>

`boolean MieleAtHome_LightDisable(integer $InstanzID)`<br>

`boolean MieleAtHome_PowerOn(integer $InstanzID)`<br>
Anmerkung: es ist unklar, bei welchen Gerätetypen es funktioniert, das Geräte aus dem ausgeschalteten Zustand einzuschalten.

`boolean MieleAtHome_PowerOff(integer $InstanzID)`<br>

`boolean MieleAtHome_SetStarttime(integer $InstanzID, int $hour, int $min)`<br>

## 5. Konfiguration

### a. I/O-Modul

#### Variablen

| Eigenschaft             | Typ     | Standardwert | Beschreibung |
| :---------------------- | :------ | :----------- | :----------- |
| Instanz ist deaktiviert | boolean | false        | Instanz temporär deaktivieren |
|                         |         |              | |
| Verbindugstyp           | integer | 0            | Auswahl der Art der Verbindung (**OAuth** oder **Developer**) |
|                         |         |              | |
| - nur bei _Developer_ - |         |              | |
| Benutzer (EMail)        | string  |              | Miele@Home-Konto: Benutzerkennung |
| Passwort                | string  |              | Miele@Home-Konto: Passwort |
| Client-ID               | string  |              | Miele@Home API-Zugangsdaten: Client-ID |
| Client-Secret           | string  |              | Miele@Home API-Zugangsdaten: Client-Secret |
| VG-Selector             | string  |              | Bedenutung unklar, muss anscheinend auf dem Wert des Landes stehen, wo das Gerät gekauft/betrieben wird |
| Sprache                 | string  |              | Sprache von Text-Ausgaben der API |

#### Schaltflächen

| Bezeichnung   | Beschreibung |
| :------------ | :----------- |
| Zugang prüfen | Prüft, ob die Angaben korrekt sind |

### b. Konfigurator-Modul

| Eigenschaft | Beschreibung |
| :---------- | :----------- |
| Kategorie   | opt. Angabe einer Kategorie, unterhalb der die Instanzen angelegt werden |
| Gerät       | Auswahlliste der Geräte des angegebenen Miele-Kontos |

### c. Geräte-Modul

| Eigenschaft             | Typ     | Standardwert | Beschreibung |
| :---------------------- | :-----  | :----------- | :----------- |
| Modul ist deaktiviert   | boolean | false        | Modul temporär deaktivieren |
|                         |         |              | |
| Geräete-Typ-ID          | integer |              | wird im Konfigurator gesetzt und darf nicht geändert werden, bestimmte die auszugebenden Felder |
| Geräte-Typ              | string  |              | wird im Konfigurator gesetzt |
| Seriennummer            | string  |              | wird im Konfigurator gesetzt und darf nicht geändert werden |
| Modell                  | string  |              | wird im Konfigurator gesetzt |
| Update-Intervall        | integer | 60           | Intervall der Datenabfrage in Sekunden |
|                         |         |              | |
| Code in Text übersetzen |         |              | Übersetzte Statustexte aus der API ignoreiren und selbst umsetzen |
|  ... Status             | boolean | false        | |
|  ... Programm           | boolean | false        | |
|  ... Phase              | boolean | false        | |
|  ... Trockenstufe       | boolean | false        | |
|  ... Entlüftungsstufe   | boolean | false        | |

| Bezeichnung         | Beschreibung |
| :------------------ | :----------- |
| Daten aktualisieren | Abfrage der aktuellen Daten |


Bisher unterstützte Gerätetypen

| Geräete-Typ-ID | Geräte-Typ |
| :------------- | :--------- |
| 1 | Waschmaschine |
| 2 | Wäschetrockner |
| 7 | Geschirrspüler |
| 12 | Backofen |
| 13 | Backofen mit Mikrowelle |
| 21 | Kühl/Gefrier-Kombination |
| 45 | Dampfgarer mit Backofen-Funktion |

### Variablenprofile

* Boolean<br>
MieleAtHome.Door

* Integer<br>
MieleAtHome.Action, MieleAtHome.Duration, MieleAtHome.Light, MieleAtHome.PowerSupply, MieleAtHome.SpinningSpeed,
MieleAtHome.Status, MieleAtHome.Supercooling, MieleAtHome.Superfreezing, MieleAtHome.Temperature

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

- 1.26 @ 01.02.2021 18:30 (beta)
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt
  - API-Version 1.0.4 (ecoFeedback)

- 1.25 @ 20.09.2020 21:14
  - URL's haben kein '/' mehr am Ende

- 1.24 @ 05.09.2020 11:12
  - LICENSE.md hinzugefügt
  - Nutzung von HasActiveParent(): Anzeige im Konfigurationsformular sowie entsprechende Absicherung von SendDataToParent()
  - interne Funktionen sind nun "private"
  - lokale Funktionen aus common.php in locale.php verlagert
  - define's durch statische Klassen-Variablen ersetzt

- 1.23 @ 08.04.2020 16:45
  - define's durch statische Klassen-Variablen ersetzt
  - Fix in MielelAtHomeDevice: 'Geräte-Typ-ID' wird nun als NumberSpinner angeboten

- 1.22 @ 06.03.2020 20:14
  - Wechsel des Verbindungstyp wird nun automatisch erkannt
  - Verwendung des OAuth-AccessToken korrigiert
  - OAuth allgemein weiter abgesichert

- 1.21 @ 10.02.2020 10:03
  - Umsetzung der letzten Änderungen der Miele-API:
    - PowerOn/PowerOff
	- Auswertung des neuen API-GET-Aufrufs "actions" um setzen zu können, welchen Aktionen zu diesem Zeitpunkt möglich sind

- 1.20 @ 06.01.2020 11:17
  - Nutzung von RegisterReference() für im Modul genutze Objekte (Scripte, Kategorien etc)

- 1.19 @ 01.01.2020 18:52
  - Anpassungen an IPS 5.3
    - Formular-Elemente: 'label' in 'caption' geändert
  - Schreibfehler korrigiert

- 1.18 @ 13.10.2019 13:18
  - Anpassungen an IPS 5.2
    - IPS_SetVariableProfileValues(), IPS_SetVariableProfileDigits() nur bei INTEGER, FLOAT
    - Dokumentation-URL in module.json
  - Umstellung auf strict_types=1
  - Umstellung von StyleCI auf php-cs-fixer

- 1.17 @ 09.08.2019 14:32
  - Schreibfehler korrigiert

- 1.16 @ 02.08.2019 08:41
  - Übernahme von Programmbezeichnung
  - Korrektur von Drehzahl

- 1.15 @ 30.07.2019 18:46
  - Anlage ohne gesetzte Import-Kategorie erfolgt in der Kategorie IP-Symcon/IP-Symcon

- 1.14 @ 29.07.2019 12:17
  - Absturz beim Aufruf des Konfigurators bei nicht eingerichteter IO-Instanz

- 1.13 @ 25.07.2019 09:22
  - Kompatibilität zu 5.2 (CC_GetUrl())
  - Absicherung bei fehlerhafter _ImportCategoryID_

- 1.12 @ 12.07.2019 14:47
  - OAuth hinzugefügt (Dank an Fonzo)
    Wichtiger Hinweis für ein Update: in der IO-Instanz den Verbindungstyp auf _Developer Key_ setzen!
  - Konfigurator als Konfigurationsformular
  - Modul-Prefixe auf _MieleAtHome_ vereinheitlicht

- 1.11 @ 23.04.2019 17:59
  - weitere Text für Phase bei Backofen/Dampfgarer
  - Tabellenausrichtung in README.md
  - Konfigurator um Sicherheitsabfrage erweitert

- 1.10 @ 29.03.2019 16:19
  - SetValue() abgesichert

- 1.9 @ 21.03.2019 17:04
  - Schalter, um eine Instanz (temporär) zu deaktivieren
  - Konfigurations-Element IntervalBox -> NumberSpinner
  - Dampfgarer/Backofen-Kombination hinzugefügt, diverse WARNING auf NOTIFY verringert

- 1.8 @ 23.01.2019 18:18
  - curl_errno() abfragen
  - Trockner: Trockenstufe 7 ('Glätten') hinzugefügt (fehlt in der API-Doku), Übersetzung angepasst

- 1.7 @ 12.01.2019 12:56
  - geänderte Login-Methode implementiert
    Achtung: die _VG-Auswahl_ muss im IO-Modul neu gesetzt werden

- 1.6 @ 10.01.2019 22:40
  - Länderauswahl ergänzt um Östereich

- 1.5 @ 30.12.2018 16:57
  - die Aktionen (HTTP-PUT-Aufrufe) stehen nun als Variablen mit Standard-Aktionen zur Verfügung
  - der _Status_ ist nun kein String mehr sondern ein Integer mit entsprechendem Variablenprofil

- 1.4 @ 28.12.2018 15:33
  - Anpassung an aktuelle API-Dokumentation
  - Ergänzung um die neuen HTTP-PUT-Aufrufe

- 1.3 @ 22.12.2018 12:25
  - Fehler in der http-Kommunikation nun nicht mehr mit _echo_ (also als **ERROR**) sondern mit _LogMessage_ als **NOTIFY** ausgegeben
  - Fix in einer DebugMessage

- 1.2 @ 21.12.2018 13:10
  - Standard-Konstanten verwenden

- 1.1 @ 27.11.2018 17:03
  - optional die Statuscodes selbst übersetzen

- 1.0 @ 04.11.2018 10:49
  - Initiale Version
