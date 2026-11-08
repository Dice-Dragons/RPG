<?php

declare(strict_types=1);

namespace cv6\RPG\Service\Genre;

use cv6\RPG\Entity\Genre;
use cv6\RPG\Repository\GenreRepository;

class PropertyService extends \XF\Service\AbstractService
{
	protected ?Genre $genre = null;

	public function __construct(\XF\App $app, ?Genre $genre = null)
	{
		parent::__construct($app);
		$this->genre = $genre;
	}

	public function alignDisplayOrder($base = 1000): void
	{
		$layouts = $this->getGenreRepo()->findGenreForList()->fetch();
		foreach ($layouts as $layout)
		{
			if ($layout->Property)
			{
				$property = $layout->Property;
				$property->display_order = (int)$base + $layout->display_order;
				$property->save();
			}
		}
	}

	public function reassignProperties(): void
	{
		$properties = $this->finder('XF:StyleProperty')
			->where('group_name', '=', Genre::GROUP)
			->where('style_id', 0)
			->where('property_name', 'LIKE', Genre::PROPERTY . '%')
			->fetch();

		foreach ($properties as $property)
		{
			$parts = explode('_', $property->property_name, 2);
			if (!empty($parts[1]))
			{
				/** @var Genre|null $genre */
				$genre = $this->finder('cv6\RPG:Genre')->where('class', '=', $parts[1])->fetchOne();
				if ($genre && $genre->property_id != $property->property_id)
				{
					$genre->property_id = $property->property_id;
					$genre->save();
				}
			}
		}
		$this->clearCache();
	}

	public function rebuildPropertys(&$error = null): void
	{
		$genres = $this->getGenreRepo()->findGenreForList('all')->fetch();

		$this->db()->beginTransaction();
		try
		{
			foreach ($genres as $genre)
			{
				$property = $this->findExistingProperty($genre);
				if ($property)
				{
					if ($genre->property_id != $property->property_id)
					{
						$genre->property_id = $property->property_id;
						$genre->save();
					}

					$property->title = \XF::phrase('cv6_genre_layout_property_title', ['title' => $genre->title]);
					$property->description = \XF::phrase('cv6_genre_layout_property_description', ['tag' => $genre->class]);
					$property->property_name = Genre::PROPERTY . $genre->class;
					$property->group_name = Genre::GROUP;
					$property->display_order = 1000 + $genre->display_order;
					$property->save();
				}
				else
				{
					$property = $this->createProperty($genre);
					$genre->property_id = $property->property_id;
					$genre->save();
				}
			}
			$this->db()->commit();
		}
		catch (\Exception $e)
		{
			$this->db()->rollback();
			$error = \XF::phrase('cv6_db_error_x', ['error' => $e]);
		}
		$this->clearCache();
	}

	public function clearCache(): void
	{
		$cacheContainer = \XF::app()->simpleCache();
		unset($cacheContainer['cv6/CharEdit']['genreMap']);
		unset($cacheContainer['cv6/RPG']['genreMap']);

		/** @var \XF\Service\StyleProperty\RebuildService $rebuildService */
		$rebuildService = \XF::service('XF:StyleProperty\RebuildService');
		$rebuildService->rebuildFullPropertyMap();
		$rebuildService->rebuildPropertyStyleCache();

		\XF::repository('XF:Style')->updateAllStylesLastModifiedDate();
	}

	public function findExistingProperty(?Genre $genre = null): ?\XF\Entity\StyleProperty
	{
		if ($genre === null)
		{
			$genre = $this->genre;
		}
		if ($genre === null)
		{
			return null;
		}

		if ($genre->property_id && $genre->Property)
		{
			return $genre->Property;
		}

		/** @var \XF\Entity\StyleProperty|null $property */
		$property = $this->finder('XF:StyleProperty')
			->where('group_name', '=', Genre::GROUP)
			->where('style_id', 0)
			->where('property_name', '=', Genre::PROPERTY . $genre->class)
			->fetchOne();

		return $property;
	}

	public function createProperty(?Genre $genre = null): \XF\Entity\StyleProperty
	{
		if ($genre === null)
		{
			$genre = $this->genre;
		}
		if ($genre === null)
		{
			throw new \InvalidArgumentException('Genre must be specified to create property');
		}

		$existing = $this->findExistingProperty($genre);
		if ($existing)
		{
			return $existing;
		}

		/** @var \XF\Entity\StyleProperty $property */
		$property = \XF::em()->create('XF:StyleProperty');
		$property->title = \XF::phrase('cv6_genre_layout_property_title', ['title' => $genre->title]);
		$property->group_name = Genre::GROUP;
		$property->style_id = 0;
		$property->property_name = Genre::PROPERTY . $genre->class;
		$property->description = \XF::phrase('cv6_genre_layout_property_description', ['tag' => $genre->class]);
		$property->property_type = 'css';
		$property->css_components = ['text', 'background', 'border', 'border_radius', 'padding', 'extra'];
		$property->addon_id = in_array((int)$genre->genre_id, Genre::DEFAULT_GENRE_IDS, true) ? 'cv6/CharEdit' : '';
		$property->display_order = 1000 + $genre->display_order;
		$property->save();
		$this->clearCache();
		return $property;
	}

	private function getGenreRepo(): GenreRepository
	{
		/** @var GenreRepository $repo */
		$repo = $this->repository('cv6\RPG:Genre');
		return $repo;
	}
}
