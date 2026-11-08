<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\Handout\Entity;

use cv6\RPG\Repository\CampaignContentRepository;

class Handout extends XFCP_Handout
{
	public function canView(&$error = null): bool
	{
		if (parent::canView($error))
		{
			return true;
		}

		if ($this->handout_state === 'deleted')
		{
			return false;
		}

		$visitor = \XF::visitor();
		if ($visitor->user_id > 0 && $this->hasPermission('view'))
		{
			/** @var CampaignContentRepository $contentRepo */
			$contentRepo = $this->repository('cv6\RPG:CampaignContent');
			if ($contentRepo->isHandoutSharedWithUserViaCampaign((int)$this->handout_id, (int)$visitor->user_id))
			{
				return true;
			}
		}

		return false;
	}
}
