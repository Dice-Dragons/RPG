<?php

declare(strict_types=1);

namespace cv6\RPG\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class IndexController extends AbstractController
{
	public function actionIndex()
	{
		$genreId = $this->filter('genre_id', 'uint');

		$viewParams = [
			'genreId' => $genreId,
		];

		return $this->view('cv6\RPG:Index\Dashboard', 'cv6_rpg_dashboard', $viewParams);
	}
}
