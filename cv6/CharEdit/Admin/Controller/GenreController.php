<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Admin\Controller;

use cv6\CharEdit\Entity\Genre;
use cv6\RPG\Repository\GenreRepository;
use XF\Mvc\ParameterBag;

class GenreController extends XFCP_GenreController
{
	protected function genreSaveProcess(Genre $genre)
	{
		$form = parent::genreSaveProcess($genre);

		$icon = $this->filter('icon', 'str');

		$form->setup(function () use ($genre, $icon): void
		{
			$genre->icon = $icon;
		});

		return $form;
	}

	public function actionDelete(ParameterBag $params)
	{
		$oldGenreId = (int)$params['genre_id'];
		$reassignGenreId = $this->isPost() ? $this->filter('reassign_genre_id', 'uint', 0) : 0;

		/** @var GenreRepository $genreRepo */
		$genreRepo = $this->repository('cv6\RPG:Genre');

		if ($this->isPost() && $oldGenreId > 0 && $reassignGenreId > 0 && $reassignGenreId !== $oldGenreId)
		{
			$genreRepo->reassignGenreRelations($oldGenreId, $reassignGenreId);
		}

		$reply = parent::actionDelete($params);

		if ($this->isPost() && $reassignGenreId > 0)
		{
			$genreRepo->rebuildGenreCounters($reassignGenreId);
		}

		return $reply;
	}
}
