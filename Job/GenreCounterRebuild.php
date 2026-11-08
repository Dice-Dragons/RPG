<?php

declare(strict_types=1);

namespace cv6\RPG\Job;

use cv6\RPG\Repository\GenreRepository;
use XF\Job\AbstractRebuildJob;

class GenreCounterRebuild extends AbstractRebuildJob
{
	protected function getNextIds($start, $batch): array
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit(
				'
					SELECT genre_id
					FROM xf_cv6_genre
					WHERE genre_id > ?
					ORDER BY genre_id
				',
				$batch
			),
			$start
		);
	}

	protected function rebuildById($id): void
	{
		/** @var GenreRepository $repo */
		$repo = $this->app->repository('cv6\RPG:Genre');
		$repo->rebuildGenreCounters((int)$id);
	}

	protected function getStatusType()
	{
		return \XF::phrase('cv6_rpg_rebuild_genre_counters');
	}
}
