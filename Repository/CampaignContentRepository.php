<?php

declare(strict_types=1);

namespace cv6\RPG\Repository;

use cv6\RPG\Entity\CampaignContent;
use cv6\RPG\Finder\CampaignContentFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Repository;

class CampaignContentRepository extends Repository
{
	public function findContentForCampaign(
		int $campaignId,
		?string $contentType = null,
		bool $onlyPlayersVisible = false
	): CampaignContentFinder
	{
		/** @var CampaignContentFinder $finder */
		$finder = $this->finder('cv6\RPG:CampaignContent');
		return $finder->forCampaign($campaignId, $contentType, $onlyPlayersVisible);
	}

	public function getCampaignContentCounts(int $campaignId, bool $isGm = false): array
	{
		$visClause = $isGm ? '' : " AND visibility = 'players'";
		$pairs = $this->db()->fetchPairs("
			SELECT content_type, COUNT(*)
			FROM xf_cv6_rpg_campaign_content
			WHERE campaign_id = ?{$visClause}
			GROUP BY content_type
		", [$campaignId]);

		return [
			CampaignContent::TYPE_BATTLEMAP => (int)($pairs[CampaignContent::TYPE_BATTLEMAP] ?? 0),
			CampaignContent::TYPE_HANDOUT => (int)($pairs[CampaignContent::TYPE_HANDOUT] ?? 0),
		];
	}

	public function isHandoutSharedWithUserViaCampaign(int $handoutId, int $userId): bool
	{
		if ($handoutId <= 0 || $userId <= 0 || !\XF::isAddOnActive('cv6/CharEdit'))
		{
			return false;
		}

		/** @var AbstractCollection<CampaignContent> $links */
		$links = $this->finder('cv6\RPG:CampaignContent')
			->with('Campaign')
			->where('content_type', CampaignContent::TYPE_HANDOUT)
			->where('content_id', $handoutId)
			->where('visibility', CampaignContent::VISIBILITY_PLAYERS)
			->fetch();

		/** @var \XF\Entity\User|null $user */
		$user = $this->em->find('XF:User', $userId);
		if (!$user)
		{
			return false;
		}

		foreach ($links as $link)
		{
			if ($link->Campaign && $link->Campaign->isMember($user))
			{
				return true;
			}
		}

		return false;
	}

	public function linkContentToCampaign(
		int $campaignId,
		string $contentType,
		int $contentId,
		string $visibility = CampaignContent::VISIBILITY_GM_ONLY,
		string $titleOverride = '',
		?string $notes = null,
		int $displayOrder = 10
	): CampaignContent
	{
		/** @var CampaignContent|null $existing */
		$existing = $this->finder('cv6\RPG:CampaignContent')
			->where('campaign_id', $campaignId)
			->where('content_type', $contentType)
			->where('content_id', $contentId)
			->fetchOne();

		if ($existing)
		{
			$existing->visibility = $visibility;
			$existing->title_override = $titleOverride;
			$existing->notes = $notes;
			$existing->display_order = $displayOrder;
			$existing->save();
			return $existing;
		}

		/** @var CampaignContent $link */
		$link = $this->em->create('cv6\RPG:CampaignContent');
		$link->campaign_id = $campaignId;
		$link->content_type = $contentType;
		$link->content_id = $contentId;
		$link->visibility = $visibility;
		$link->title_override = $titleOverride;
		$link->notes = $notes;
		$link->display_order = $displayOrder;
		$link->added_user_id = (int)\XF::visitor()->user_id;
		$link->added_date = \XF::$time;
		$link->save();

		return $link;
	}

	public function getRecentRollsForNode(int $nodeId, bool $isGm = false, int $limit = 25): AbstractCollection|array
	{
		if ($nodeId <= 0 || !\XF::isAddOnActive('cv6/DiceRoller'))
		{
			return [];
		}

		$threadIds = $this->db()->fetchAllColumn(
			'SELECT thread_id FROM xf_thread WHERE node_id = ? AND discussion_state = ?',
			[$nodeId, 'visible']
		);
		if (empty($threadIds))
		{
			return [];
		}

		$rollFinder = $this->finder('cv6\DiceRoller:Rolls')
			->with(['User', 'Wireset', 'Thread', 'Character'])
			->where('thread_id', $threadIds)
			->order('roll_time', 'DESC')
			->limit($limit);

		if (!$isGm)
		{
			$rollFinder->where('roll_state', 'visible');
		}
		else
		{
			$rollFinder->where('roll_state', ['visible', 'hidden']);
		}

		return $rollFinder->fetch();
	}

	/**
	 * @return array<int, string>
	 */
	public function getAvailableItemsForUser(string $contentType, int $userId): array
	{
		$availableItems = [];
		if ($userId <= 0)
		{
			return $availableItems;
		}

		if ($contentType === CampaignContent::TYPE_BATTLEMAP && \XF::isAddOnActive('cv6/BattleMaps'))
		{
			if ($this->db()->getSchemaManager()->tableExists('xf_cv6_bm_map'))
			{
				$maps = $this->finder('cv6\BattleMaps:Map')
					->where('user_id', $userId)
					->where('map_state', 'visible')
					->order('last_update', 'DESC')
					->fetch();
				foreach ($maps as $map)
				{
					$availableItems[(int)$map->map_id] = (string)$map->title;
				}
			}
		}
		else if ($contentType === CampaignContent::TYPE_HANDOUT && \XF::isAddOnActive('cv6/Handout'))
		{
			if ($this->db()->getSchemaManager()->tableExists('xf_cv6_handout'))
			{
				$handouts = $this->finder('cv6\Handout:Handout')
					->where('user_id', $userId)
					->where('handout_state', '<>', 'deleted')
					->order('display_order', 'ASC')
					->fetch();
				foreach ($handouts as $handout)
				{
					$availableItems[(int)$handout->handout_id] = (string)$handout->title;
				}
			}
		}

		return $availableItems;
	}
}
