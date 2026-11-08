# [DD] RPG Suite
*Diese Dokumentation ist auch verfügbar auf: [English](README.md)*

![Version](https://img.shields.io/badge/version-1.0.0_Beta_1-blue.svg) ![XenForo](https://img.shields.io/badge/XenForo-2.3%2B-orange.svg) ![PHP](https://img.shields.io/badge/PHP-8.2%2B-qt.svg)

Zentrale RPG-Plattform (Genres, Verlage, Dashboard) und modulare Integrationsbrücke zwischen **[DD] Character Database**, **[DD] Dice Roller**, **[DD] BattleMap Generator** und **[DD] Handout Generator**.

## Funktionen
* Zentrale Verwaltung für RPG-Genres (Icons, Zähler, automatische Style-Properties) und RPG-Verlage (Logos, URLs, Lizenzhinweise, JSON/ZIP-Export & -Import)
* AdminCP-Ökosystem- & Add-on-Übersicht für installierte, deaktivierte und verfügbare RPG-Suite-Module inklusive direktem Aktivierungs-Schalter
* Öffentliches RPG Suite Dashboard (`/rpg/`) mit Genre-Filter in der linken Seitenleiste für alle genre-fähigen Widgets
* Verknüpfung von RPG-Settings mit primären Würfel-Wiresets sowie automatische Wireset-Zuweisung für neue Kampagnenforen
* Würfelbare Charakter-Attribute/Fertigkeiten, charakterbezogene Grafik-Würfelsets und „Als Charakter würfeln“-Integration im Dice Roller
* Kampagnen-Inhaltsverknüpfung für BattleMaps und Handouts (Sichtbarkeit für SL oder Gruppe) sowie optionale In-Thread RPG-Begleiterleiste

## Voraussetzungen
* XenForo 2.3.0+, PHP 8.2.0+
* [DD] - Core Library (v1.6.3+)
* Optionale [Icon Interfaces](https://forum.dice-dragons.de/downloads/categories/icon-interfaces.61/) (z. B. `[DD] Icon: Game-Icons`) für erweiterte RPG-Genre-Icons

## Konfiguration / Nutzung
Die Verwaltung von Optionen, Genres, Verlagen und der Modulübersicht erfolgt im AdminCP unter **RPG Suite**. Ein deutsches Sprachpaket liegt unter `_output/language/lang-cv6-RPG-de-de.xml` (`language/RPG-1000031-de.xml`) bei.
