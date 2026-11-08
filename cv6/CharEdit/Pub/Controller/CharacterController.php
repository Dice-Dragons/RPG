<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Pub\Controller;

use cv6\CharEdit\Entity\Character as Char;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class CharacterController extends XFCP_CharacterController
{
	protected function editBasics(Char $char)
	{
		$reply = parent::editBasics($char);

		if ($reply instanceof View && \XF::isAddOnActive('cv6/DiceRoller'))
		{
			/** @var \cv6\DiceRoller\Repository\DiceSetRepository $diceSetRepo */
			$diceSetRepo = $this->repository('cv6\DiceRoller:DiceSet');
			$selectableSets = $diceSetRepo->getSelectableSetsForUser(\XF::visitor());
			$reply->setParam('rpgSelectableDiceSets', $selectableSets);
		}

		return $reply;
	}

	public function actionSave(ParameterBag $params)
	{
		if ($this->isPost() && $this->filter('save_action', 'str') === 'basic')
		{
			$characterInput = $this->filter('character', 'array');
			if (array_key_exists('cv6_rpg_dice_set', $characterInput))
			{
				$char = $this->assertCharExists($params->character_id);
				if ($char->isValidColumn('cv6_rpg_dice_set'))
				{
					$diceSetKey = trim((string)$characterInput['cv6_rpg_dice_set']);
					$char->set('cv6_rpg_dice_set', $diceSetKey !== '' ? $diceSetKey : null);
					$char->saveIfChanged();
				}
			}
		}

		return parent::actionSave($params);
	}
}
