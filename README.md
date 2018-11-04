# IPSymconMieleAtHome

[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.0-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Module-Version](https://img.shields.io/badge/Modul_Version-1.0-blue.svg)
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

## 2. Voraussetzungen

 - IP-Symcon ab Version 5
 - Miele@Home-Account
 - Zugangsdaten zu Miele@Home-third-party-API

## 3. Installation

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconMieleAtHome.git`

und mit _OK_ bestätigen.

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

## 4. Funktionsreferenz

## 5. Konfiguration

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

- 1.0 @ 04.11.2018 10:49<br>
  Initiale Version
