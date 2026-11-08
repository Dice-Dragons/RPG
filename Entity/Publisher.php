<?php

declare(strict_types=1);

namespace cv6\RPG\Entity;

use XF\Entity\LinkableInterface;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $publisher_id
 * @property string $title
 * @property string|null $url_slug
 * @property string $description
 * @property string $logo_url
 * @property string $url
 * @property string|null $license_notice
 * @property bool $active
 * @property int $display_order
 *
 * RELATIONS
 * @property-read AbstractCollection<\cv6\CharEdit\Entity\Setting> $Settings
 */
class Publisher extends Entity implements LinkableInterface, ViewableInterface
{
	public function canView(&$error = null)
	{
		if (\XF::isAddOnActive('cv6/CharEdit') && method_exists(\XF::visitor(), 'hasCharacterPermission'))
		{
			return $this->hasPermission('viewSetting');
		}
		return true;
	}

	public function canEdit(&$error = null)
	{
		return \XF::visitor()->hasAdminPermission('cv6ManagePublisher');
	}

	public function canReport(&$error = null, ?\XF\Entity\User $asUser = null)
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}

	public function hasPermission($permission)
	{
		$visitor = \XF::visitor();
		if (method_exists($visitor, 'hasCharacterPermission'))
		{
			return $visitor->hasCharacterPermission($permission);
		}
		return true;
	}

	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null)
	{
		$routePrefix = \XF::isAddOnActive('cv6/CharEdit') ? 'char/publisher' : 'rpg/publishers';
		$route = ($canonical ? 'canonical:' : '') . $routePrefix;
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	public function getContentPublicRoute()
	{
		return \XF::isAddOnActive('cv6/CharEdit') ? 'char/publisher' : 'rpg/publishers';
	}

	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('cv6_publisher_x', [
			'title' => $this->title,
		]);
	}

	protected function verifyUrlSlug(&$name)
	{
		if (!$name)
		{
			$name = null;
			return true;
		}

		if ($name === strval(intval($name)) || $name === '-')
		{
			$this->error(\XF::phrase('cv6_url_slug_contain_more_numbers_hyphen'), 'url_slug');
			return false;
		}

		return true;
	}

	public function getSettings(bool $includeCo = true)
	{
		if (!\XF::isAddOnActive('cv6/CharEdit'))
		{
			return $this->em()->getEmptyCollection();
		}

		/** @var \cv6\CharEdit\Repository\SettingRepository $repo */
		$repo = $this->repository('cv6\CharEdit:Setting');
		return $repo->getSettingsByPublisher($this, $includeCo);
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		if ($this->db()->getSchemaManager()->tableExists('xf_cv6_setting'))
		{
			$this->db()->update(
				'xf_cv6_setting',
				['publisher_id' => 0],
				'publisher_id = ?',
				[$this->publisher_id]
			);
		}

		if ($this->logo_url)
		{
			$normalized = ltrim($this->logo_url, '/');
			if (str_starts_with($normalized, 'data/'))
			{
				$normalized = substr($normalized, 5);
			}
			if (str_starts_with($normalized, 'assets/settings/publisher/'))
			{
				\XF\Util\File::deleteFromAbstractedPath('data://' . $normalized);
			}
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_cv6_publisher';
		$structure->shortName = 'cv6\RPG:Publisher';
		$structure->primaryKey = 'publisher_id';
		$structure->contentType = 'cv6_publisher';
		$structure->columns = [
			'publisher_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'title' => ['type' => self::STR, 'required' => 'please_enter_valid_title', 'maxLength' => 50],
			'url_slug' => [
				'type' => self::STR,
				'maxLength' => 50,
				'nullable' => true,
				'default' => null,
				'unique' => 'cv6_slug_must_be_unique',
				'match' => self::MATCH_ALPHANUMERIC_HYPHEN,
				'verify' => 'verifyUrlSlug',
			],
			'description' => ['type' => self::STR, 'required' => 'cv6_please_enter_valid_description', 'maxLength' => 10000],
			'logo_url' => ['type' => self::STR, 'maxLength' => 250, 'default' => ''],
			'url' => ['type' => self::STR, 'maxLength' => 250, 'default' => ''],
			'license_notice' => ['type' => self::STR, 'nullable' => true, 'default' => null],
			'active' => ['type' => self::BOOL, 'default' => true],
			'display_order' => ['type' => self::UINT, 'default' => 1],
		];
		$structure->behaviors = [
			'XF:Indexable' => [
				'checkForUpdates' => ['title', 'description', 'active'],
			],
		];
		$structure->getters = [];
		$structure->relations = [
			'Settings' => [
				'entity' => 'cv6\CharEdit:Setting',
				'type' => self::TO_MANY,
				'conditions' => 'publisher_id',
			],
		];

		return $structure;
	}
}
