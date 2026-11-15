<?php

declare(strict_types=1);

namespace cv6\RPG\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\AdminSectionPlugin;
use XF\Entity\AddOn;
use XF\Mvc\Reply\AbstractReply;

class ManagerController extends AbstractController
{
	protected const VERSIONS_JSON_URL = 'https://raw.githubusercontent.com/Dice-Dragons/.github/main/versions.json';
	protected const CACHE_KEY = 'remoteVersions';
	protected const CACHE_TTL = 21600; // 6 hours

	public function actionIndex(): AbstractReply
	{
		$this->setSectionContext('cv6RpgManager');

		if ($this->filter('refresh', 'bool'))
		{
			$this->getRemoteVersions(true);
			return $this->redirect($this->buildLink('rpg/manager'));
		}

		$remoteInfo = $this->getRemoteVersions(false);
		$remoteData = is_array($remoteInfo['data'] ?? null) ? $remoteInfo['data'] : [];

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
						'resource_url' => 'https://forum.dice-dragons.de/downloads/rpg-suite.191/',
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
						'resource_url' => 'https://forum.dice-dragons.de/downloads/battle-map-generator.195/',
					],
					'cv6/Handout' => [
						'default_title' => '[DD] Handout Generator',
						'desc_phrase' => 'cv6_rpg_addon_desc_handout',
						'icon' => 'fa-scroll',
						'resource_url' => 'https://forum.dice-dragons.de/downloads/handout-generator.194/',
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
				$installedVersionId = $installedEntity
					? (int)$installedEntity->version_id
					: ($addOnObj ? (int)$addOnObj->version_id : 0);

				$remote = is_array($remoteData[$addOnId] ?? null) ? $remoteData[$addOnId] : null;
				$remoteVersionId = $remote ? (int)($remote['version_id'] ?? 0) : 0;
				$remoteVersionString = $remote ? (string)($remote['version_string'] ?? '') : '';

				$resourceUrl = $this->resolveResourceUrl(
					$addOnObj ? (string)($addOnObj->faq_url ?? '') : '',
					$remote ? (string)($remote['faq_url'] ?? '') : '',
					$meta['resource_url']
				);

				$resolvedItems[$addOnId] = [
					'addon_id' => $addOnId,
					'addon_id_url' => str_replace('/', '-', $addOnId),
					'addOn' => $addOnObj,
					'title' => $title,
					'version_string' => $versionString,
					'version_id' => $installedVersionId,
					'remote_version_id' => $remoteVersionId,
					'remote_version_string' => $remoteVersionString,
					'is_update_available' => ($installedVersionId > 0 && $remoteVersionId > $installedVersionId),
					'is_up_to_date' => ($installedVersionId > 0 && $remoteVersionId > 0 && $installedVersionId >= $remoteVersionId),
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
			'lastVersionCheck' => (int)($remoteInfo['fetched_at'] ?? 0),
		]);
	}

	/**
	 * Resolves the most specific resource page URL across local add-on metadata, remote registry, and catalog defaults.
	 */
	protected function resolveResourceUrl(string $localUrl, string $remoteUrl, string $fallbackUrl): string
	{
		$genericUrls = [
			'https://forum.dice-dragons.de/',
			'https://forum.dice-dragons.de/downloads/',
		];

		foreach ([$localUrl, $remoteUrl] as $candidate)
		{
			$candidate = trim($candidate);
			if ($candidate !== '' && !in_array($candidate, $genericUrls, true))
			{
				return $candidate;
			}
		}

		return $fallbackUrl;
	}

	/**
	 * Fetches and caches the public Dice Dragons versions.json registry (6-hour TTL).
	 */
	protected function getRemoteVersions(bool $forceRefresh = false): array
	{
		$simpleCache = $this->app()->simpleCache()->getSet('cv6/RPG');
		$cached = $simpleCache->getValue(self::CACHE_KEY);

		if (
			!$forceRefresh
			&& is_array($cached)
			&& isset($cached['fetched_at'], $cached['data'])
			&& (\XF::$time - (int)$cached['fetched_at']) < self::CACHE_TTL
		)
		{
			return $cached;
		}

		try
		{
			$response = $this->app()->http()->client()->get(self::VERSIONS_JSON_URL, [
				'timeout' => 5,
			]);

			if ($response->getStatusCode() === 200)
			{
				$decoded = json_decode((string)$response->getBody(), true);
				if (is_array($decoded))
				{
					$fresh = [
						'fetched_at' => \XF::$time,
						'data' => $decoded,
					];
					$simpleCache->setValue(self::CACHE_KEY, $fresh);
					return $fresh;
				}
			}
		}
		catch (\Throwable $e)
		{
			// Fail silently and fall back to cached data or empty registry when GitHub is unreachable
		}

		return is_array($cached) ? $cached : ['fetched_at' => 0, 'data' => []];
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
