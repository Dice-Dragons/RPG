<?php

declare(strict_types=1);

namespace cv6\RPG\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class IndexController extends AbstractController
{
	public function actionIndex()
	{
		$genreId = $this->filter('genre_id', 'uint');
		$selectedGenre = null;
		if ($genreId > 0)
		{
			/** @var \cv6\RPG\Entity\Genre|null $selectedGenre */
			$selectedGenre = $this->em()->find('cv6\RPG:Genre', $genreId);
			if (!$selectedGenre || !$selectedGenre->active)
			{
				$genreId = 0;
				$selectedGenre = null;
			}
		}

		$viewParams = [
			'genreId' => $genreId,
			'selectedGenre' => $selectedGenre,
		];

		return $this->view('cv6\RPG:Index\Dashboard', 'cv6_rpg_dashboard', $viewParams);
	}
}
