<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Service\Campaign;

use cv6\CharEdit\Entity\Campaign;
use XF\Entity\Forum;
use XF\Entity\User;
use XF\Service\UpdatePermissionsService;

class NodeManager extends XFCP_NodeManager
{
	public function createCampaignForum(Campaign $campaign, int $parentNodeId = 0, ?string $title = null): Forum
	{
		$forum = parent::createCampaignForum($campaign, $parentNodeId, $title);

		if (\XF::isAddOnActive('cv6/DiceRoller') && $forum->isValidColumn('cv6_allowdiceroll'))
		{
			$forum->set('cv6_allowdiceroll', true);

			$wiresetTags = [];
			if ($campaign->Setting && !empty($campaign->Setting->cv6_rpg_wireset))
			{
				$wiresetTags[] = (string)$campaign->Setting->cv6_rpg_wireset;
			}

			$defaultTags = $this->app->options()->cv6RpgDefaultCampaignWiresets ?? [];
			if (is_string($defaultTags))
			{
				$defaultTags = $defaultTags !== '' ? array_map('trim', explode(',', $defaultTags)) : [];
			}
			if (is_array($defaultTags))
			{
				foreach ($defaultTags as $tag)
				{
					$tag = trim((string)$tag);
					if ($tag !== '')
					{
						$wiresetTags[] = $tag;
					}
				}
			}

			$existingWiresets = $forum->get('cv6_wiresets');
			if (is_array($existingWiresets))
			{
				$wiresetTags = array_merge($existingWiresets, $wiresetTags);
			}

			$wiresetTags = array_values(array_unique(array_filter($wiresetTags)));
			if (!empty($wiresetTags) && $forum->isValidColumn('cv6_wiresets'))
			{
				$forum->set('cv6_wiresets', $wiresetTags);
			}

			$forum->saveIfChanged();
		}

		return $forum;
	}

	public function syncMemberPermissions(Campaign $campaign): void
	{
		parent::syncMemberPermissions($campaign);

		if (!\XF::isAddOnActive('cv6/DiceRoller'))
		{
			return;
		}

		$nodeId = (int)$campaign->node_id;
		if ($nodeId <= 0)
		{
			return;
		}

		$memberUserIds = array_map('intval', $campaign->member_user_ids ?: []);
		if ($campaign->gm_user_id > 0 && !in_array((int)$campaign->gm_user_id, $memberUserIds, true))
		{
			$memberUserIds[] = (int)$campaign->gm_user_id;
		}

		$dicePermissions = [
			'forum' => [
				'rollDice' => 'content_allow',
				'viewDice' => 'content_allow',
			],
		];

		foreach ($memberUserIds as $userId)
		{
			/** @var User|null $user */
			$user = $this->em()->find(User::class, $userId);
			if ($user)
			{
				/** @var UpdatePermissionsService $permService */
				$permService = $this->service(UpdatePermissionsService::class);
				$permService->setContent('node', $nodeId)->setUser($user);
				$permService->updatePermissions($dicePermissions);
			}
		}
	}

	public function syncGmModerator(Campaign $campaign, ?int $previousGmUserId = null): void
	{
		parent::syncGmModerator($campaign, $previousGmUserId);

		if (!\XF::isAddOnActive('cv6/DiceRoller'))
		{
			return;
		}

		$nodeId = (int)$campaign->node_id;
		$gmUserId = (int)$campaign->gm_user_id;
		if ($nodeId <= 0 || $gmUserId <= 0)
		{
			return;
		}

		/** @var User|null $gmUser */
		$gmUser = $this->em()->find(User::class, $gmUserId);
		if (!$gmUser)
		{
			return;
		}

		$gmDicePermissions = [
			'forum' => [
				'rollDice' => 'content_allow',
				'viewDice' => 'content_allow',
				'rollHiddenDice' => 'content_allow',
				'viewHiddenDice' => 'content_allow',
				'deleteAnyRoll' => 'content_allow',
				'undeleteRoll' => 'content_allow',
				'viewDeletedRolls' => 'content_allow',
			],
		];

		/** @var UpdatePermissionsService $permService */
		$permService = $this->service(UpdatePermissionsService::class);
		$permService->setContent('node', $nodeId)->setUser($gmUser);
		$permService->updatePermissions($gmDicePermissions);
	}
}
