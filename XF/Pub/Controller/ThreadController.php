<?php

declare(strict_types=1);

namespace cv6\RPG\XF\Pub\Controller;

use cv6\RPG\Entity\CampaignContent;
use cv6\RPG\Repository\CampaignContentRepository;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class ThreadController extends XFCP_ThreadController
{
	public function actionIndex(ParameterBag $params)
	{
		$reply = parent::actionIndex($params);

		if ($reply instanceof View && !$this->isPost())
		{
			if (!empty($this->app()->options()->cv6RpgEnableThreadCompanion) && \XF::isAddOnActive('cv6/CharEdit'))
			{
				/** @var \XF\Entity\Thread|null $thread */
				$thread = $reply->getParam('thread');
				$forum = $thread ? $thread->Forum : null;
				$campaign = ($forum && isset($forum->Campaign)) ? $forum->Campaign : null;

				if ($campaign)
				{
					$visitor = \XF::visitor();
					$isGm = $campaign->isGm($visitor) || $campaign->canEdit($visitor);
					$onlyPlayers = !$isGm;

					/** @var CampaignContentRepository $contentRepo */
					$contentRepo = $this->repository('cv6\RPG:CampaignContent');

					$battlemapLinks = $contentRepo->findContentForCampaign(
						(int)$campaign->campaign_id,
						CampaignContent::TYPE_BATTLEMAP,
						$onlyPlayers
					)->fetch();

					$handoutLinks = $contentRepo->findContentForCampaign(
						(int)$campaign->campaign_id,
						CampaignContent::TYPE_HANDOUT,
						$onlyPlayers
					)->fetch();

					$myCampaignChars = [];
					if ($visitor->user_id > 0)
					{
						$myCampaignChars = $this->finder('cv6\CharEdit:Character')
							->where('campaign_id', $campaign->campaign_id)
							->where('user_id', $visitor->user_id)
							->where('char_state', '<>', 'deleted')
							->where('char_state', '<>', 'wizard')
							->order('name')
							->fetch();
					}

					$reply->setParams([
						'rpgThreadCampaign' => $campaign,
						'rpgThreadBattlemapLinks' => $battlemapLinks,
						'rpgThreadHandoutLinks' => $handoutLinks,
						'rpgThreadCharacters' => $myCampaignChars,
						'rpgThreadIsGm' => $isGm,
					]);
				}
			}
		}

		return $reply;
	}
}
