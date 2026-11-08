<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\DiceRoller\Entity;

class Dice extends XFCP_Dice
{
	public function getGraphicUrlForSetKey(?string $setKey = null, ?\XF\Entity\User $user = null): string
	{
		/** @var \cv6\RPG\cv6\DiceRoller\Repository\DiceSetRepository $repo */
		$repo = $this->repository('cv6\DiceRoller:DiceSet');
		if (method_exists($repo, 'getGraphicUrlForSetKey'))
		{
			return $repo->getGraphicUrlForSetKey($this, $setKey, $user);
		}

		return $this->getGraphicUrl($user);
	}
}
