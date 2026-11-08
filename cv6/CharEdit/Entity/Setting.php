<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Entity;

use XF\Mvc\Entity\Structure;

class Setting extends XFCP_Setting
{
	public function getRpgWireset(): ?\XF\Mvc\Entity\Entity
	{
		$tag = (string)$this->cv6_rpg_wireset;
		if ($tag === '' || !\XF::isAddOnActive('cv6/DiceRoller'))
		{
			return null;
		}

		return $this->em()->find('cv6\DiceRoller:Wiresets', $tag);
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['cv6_rpg_wireset'] = [
			'type' => self::STR,
			'maxLength' => 25,
			'default' => '',
		];

		$structure->getters['RpgWireset'] = true;

		if (\XF::isAddOnActive('cv6/DiceRoller'))
		{
			$structure->relations['Wireset'] = [
				'entity' => 'cv6\DiceRoller:Wiresets',
				'type' => self::TO_ONE,
				'conditions' => [['tag', '=', '$cv6_rpg_wireset']],
				'primary' => true,
			];
		}

		return $structure;
	}
}
