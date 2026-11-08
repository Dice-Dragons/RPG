<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Pub\Controller;

use cv6\RPG\Entity\CampaignContent;
use cv6\RPG\Repository\CampaignContentRepository;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class CampaignController extends XFCP_CampaignController
{
	public function actionView(ParameterBag $params)
	{
		$reply = parent::actionView($params);

		if ($reply instanceof View)
		{
			/** @var \cv6\CharEdit\Entity\Campaign|null $campaign */
			$campaign = $reply->getParam('campaign');
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

				$recentRolls = $contentRepo->getRecentRollsForNode((int)$campaign->node_id, $isGm, 25);

				$reply->setParams([
					'rpgBattlemapLinks' => $battlemapLinks,
					'rpgHandoutLinks' => $handoutLinks,
					'rpgRecentRolls' => $recentRolls,
					'rpgIsGm' => $isGm,
				]);
			}
		}

		return $reply;
	}

	public function actionRpgLinkContent(ParameterBag $params)
	{
		$campaignId = $params->campaign_id ?: $params->url_slug;
		$campaign = $this->assertViewableCampaign($campaignId);

		$visitor = \XF::visitor();
		if (!$campaign->isGm($visitor) && !$campaign->canEdit($visitor))
		{
			return $this->noPermission();
		}

		$contentType = $this->filter('content_type', 'str');
		if (!in_array($contentType, [CampaignContent::TYPE_BATTLEMAP, CampaignContent::TYPE_HANDOUT], true))
		{
			$contentType = CampaignContent::TYPE_BATTLEMAP;
		}

		if ($this->isPost())
		{
			$contentId = $this->filter('content_id', 'uint');
			$visibility = $this->filter('visibility', 'str') ?: CampaignContent::VISIBILITY_GM_ONLY;
			if (!in_array($visibility, [CampaignContent::VISIBILITY_GM_ONLY, CampaignContent::VISIBILITY_PLAYERS], true))
			{
				$visibility = CampaignContent::VISIBILITY_GM_ONLY;
			}
			$titleOverride = $this->filter('title_override', 'str');
			$notes = $this->filter('notes', 'str');
			$displayOrder = $this->filter('display_order', 'uint') ?: 10;

			if ($contentId <= 0)
			{
				return $this->error(\XF::phrase('please_complete_required_fields'));
			}

			/** @var CampaignContentRepository $contentRepo */
			$contentRepo = $this->repository('cv6\RPG:CampaignContent');
			$contentRepo->linkContentToCampaign(
				(int)$campaign->campaign_id,
				$contentType,
				$contentId,
				$visibility,
				$titleOverride,
				$notes !== '' ? $notes : null,
				$displayOrder
			);

			return $this->redirect($this->buildLink('char/campaigns', $campaign));
		}

		/** @var CampaignContentRepository $contentRepo */
		$contentRepo = $this->repository('cv6\RPG:CampaignContent');
		$availableItems = $contentRepo->getAvailableItemsForUser($contentType, (int)$visitor->user_id);

		$viewParams = [
			'campaign' => $campaign,
			'contentType' => $contentType,
			'availableItems' => $availableItems,
		];

		return $this->view('cv6\RPG:Campaign\LinkContent', 'cv6_rpg_campaign_link_content', $viewParams);
	}

	public function actionRpgToggleVisibility(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$campaignId = $params->campaign_id ?: $params->url_slug;
		$campaign = $this->assertViewableCampaign($campaignId);

		$visitor = \XF::visitor();
		if (!$campaign->isGm($visitor) && !$campaign->canEdit($visitor))
		{
			return $this->noPermission();
		}

		$linkId = $this->filter('link_id', 'uint');
		/** @var CampaignContent|null $link */
		$link = $this->em()->find('cv6\RPG:CampaignContent', $linkId);
		if (!$link || (int)$link->campaign_id !== (int)$campaign->campaign_id)
		{
			return $this->notFound();
		}

		$link->visibility = ($link->visibility === CampaignContent::VISIBILITY_PLAYERS)
			? CampaignContent::VISIBILITY_GM_ONLY
			: CampaignContent::VISIBILITY_PLAYERS;
		$link->save();

		return $this->redirect($this->buildLink('char/campaigns', $campaign));
	}

	public function actionRpgUnlinkContent(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$campaignId = $params->campaign_id ?: $params->url_slug;
		$campaign = $this->assertViewableCampaign($campaignId);

		$visitor = \XF::visitor();
		if (!$campaign->isGm($visitor) && !$campaign->canEdit($visitor))
		{
			return $this->noPermission();
		}

		$linkId = $this->filter('link_id', 'uint');
		/** @var CampaignContent|null $link */
		$link = $this->em()->find('cv6\RPG:CampaignContent', $linkId);
		if ($link && (int)$link->campaign_id === (int)$campaign->campaign_id)
		{
			$link->delete();
		}

		return $this->redirect($this->buildLink('char/campaigns', $campaign));
	}
}
