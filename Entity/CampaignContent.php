<?php

declare(strict_types=1);

namespace cv6\RPG\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $link_id
 * @property int $campaign_id
 * @property string $content_type
 * @property int $content_id
 * @property string $visibility
 * @property string $title_override
 * @property string|null $notes
 * @property int $display_order
 * @property int $added_user_id
 * @property int $added_date
 *
 * GETTERS
 * @property-read Entity|null $Content
 * @property-read string $effective_title
 * @property-read bool $is_visible_to_players
 *
 * RELATIONS
 * @property-read \cv6\CharEdit\Entity\Campaign|null $Campaign
 * @property-read \XF\Entity\User|null $AddedUser
 */
class CampaignContent extends Entity
{
	public const TYPE_BATTLEMAP = 'battlemap';
	public const TYPE_HANDOUT = 'handout';

	public const VISIBILITY_GM_ONLY = 'gm_only';
	public const VISIBILITY_PLAYERS = 'players';

	public function isVisibleToPlayers(): bool
	{
		return $this->visibility === self::VISIBILITY_PLAYERS;
	}

	public function getIsVisibleToPlayers(): bool
	{
		return $this->isVisibleToPlayers();
	}

	public function canView(?\XF\Entity\User $user = null): bool
	{
		$user = $user ?: \XF::visitor();
		if (!$this->Campaign || !$this->Campaign->canView($user))
		{
			return false;
		}

		if ($this->Campaign->isGm($user) || $this->Campaign->canEdit($user))
		{
			return true;
		}

		return $this->isVisibleToPlayers() && $this->Campaign->isMember($user);
	}

	public function getContent(): ?Entity
	{
		if ($this->content_type === self::TYPE_HANDOUT && \XF::isAddOnActive('cv6/Handout'))
		{
			return $this->em()->find('cv6\Handout:Handout', $this->content_id);
		}

		if ($this->content_type === self::TYPE_BATTLEMAP && \XF::isAddOnActive('cv6/BattleMaps'))
		{
			if ($this->db()->getSchemaManager()->tableExists('xf_cv6_bm_map'))
			{
				try
				{
					return $this->em()->find('cv6\BattleMaps:Map', $this->content_id);
				}
				catch (\Throwable $e)
				{
					return null;
				}
			}
		}

		return null;
	}

	public function getEffectiveTitle(): string
	{
		if ($this->title_override !== '')
		{
			return $this->title_override;
		}

		$content = $this->Content;
		if ($content && isset($content->title))
		{
			return (string)$content->title;
		}

		return '#' . $this->content_id;
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_cv6_rpg_campaign_content';
		$structure->shortName = 'cv6\RPG:CampaignContent';
		$structure->primaryKey = 'link_id';
		$structure->columns = [
			'link_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'campaign_id' => ['type' => self::UINT, 'required' => true],
			'content_type' => [
				'type' => self::STR,
				'maxLength' => 25,
				'required' => true,
				'allowedValues' => [self::TYPE_BATTLEMAP, self::TYPE_HANDOUT],
			],
			'content_id' => ['type' => self::UINT, 'required' => true],
			'visibility' => [
				'type' => self::STR,
				'maxLength' => 20,
				'default' => self::VISIBILITY_GM_ONLY,
				'allowedValues' => [self::VISIBILITY_GM_ONLY, self::VISIBILITY_PLAYERS],
			],
			'title_override' => ['type' => self::STR, 'maxLength' => 150, 'default' => ''],
			'notes' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'display_order' => ['type' => self::UINT, 'default' => 10],
			'added_user_id' => ['type' => self::UINT, 'default' => 0],
			'added_date' => ['type' => self::UINT, 'default' => \XF::$time],
		];
		$structure->getters = [
			'Content' => true,
			'effective_title' => true,
			'is_visible_to_players' => true,
		];
		$structure->relations = [
			'AddedUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$added_user_id']],
				'primary' => true,
			],
		];

		if (\XF::isAddOnActive('cv6/CharEdit'))
		{
			$structure->relations['Campaign'] = [
				'entity' => 'cv6\CharEdit:Campaign',
				'type' => self::TO_ONE,
				'conditions' => 'campaign_id',
				'primary' => true,
			];
		}

		return $structure;
	}
}
