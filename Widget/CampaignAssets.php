<?php

declare(strict_types=1);

namespace cv6\RPG\Widget;

use cv6\CharEdit\Entity\Campaign as CampaignEntity;
use cv6\RPG\Entity\CampaignContent;
use cv6\RPG\Repository\CampaignContentRepository;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class CampaignAssets extends AbstractWidget
{
	public function render(): WidgetRenderer|string
	{
		if (!\XF::isAddOnActive('cv6/CharEdit'))
		{
			return '';
		}

		/** @var CampaignEntity|null $campaign */
		$campaign = $this->contextParams['campaign'] ?? null;
		if (!$campaign instanceof CampaignEntity || !$campaign->canView())
		{
			return '';
		}

		$bmActive = \XF::isAddOnActive('cv6/BattleMaps');
		$handoutActive = \XF::isAddOnActive('cv6/Handout');
		if (!$bmActive && !$handoutActive)
		{
			return '';
		}

		$visitor = \XF::visitor();
		$isGm = $campaign->isGm($visitor) || $campaign->canEdit($visitor);
		$onlyPlayers = !$isGm;

		/** @var CampaignContentRepository $contentRepo */
		$contentRepo = $this->repository('cv6\RPG:CampaignContent');

		$battlemapLinks = $bmActive
			? $contentRepo->findContentForCampaign((int)$campaign->campaign_id, CampaignContent::TYPE_BATTLEMAP, $onlyPlayers)->fetch()
			: null;

		$handoutLinks = $handoutActive
			? $contentRepo->findContentForCampaign((int)$campaign->campaign_id, CampaignContent::TYPE_HANDOUT, $onlyPlayers)->fetch()
			: null;

		$hasBattlemaps = $bmActive && ($isGm || ($battlemapLinks && $battlemapLinks->count() > 0));
		$hasHandouts = $handoutActive && ($isGm || ($handoutLinks && $handoutLinks->count() > 0));

		if (!$hasBattlemaps && !$hasHandouts)
		{
			return '';
		}

		$viewParams = [
			'title' => $this->getTitle(),
			'campaign' => $campaign,
			'battlemapLinks' => $battlemapLinks ?: [],
			'handoutLinks' => $handoutLinks ?: [],
			'isGm' => $isGm,
		];

		return $this->renderer('cv6_rpg_widget_campaign_assets', $viewParams);
	}

	public function getOptionsTemplate()
	{
		return null;
	}
}
