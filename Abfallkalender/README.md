# Abfallkalender (generisch)
Dieses IP-Symcon-Modul erstellt einen generischen Abfallkalender, bei dem die Daten der Müllabfuhr manuell gepflegt werden müssen. Also primär für alle diejenigen, die einen Abfallkalender in IP-Symcon nutzen möchten, jedoch keine Möglichkeit haben, die Daten vom jeweiligen Abfallunternehmen parsen zu können.

## Inhaltsverzeichnis
1. [Funktionen](#funktionen)
2. [Voraussetzungen](#voraussetzungen)
3. [Installation](#installation)
4. [Changelog](#changelog)
5. [To Do's](#to-dos)

## Funktionen
* HTML-Box welche die nächsten Abfalltermine anzeigt
* Option für die Aktivierung der Push-Benachrichtigung (Modulkonfiguration)
* Auswahl folgender Müllarten:
    * Restmüll
    * Gelber Sack
    * Pappe/Papier
    * Biotonne
* Die String-Variablen der Abfuhrtermine können selbstverständlich auch von jedem Skript, welches Abfuhrtermine einer Webseite parsed, automatisch "befüllt" werden. Hierbei ist nur wichtig, dass nach jedem Termin ein "New-Line" folgt.  

## Voraussetzungen
* IP-Symcon ab Version 4.2

## Installation
Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/dampflok2000/SymconModulesDampflok2000`

Anschließend eine neue Instanz erstellen:

Hersteller         | Gerät       | 
------------ | --------- | 
(Sonstige)       | Abfallkalender   | 

## Changelog
* 0.9.5
    * Biotonne hinzugefügt
    * E-Mail-Benachrichtigung hinzugefügt
* 0.9.0
    * Initiale Erstellung des Moduls

## To Do's
- [x] Option für Biotonne
- [ ] Übersetzung
- [x] E-Mail-Benachrichtigung
