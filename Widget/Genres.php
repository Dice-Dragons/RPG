<?php

declare(strict_types=1);

namespace cv6\RPG\Widget;

use cv6\RPG\Repository\GenreRepository;
use XF\Http\Request;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class Genres extends AbstractWidget
{
	protected $defaultOptions = [
		'layout' => 'sidenav',
		'target_route' => 'char/setting/list',
		'count_field' => 'setting_count',
		'show_all' => true,
	];

	public function render(): WidgetRenderer|string
	{
		/** @var GenreRepository $genreRepo */
		$genreRepo = $this->repository('cv6\RPG:Genre');
		$genres = $genreRepo->findGenreForList(true)->fetch();

		if (!$genres->count())
		{
			return '';
		}

		$options = $this->options;
		$targetRoute = $options['target_route'] ?? 'char/setting/list';
		if ($targetRoute === 'char/setting/list' && !\XF::isAddOnActive('cv6/CharEdit') && \XF::isAddOnActive('cv6/BattleMaps'))
		{
			$targetRoute = 'battle-maps';
		}

		$selectedGenreId = (int)($this->contextParams['genre_id'] ?? 0);

		$viewParams = [
			'title' => $this->getTitle(),
			'genres' => $genres,
			'layout' => $options['layout'] ?? 'sidenav',
			'targetRoute' => $targetRoute,
			'countField' => $options['count_field'] ?? 'setting_count',
			'showAll' => (bool)($options['show_all'] ?? true),
			'selectedGenreId' => $selectedGenreId,
		];

		return $this->renderer('cv6_rpg_widget_genres', $viewParams);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null): bool
	{
		$options = $request->filter([
			'layout' => 'str',
			'target_route' => 'str',
			'count_field' => 'str',
			'show_all' => 'bool',
		]);

		if (!in_array($options['layout'], ['sidenav', 'pills', 'grid'], true))
		{
			$options['layout'] = 'sidenav';
		}

		if (!in_array($options['count_field'], ['setting_count', 'map_count', 'wireset_count', ''], true))
		{
			$options['count_field'] = 'setting_count';
		}

		if ($options['target_route'] === '')
		{
			$options['target_route'] = 'char/setting/list';
		}

		return true;
	}
}
