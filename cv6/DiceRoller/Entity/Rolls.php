<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\DiceRoller\Entity;

use XF\Mvc\Entity\Structure;

class Rolls extends XFCP_Rolls
{
	public function hasCharacter(): bool
	{
		return (int)$this->cv6_character_id > 0 && $this->Character !== null;
	}

	public function getEffectiveDiceSetKey(): string
	{
		if ($this->hasCharacter() && !empty($this->Character->cv6_rpg_dice_set) && $this->Character->cv6_rpg_dice_set !== 'default')
		{
			return (string)$this->Character->cv6_rpg_dice_set;
		}

		if ($this->User && $this->User->Option && !empty($this->User->Option->cv6_dice_set) && $this->User->Option->cv6_dice_set !== 'default')
		{
			return (string)$this->User->Option->cv6_dice_set;
		}

		$app = $this->app();
		$templater = $app->templater();
		$style = ($templater && method_exists($templater, 'getStyle')) ? $templater->getStyle() : $app->style();
		$styleSet = $style ? (string)$style->getProperty('cv6DefaultDiceSet') : '';
		if ($styleSet !== '')
		{
			return $styleSet;
		}

		return (string)($app->options()->cv6DiceDefaultSet ?? 'default') ?: 'default';
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['cv6_character_id'] = [
			'type' => self::UINT,
			'default' => 0,
			'api' => true,
		];

		$structure->getters['EffectiveDiceSetKey'] = 'getEffectiveDiceSetKey';

		if (\XF::isAddOnActive('cv6/CharEdit'))
		{
			$structure->relations['Character'] = [
				'entity' => 'cv6\CharEdit:Character',
				'type' => self::TO_ONE,
				'conditions' => [['character_id', '=', '$cv6_character_id']],
				'primary' => true,
			];
		}

		return $structure;
	}
}
