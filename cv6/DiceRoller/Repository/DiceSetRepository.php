<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\DiceRoller\Repository;

use cv6\DiceRoller\Entity\Dice;
use cv6\DiceRoller\Entity\DiceSet;
use XF\Entity\User;

class DiceSetRepository extends XFCP_DiceSetRepository
{
	public function getGraphicUrlForSetKey(Dice $die, ?string $setKey = null, ?User $user = null): string
	{
		if ($die->graphic_type === 'set' && $setKey !== null && $setKey !== '' && $setKey !== 'default')
		{
			$slot = $die->set_slot ?: 'd6';
			/** @var DiceSet|null $set */
			$set = $this->getSetByKey($setKey);
			if ($set && $set->active)
			{
				$url = $this->getSlotFileUrl($set, $slot);
				if ($url)
				{
					return $url;
				}
			}
		}

		return $this->getGraphicUrl($die, $user);
	}
}
