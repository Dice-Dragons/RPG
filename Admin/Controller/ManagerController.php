<?php

declare(strict_types=1);

namespace cv6\RPG\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\AdminSectionPlugin;
use XF\Entity\AddOn;
use XF\Mvc\Reply\AbstractReply;

class ManagerController extends AbstractController
{
	public function actionIndex(): AbstractReply
	{
		$this->setSectionContext('cv6RpgManager');

		$catalog = [
			'foundation' => [
				'title_phrase' => 'cv6_rpg_addon_group_foundation',
				'items' => [
					'cv6/Core' => [
						'default_title' => '[DD] - Core Library',
						'desc_phrase' => 'cv6_rpg_addon_desc_core',
						'icon' => 'fa-cogs',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/core.145/',
					],
					'cv6/RPG' => [
						'default_title' => '[DD] RPG Suite',
						'desc_phrase' => 'cv6_rpg_addon_desc_rpg',
						'icon' => 'fa-dice-d20',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/',
					],
					'cv6/CustomFields' => [
						'default_title' => '[DD] Custom Fields Extension',
						'desc_phrase' => 'cv6_rpg_addon_desc_customfields',
						'icon' => 'fa-sliders-h',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/custom-fields.148/',
					],
				],
			],
			'modules' => [
				'title_phrase' => 'cv6_rpg_addon_group_modules',
				'items' => [
					'cv6/CharEdit' => [
						'default_title' => '[DD] Character Database',
						'desc_phrase' => 'cv6_rpg_addon_desc_charedit',
						'icon' => 'fa-id-card',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/charedit.179/',
					],
					'cv6/DiceRoller' => [
						'default_title' => '[DD] Dice Roller',
						'desc_phrase' => 'cv6_rpg_addon_desc_diceroller',
						'icon' => 'fa-dice',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/dice-roller.146/',
					],
					'cv6/BattleMaps' => [
						'default_title' => '[DD] BattleMap Generator',
						'desc_phrase' => 'cv6_rpg_addon_desc_battlemaps',
						'icon' => 'fa-map',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/',
					],
					'cv6/Handout' => [
						'default_title' => '[DD] Handout Generator',
						'desc_phrase' => 'cv6_rpg_addon_desc_handout',
						'icon' => 'fa-scroll',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/',
					],
				],
			],
			'recommended' => [
				'title_phrase' => 'cv6_rpg_addon_group_recommended',
				'items' => [
					'cv6/FontManager' => [
						'default_title' => '[DD] Font Manager',
						'desc_phrase' => 'cv6_rpg_addon_desc_fontmanager',
						'icon' => 'fa-font',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/font-manager.187/',
					],
					'cv6/Timeline' => [
						'default_title' => '[DD] Timeline',
						'desc_phrase' => 'cv6_rpg_addon_desc_timeline',
						'icon' => 'fa-stream',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/time-line.189/',
					],
					'cv6/Prefix' => [
						'default_title' => '[DD] Prefix Tools',
						'desc_phrase' => 'cv6_rpg_addon_desc_prefix',
						'icon' => 'fa-tags',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/prefix-tools.152/',
					],
					'cv6/IconsGame' => [
						'default_title' => '[DD] Icon Interface: Game-Icons.net',
						'desc_phrase' => 'cv6_rpg_addon_desc_iconsgame',
						'icon' => 'fa-dragon',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/game-icons.186/',
					],
				],
			],
		];

		$addOnManager = $this->app()->addOnManager();
		$addonGroups = [];

		foreach ($catalog as $groupKey => $groupDef)
		{
			$resolvedItems = [];
			foreach ($groupDef['items'] as $addOnId => $meta)
			{
				$addOnObj = $addOnManager->getById($addOnId);
				$installedEntity = $addOnObj ? $addOnObj->getInstalledAddOn() : null;

				$isInstalled = $addOnObj !== null && $addOnObj->isInstalled();
				$isActive = $addOnObj !== null && $addOnObj->isActive();
				$isAvailableOnDisk = $addOnObj !== null && $addOnObj->isAvailable();

				$title = $installedEntity
					? (string)$installedEntity->title
					: ($addOnObj ? (string)$addOnObj->title : $meta['default_title']);

				$versionString = $installedEntity
					? (string)$installedEntity->version_string
					: ($addOnObj ? (string)$addOnObj->version_string : '');

				$resourceUrl = $meta['resource_url'];
				if ($addOnObj && !empty($addOnObj->faq_url) && $addOnObj->faq_url !== 'https://forum.dice-dragons.de/downloads/')
				{
					$resourceUrl = (string)$addOnObj->faq_url;
				}

				$resolvedItems[$addOnId] = [
					'addon_id' => $addOnId,
					'addon_id_url' => str_replace('/', '-', $addOnId),
					'addOn' => $addOnObj,
					'title' => $title,
					'version_string' => $versionString,
					'desc_phrase' => $meta['desc_phrase'],
					'icon' => $meta['icon'],
					'resource_url' => $resourceUrl,
					'is_installed' => $isInstalled,
					'is_active' => $isActive,
					'is_available_on_disk' => $isAvailableOnDisk,
				];
			}

			$addonGroups[$groupKey] = [
				'title_phrase' => $groupDef['title_phrase'],
				'items' => $resolvedItems,
			];
		}

		/** @var AdminSectionPlugin $sectionPlugin */
		$sectionPlugin = $this->plugin(AdminSectionPlugin::class);

		return $sectionPlugin->actionView('cv6Rpg', null, null, 'cv6_rpg_manager', [
			'addonGroups' => $addonGroups,
		]);
	}

	public function actionToggle(): AbstractReply
	{
		$this->assertAdminPermission('addOn');
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$addOnId = $this->filter('addon_id', 'str');
		/** @var AddOn $addOn */
		$addOn = $this->assertRecordExists(AddOn::class, $addOnId);

		if (!$addOn->canEdit())
		{
			return $this->noPermission();
		}

		if (!$addOn->active)
		{
			$addOnObj = $this->app()->addOnManager()->getById($addOn->addon_id);
			if ($addOnObj)
			{
				$errors = [];
				$warnings = [];
				$addOnObj->checkRequirements($errors, $warnings);
				if ($errors)
				{
					return $this->error($errors);
				}
			}

			$addOn->active = true;
			$addOn->save();
		}

		return $this->redirect($this->buildLink('rpg/manager'));
	}
}
