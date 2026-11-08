<?php

declare(strict_types=1);

namespace cv6\RPG;

use XF\Entity\AddOn;

class Listener
{
	public static function addonPostInstall(
		\XF\AddOn\AddOn $addOn,
		AddOn $installedAddOn,
		array $json,
		array &$stateChanges
	): void
	{
		self::syncIfRpgModule($installedAddOn->addon_id);
	}

	public static function addonPostUpgrade(
		\XF\AddOn\AddOn $addOn,
		AddOn $upgradedAddOn,
		array $json,
		array &$stateChanges
	): void
	{
		self::syncIfRpgModule($upgradedAddOn->addon_id);
	}

	public static function addonPostRebuild(
		\XF\AddOn\AddOn $addOn,
		AddOn $rebuiltAddOn,
		array $json
	): void
	{
		self::syncIfRpgModule($rebuiltAddOn->addon_id);
	}

	protected static function syncIfRpgModule(string $addOnId): void
	{
		$modules = ['cv6/RPG', 'cv6/CharEdit', 'cv6/DiceRoller', 'cv6/BattleMaps', 'cv6/Handout'];
		if (!in_array($addOnId, $modules, true))
		{
			return;
		}

		$rpgAddOn = \XF::app()->addOnManager()->getById('cv6/RPG');
		if (!$rpgAddOn)
		{
			return;
		}

		$setup = new Setup($rpgAddOn, \XF::app());
		$setup->syncBridgeSchema();
	}

	public static function dicerollerContextWiresets(
		\XF\Mvc\Entity\AbstractCollection &$allowedWiresets,
		?\XF\Entity\Thread $thread,
		?\XF\Entity\Forum $forum,
		\cv6\DiceRoller\ControllerPlugin\Roller $roller
	): void
	{
		if (!$forum || !\XF::isAddOnActive('cv6/CharEdit') || !\XF::isAddOnActive('cv6/DiceRoller'))
		{
			return;
		}

		$settingId = (int)($forum->cv6_setting_id ?? 0);
		if ($settingId <= 0)
		{
			return;
		}

		/** @var \cv6\CharEdit\Entity\Setting|null $setting */
		$setting = \XF::em()->find('cv6\CharEdit:Setting', $settingId);
		if (!$setting)
		{
			return;
		}

		$settingWiresetTag = trim((string)($setting->cv6_rpg_wireset ?? ''));
		if ($settingWiresetTag === '' || isset($allowedWiresets[$settingWiresetTag]))
		{
			return;
		}

		/** @var \cv6\DiceRoller\Entity\Wiresets|null $wireset */
		$wireset = \XF::em()->find('cv6\DiceRoller:Wiresets', $settingWiresetTag);
		if ($wireset && $wireset->active)
		{
			$entities = $allowedWiresets->toArray();
			$entities = [$settingWiresetTag => $wireset] + $entities;
			$allowedWiresets = \XF::em()->getBasicCollection($entities);
		}
	}

	public static function preRenderCssLayouts(
		\XF\Template\Templater $templater,
		string &$type,
		string &$template,
		array &$params
	): void
	{
		$app = \XF::app();
		$cacheContainer = $app->simpleCache();
		$genres = $cacheContainer['cv6/CharEdit']['genreMap'] ?? null;
		if ($genres === null)
		{
			/** @var \cv6\RPG\Repository\GenreRepository $genreRepo */
			$genreRepo = $app->repository('cv6\RPG:Genre');
			$genres = $genreRepo->getGenreClassDefinitionList();
			$cacheContainer['cv6/CharEdit']['genreMap'] = $genres;
		}
		$params['genres'] = $genres;
	}
}
