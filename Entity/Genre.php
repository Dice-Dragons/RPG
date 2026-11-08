<?php

declare(strict_types=1);

namespace cv6\RPG\Entity;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $genre_id
 * @property string $class
 * @property string $icon
 * @property int $property_id
 * @property bool $active
 * @property int $display_order
 * @property int $setting_count
 * @property int $map_count
 * @property int $wireset_count
 *
 * GETTERS
 * @property-read \XF\Phrase|string $title
 * @property-read \XF\Phrase|string $description
 *
 * RELATIONS
 * @property-read AbstractCollection<\cv6\CharEdit\Entity\Setting> $Settings
 * @property-read \XF\Entity\StyleProperty|null $Property
 * @property-read \XF\Entity\Phrase|null $MasterTitle
 * @property-read \XF\Entity\Phrase|null $MasterDescription
 */
class Genre extends Entity
{
	public const BLOCKED = 1;
	public const DEFAULT_GENRE_IDS = [1, 3, 5, 6, 7, 8];

	public const GROUP = 'cv6Genre';
	public const PROPERTY = 'cv6Genre_';

	public function getPhraseName(string $type = 'title'): string
	{
		if (!$this->genre_id)
		{
			return '';
		}

		$type = ($type === 'description' || $type === 'desc') ? 'desc' : 'title';
		return 'cv6_ce_genre_' . $type . '_' . (int)$this->genre_id;
	}

	public function getTitle(): \XF\Phrase|string
	{
		$phraseName = $this->getPhraseName('title');
		return $phraseName !== '' ? \XF::phrase($phraseName) : '';
	}

	public function getDescription(): \XF\Phrase|string
	{
		$phraseName = $this->getPhraseName('desc');
		if ($phraseName === '')
		{
			return '';
		}

		$phrase = \XF::phrase($phraseName);
		$phrase->fallback('');
		if ($phrase->render('raw') === '')
		{
			return '';
		}

		return $phrase;
	}

	public function getMasterPhrase(string $type = 'title'): \XF\Entity\Phrase
	{
		$type = ($type === 'description' || $type === 'desc') ? 'desc' : 'title';
		$phrase = $type === 'title' ? $this->MasterTitle : $this->MasterDescription;
		if (!$phrase)
		{
			/** @var \XF\Entity\Phrase $phrase */
			$phrase = $this->em()->create('XF:Phrase');
			$phrase->title = $this->getPhraseName($type);
			$phrase->language_id = 0;
			$phrase->addon_id = ($type === 'title' && in_array((int)$this->genre_id, self::DEFAULT_GENRE_IDS, true))
				? 'cv6/CharEdit'
				: '';
		}

		return $phrase;
	}

	public function getMasterTitlePhrase(): \XF\Entity\Phrase
	{
		return $this->getMasterPhrase('title');
	}

	public function getMasterDescriptionPhrase(): \XF\Entity\Phrase
	{
		return $this->getMasterPhrase('desc');
	}

	public function canDelete(): bool
	{
		return ((int)$this->genre_id > self::BLOCKED);
	}

	public function updateSettingCount(int $amount = 1, bool $save = true): void
	{
		$this->setting_count = max(0, $this->setting_count + $amount);
		if ($save)
		{
			$this->saveIfChanged();
		}
	}

	public function updateMapCount(int $amount = 1, bool $save = true): void
	{
		$this->map_count = max(0, $this->map_count + $amount);
		if ($save)
		{
			$this->saveIfChanged();
		}
	}

	public function updateWiresetCount(int $amount = 1, bool $save = true): void
	{
		$this->wireset_count = max(0, $this->wireset_count + $amount);
		if ($save)
		{
			$this->saveIfChanged();
		}
	}

	public function rebuildCounters(): void
	{
		$this->rebuildSettingCount();
		$this->rebuildMapCount();
		$this->rebuildWiresetCount();
	}

	public function rebuildSettingCount(): int
	{
		if (!$this->db()->getSchemaManager()->tableExists('xf_cv6_setting'))
		{
			return 0;
		}

		$count = $this->db()->fetchOne('
			SELECT COUNT(*)
			FROM xf_cv6_setting
			WHERE genre_id = ? AND active = 1
		', [$this->genre_id]);

		$this->setting_count = (int)$count;
		$this->saveIfChanged();

		return (int)$count;
	}

	public function rebuildMapCount(): int
	{
		$sm = $this->db()->getSchemaManager();
		if (!$sm->tableExists('xf_cv6_bm_map'))
		{
			return 0;
		}

		if ($sm->columnExists('xf_cv6_bm_map', 'genre_id'))
		{
			$count = $this->db()->fetchOne("
				SELECT COUNT(*)
				FROM xf_cv6_bm_map
				WHERE genre_id = ? AND map_state = 'visible'
			", [$this->genre_id]);
		}
		elseif ($sm->columnExists('xf_cv6_bm_map', 'genre'))
		{
			$count = $this->db()->fetchOne("
				SELECT COUNT(*)
				FROM xf_cv6_bm_map
				WHERE genre = ? AND map_state = 'visible'
			", [$this->class]);
		}
		else
		{
			$count = 0;
		}

		$this->map_count = (int)$count;
		$this->saveIfChanged();

		return (int)$count;
	}

	public function rebuildWiresetCount(): int
	{
		$sm = $this->db()->getSchemaManager();
		if (!$sm->tableExists('xf_cv6_wiresets') || !$sm->columnExists('xf_cv6_wiresets', 'genre_id'))
		{
			return 0;
		}

		$count = $this->db()->fetchOne('
			SELECT COUNT(*)
			FROM xf_cv6_wiresets
			WHERE genre_id = ? AND active = 1
		', [$this->genre_id]);

		$this->wireset_count = (int)$count;
		$this->saveIfChanged();

		return (int)$count;
	}

	protected function _postSave()
	{
		parent::_postSave();

		if ($this->isChanged(['class', 'icon', 'active', 'display_order']))
		{
			$this->clearGenreCache();
		}
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		if ($this->MasterTitle)
		{
			$this->MasterTitle->delete();
		}
		if ($this->MasterDescription)
		{
			$this->MasterDescription->delete();
		}

		$sm = $this->db()->getSchemaManager();
		if ($sm->tableExists('xf_cv6_setting'))
		{
			$this->db()->update(
				'xf_cv6_setting',
				['genre_id' => self::BLOCKED],
				'genre_id = ?',
				[$this->genre_id]
			);
		}
		if ($sm->tableExists('xf_cv6_bm_map') && $sm->columnExists('xf_cv6_bm_map', 'genre_id'))
		{
			$this->db()->update(
				'xf_cv6_bm_map',
				['genre_id' => self::BLOCKED],
				'genre_id = ?',
				[$this->genre_id]
			);
		}
		if ($sm->tableExists('xf_cv6_wiresets') && $sm->columnExists('xf_cv6_wiresets', 'genre_id'))
		{
			$this->db()->update(
				'xf_cv6_wiresets',
				['genre_id' => self::BLOCKED],
				'genre_id = ?',
				[$this->genre_id]
			);
		}

		/** @var Genre|null $defaultGenre */
		$defaultGenre = $this->em()->find('cv6\RPG:Genre', self::BLOCKED);
		if ($defaultGenre)
		{
			$defaultGenre->rebuildCounters();
		}

		$this->clearGenreCache();
	}

	public function clearGenreCache(): void
	{
		/** @var \cv6\RPG\Service\Genre\PropertyService $propertyService */
		$propertyService = \XF::service('cv6\RPG:Genre\PropertyService');
		$propertyService->clearCache();
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_cv6_genre';
		$structure->shortName = 'cv6\RPG:Genre';
		$structure->primaryKey = 'genre_id';
		$structure->contentType = 'cv6_genre';
		$structure->columns = [
			'genre_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'class' => [
				'type' => self::STR,
				'maxLength' => 25,
				'required' => 'cv6_please_enter_valid_cssclass',
				'unique' => 'cv6_class_must_be_unique',
				'match' => 'alphanumeric',
			],
			'icon' => [
				'type' => self::STR,
				'maxLength' => 100,
				'default' => '',
			],
			'property_id' => ['type' => self::UINT, 'default' => 0],
			'active' => ['type' => self::BOOL, 'default' => true],
			'display_order' => ['type' => self::UINT, 'default' => 1],
			'setting_count' => ['type' => self::UINT, 'default' => 0],
			'map_count' => ['type' => self::UINT, 'default' => 0],
			'wireset_count' => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = [
			'title' => true,
			'description' => true,
		];
		$structure->relations = [
			'Settings' => [
				'entity' => 'cv6\CharEdit:Setting',
				'type' => self::TO_MANY,
				'conditions' => 'genre_id',
			],
			'Property' => [
				'entity' => 'XF:StyleProperty',
				'type' => self::TO_ONE,
				'conditions' => 'property_id',
				'primary' => true,
			],
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'cv6_ce_genre_title_', '$genre_id'],
				],
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'cv6_ce_genre_desc_', '$genre_id'],
				],
			],
		];

		return $structure;
	}
}
