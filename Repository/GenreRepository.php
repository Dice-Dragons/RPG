<?php

declare(strict_types=1);

namespace cv6\RPG\Repository;

use cv6\RPG\Entity\Genre;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class GenreRepository extends Repository
{
	public function findGenreForList($onlyActive = false, $byTitle = false): Finder
	{
		$finder = $this->finder('cv6\RPG:Genre')->with('MasterTitle');
		if ($byTitle === true)
		{
			$finder->order('MasterTitle.phrase_text');
		}
		else
		{
			$finder->order('display_order');
		}
		if ($onlyActive === true)
		{
			$finder->where('active', true);
		}
		return $finder;
	}

	public function getGenreClassDefinitionList(): array
	{
		return $this->findGenreForList()->fetch()->pluckNamed('class', 'genre_id');
	}

	public function getGenreListData($onlyActive = false): array
	{
		$genre = $this->findGenreForList($onlyActive)->fetch();

		return [
			'totalGenre' => $genre->count(),
			'genre' => $genre,
		];
	}

	public function getGenreData($onlyActive = false)
	{
		return $this->findGenreForList($onlyActive)->fetch();
	}

	public function alignDisplayOrder($base = 1000): void
	{
		$layouts = $this->findGenreForList()->fetch();
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

	public function getGenreTitlePairs($onlyActive = false): array
	{
		return $this->findGenreForList($onlyActive)
			->fetch()
			->pluckNamed('title', 'genre_id');
	}

	public function getBattleMapGenrePairs(bool $onlyActive = true): array
	{
		$genres = $this->findGenreForList($onlyActive)->fetch();
		$pairs = [];
		foreach ($genres as $genre)
		{
			$pairs[$genre->genre_id] = [
				'genre_id' => $genre->genre_id,
				'class' => $genre->class,
				'title' => (string)$genre->title,
				'icon' => $genre->icon,
				'setting_count' => $genre->setting_count,
				'map_count' => $genre->map_count,
				'wireset_count' => $genre->wireset_count,
			];
		}
		return $pairs;
	}

	public function rebuildSettingCount($genreId): void
	{
		/** @var Genre|null $genre */
		$genre = $this->em->find('cv6\RPG:Genre', $genreId);
		if ($genre)
		{
			$genre->rebuildCounters();
		}
	}

	public function rebuildGenreCounters(int $genreId): void
	{
		/** @var Genre|null $genre */
		$genre = $this->em->find('cv6\RPG:Genre', $genreId);
		if ($genre)
		{
			$genre->rebuildCounters();
		}
	}

	public function reassignGenreRelations(int $oldGenreId, int $newGenreId): void
	{
		if ($oldGenreId <= 0 || $newGenreId <= 0 || $oldGenreId === $newGenreId)
		{
			return;
		}

		$db = $this->db();
		$sm = $db->getSchemaManager();

		if ($sm->tableExists('xf_cv6_setting') && $sm->columnExists('xf_cv6_setting', 'genre_id'))
		{
			$db->update('xf_cv6_setting', ['genre_id' => $newGenreId], 'genre_id = ?', [$oldGenreId]);
		}

		if ($sm->tableExists('xf_cv6_bm_map') && $sm->columnExists('xf_cv6_bm_map', 'genre_id'))
		{
			$db->update('xf_cv6_bm_map', ['genre_id' => $newGenreId], 'genre_id = ?', [$oldGenreId]);
		}

		if ($sm->tableExists('xf_cv6_wiresets') && $sm->columnExists('xf_cv6_wiresets', 'genre_id'))
		{
			$db->update('xf_cv6_wiresets', ['genre_id' => $newGenreId], 'genre_id = ?', [$oldGenreId]);
		}
	}
}
