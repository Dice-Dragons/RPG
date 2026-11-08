# [DD] RPG Suite
*This documentation is also available in: [Deutsch](README.de.md)*

![Version](https://img.shields.io/badge/version-1.0.0_Beta_1-blue.svg) ![XenForo](https://img.shields.io/badge/XenForo-2.3%2B-orange.svg) ![PHP](https://img.shields.io/badge/PHP-8.2%2B-qt.svg)

Core RPG domain platform (Genres, Publishers, Dashboard) and modular integration bridge connecting **[DD] Character Database**, **[DD] Dice Roller**, **[DD] BattleMap Generator**, and **[DD] Handout Generator**.

## Features
* Central management for RPG Genres (icons, counters, automatic style properties) and RPG Publishers (logos, URLs, license notices, JSON/ZIP export & import)
* AdminCP Ecosystem & Add-on Overview showing installed, disabled, and available RPG Suite modules with direct enable toggles
* Public RPG Suite Dashboard (`/rpg/`) with a left-sidebar Genre filter that dynamically filters all genre-aware widgets
* Setting-to-Wireset linking and automatic Campaign Forum wireset provisioning when creating new campaign forums
* Rollable character attributes/skills, character-specific preferred Dice Sets, and "Roll as Character" integration in the Dice Roller
* Campaign resource linking for BattleMaps and Handouts (with GM-only or party-visible access) and an optional In-Thread RPG Companion Bar

## Requirements
* XenForo 2.3.0+, PHP 8.2.0+
* [DD] - Core Library (v1.6.3+)
* Optional [Icon Interfaces](https://forum.dice-dragons.de/downloads/categories/icon-interfaces.61/) (such as `[DD] Icon: Game-Icons`) for extended RPG genre icons

## Configuration / Usage
Configure RPG Suite options, Genres, Publishers, and inspect module status in the AdminCP under **RPG Suite**. A German language pack is included in `_output/language/lang-cv6-RPG-de-de.xml` (`language/RPG-1000031-de.xml`).
