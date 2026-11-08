<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\DiceRoller\ControllerPlugin;

use XF\Entity\Forum;
use XF\Entity\Thread;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class Roller extends XFCP_Roller
{
	public function actionDisplayRoll(Thread $thread, ParameterBag $params, $confirmUrl)
	{
		$reply = parent::actionDisplayRoll($thread, $params, $confirmUrl);
		if ($reply instanceof View)
		{
			$forum = $thread->Forum ?: $this->finder('XF:Forum')->where('node_id', '=', $thread->node_id)->fetchOne();
			$this->enrichRpgRollView($reply, $thread, $forum);
		}
		return $reply;
	}

	public function actionDisplayNew(Forum $forum, ParameterBag $params, $confirmUrl)
	{
		$reply = parent::actionDisplayNew($forum, $params, $confirmUrl);
		if ($reply instanceof View)
		{
			$this->enrichRpgRollView($reply, null, $forum);
		}
		return $reply;
	}

	protected function performSaveRoll(int $thread_id, $confirmUrl)
	{
		$reply = parent::performSaveRoll($thread_id, $confirmUrl);

		if ($reply instanceof Redirect)
		{
			$jsonParams = $reply->getJsonParams();
			$rollId = (int)($jsonParams['cv6_rollid'] ?? 0);
			if ($rollId > 0)
			{
				$characterId = $this->filter('cv6_character_id', 'uint');
				if ($characterId <= 0)
				{
					$rollInput = $this->filter('roll', 'array');
					$characterId = (int)($rollInput['cv6_character_id'] ?? 0);
				}

				if ($characterId > 0 && \XF::isAddOnActive('cv6/CharEdit'))
				{
					/** @var \cv6\CharEdit\Entity\Character|null $character */
					$character = $this->em()->find('cv6\CharEdit:Character', $characterId);
					$visitor = \XF::visitor();
					if ($character && ((int)$character->user_id === (int)$visitor->user_id || $character->canEdit()))
					{
						/** @var \cv6\DiceRoller\Entity\Rolls|null $rollEntity */
						$rollEntity = $this->em()->find('cv6\DiceRoller:Rolls', $rollId);
						if ($rollEntity && $rollEntity->isValidColumn('cv6_character_id'))
						{
							$rollEntity->fastUpdate('cv6_character_id', (int)$character->character_id);
						}
					}
				}
			}
		}

		return $reply;
	}

	protected function enrichRpgRollView(View $reply, ?Thread $thread, ?Forum $forum): void
	{
		if (!\XF::isAddOnActive('cv6/CharEdit'))
		{
			return;
		}

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return;
		}

		$settingId = 0;
		$campaignId = 0;

		if ($forum && isset($forum->cv6_setting_id) && (int)$forum->cv6_setting_id > 0)
		{
			$settingId = (int)$forum->cv6_setting_id;
		}
		if ($thread && isset($thread->cv6_setting_id) && (int)$thread->cv6_setting_id > 0)
		{
			$settingId = (int)$thread->cv6_setting_id;
		}

		if ($forum)
		{
			/** @var \cv6\CharEdit\Entity\Campaign|null $campaign */
			$campaign = $this->finder('cv6\CharEdit:Campaign')
				->where('node_id', $forum->node_id)
				->fetchOne();
			if ($campaign)
			{
				$campaignId = (int)$campaign->campaign_id;
				if ($settingId <= 0)
				{
					$settingId = (int)$campaign->setting_id;
				}
			}
		}

		// Ensure Setting's primary wireset is included in allowed wiresets
		$allowedWiresets = $reply->getParam('wiresets');
		$settingWiresetTag = '';
		if ($settingId > 0)
		{
			/** @var \cv6\CharEdit\Entity\Setting|null $setting */
			$setting = $this->em()->find('cv6\CharEdit:Setting', $settingId);
			if ($setting && !empty($setting->cv6_rpg_wireset))
			{
				$settingWiresetTag = (string)$setting->cv6_rpg_wireset;
				/** @var \cv6\DiceRoller\Entity\Wiresets|null $ws */
				$ws = $this->em()->find('cv6\DiceRoller:Wiresets', $settingWiresetTag);
				if ($ws && $ws->active)
				{
					$list = $allowedWiresets ? $allowedWiresets->toArray() : [];
					if (!isset($list[$settingWiresetTag]))
					{
						$list = [$settingWiresetTag => $ws] + $list;
						$allowedWiresets = new ArrayCollection($list);
						$reply->setParam('wiresets', $allowedWiresets);
					}
				}
			}
		}

		// Load visitor's characters (prioritizing campaign & setting characters)
		$charFinder = $this->finder('cv6\CharEdit:Character')
			->where('user_id', $visitor->user_id)
			->where('char_state', '<>', 'deleted')
			->where('char_state', '<>', 'wizard')
			->with(['Setting', 'Campaign'])
			->order('name');

		if ($settingId > 0)
		{
			$charFinder->where('setting_id', $settingId);
		}

		$characters = $charFinder->fetch();

		$selectedCharId = $this->filter('character_id', 'uint');
		if ($selectedCharId <= 0 && $campaignId > 0)
		{
			foreach ($characters as $c)
			{
				if ((int)$c->campaign_id === $campaignId)
				{
					$selectedCharId = (int)$c->character_id;
					break;
				}
			}
		}
		if ($selectedCharId <= 0 && $characters->count() === 1)
		{
			$firstChar = $characters->first();
			$selectedCharId = $firstChar ? (int)$firstChar->character_id : 0;
		}

		$characterRollables = [];
		foreach ($characters as $charId => $char)
		{
			if (method_exists($char, 'getRollableFields'))
			{
				$characterRollables[$charId] = array_values($char->getRollableFields());
			}
		}

		$prefillFieldId = $this->filter('rpg_field', 'str');
		$prefillComment = $this->filter('rpg_comment', 'str');
		$prefillModifier = $this->filter('rpg_modifier', 'int');
		$prefillWireset = $this->filter('rpg_wireset', 'str') ?: $settingWiresetTag;
		$prefillTarget = $this->filter('rpg_target', 'str') ?: 'modifier';

		if ($prefillFieldId !== '' && $selectedCharId > 0 && isset($characters[$selectedCharId]))
		{
			$rollables = $characters[$selectedCharId]->getRollableFields();
			if (isset($rollables[$prefillFieldId]))
			{
				$fData = $rollables[$prefillFieldId];
				if ($prefillComment === '')
				{
					$prefillComment = (string)$fData['title'];
				}
				$prefillModifier = (int)$fData['value'];
				if (!empty($fData['wireset']))
				{
					$prefillWireset = (string)$fData['wireset'];
				}
				if (!empty($fData['param_target']))
				{
					$prefillTarget = (string)$fData['param_target'];
				}
			}
		}

		$reply->setParams([
			'rpgCharacters' => $characters,
			'rpgSelectedCharacterId' => $selectedCharId,
			'rpgCharacterRollablesJson' => json_encode($characterRollables),
			'rpgPrefill' => [
				'field_id' => $prefillFieldId,
				'comment' => $prefillComment,
				'modifier' => $prefillModifier,
				'wireset' => $prefillWireset,
				'target' => $prefillTarget,
			],
		]);
	}
}
