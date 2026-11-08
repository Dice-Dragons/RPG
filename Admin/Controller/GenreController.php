<?php

declare(strict_types=1);

namespace cv6\RPG\Admin\Controller;

use cv6\RPG\Entity\Genre;
use cv6\RPG\Repository\GenreRepository;
use cv6\RPG\Service\Genre\PropertyService;
use cv6\RPG\Service\Genre\SortService;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\SortPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Entity\Style;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class GenreController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!\XF::visitor()->hasAdminPermission('cv6ManageRpg') && !\XF::visitor()->hasAdminPermission('cv6ManageCharsettings'))
		{
			$this->assertAdminPermission('cv6ManageRpg');
		}
	}

	public function actionIndex(): AbstractReply
	{
		$genres = $this->getGenreRepo()->getGenreListData();
		/** @var Style $style */
		$style = $this->assertRecordExists('XF:Style', \XF::options()->defaultStyleId);
		$genreField = null;

		if (\XF::isAddOnActive('cv6/CharEdit') && !empty(\XF::options()->cv6EnableGenre))
		{
			$genreFinder = $this->finder('cv6\CharEdit:SettingField');
			if (method_exists($genreFinder, 'forGenre'))
			{
				$genreField = $genreFinder->forGenre()->fetchOne();
			}
		}

		$viewParams = [
			'style' => $style,
			'genres' => $genres,
			'genrefield' => $genreField,
		];
		return $this->view('cv6\RPG:Genre\Listing', 'cv6_rpg_genre_list', $viewParams);
	}

	public function actionToggle(ParameterBag $params): AbstractReply
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle('cv6\RPG:Genre', 'active', [
			'preSaveCallback' => function (Genre $genre): void
			{
				if (!$genre->canDelete())
				{
					throw $this->exception($this->error(\XF::phrase('cv6_rpg_default_genre_cannot_be_disabled')));
				}
			},
		]);
	}

	public function genreAddEdit(Genre $genre): AbstractReply
	{
		/** @var Style $style */
		$style = $this->assertRecordExists('XF:Style', \XF::options()->defaultStyleId);
		if (!$style->canEdit())
		{
			return $this->error(\XF::phrase('style_properties_in_style_can_not_be_modified'));
		}

		$viewParams = [
			'style' => $style,
			'genre' => $genre,
		];
		return $this->view('cv6\RPG:Genre\Edit', 'cv6_rpg_genre_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params): AbstractReply
	{
		$genre = $this->assertGenreExists($params['genre_id']);
		return $this->genreAddEdit($genre);
	}

	public function actionAdd(): AbstractReply
	{
		/** @var Genre $genre */
		$genre = $this->em()->create('cv6\RPG:Genre');
		return $this->genreAddEdit($genre);
	}

	protected function genreSaveProcess(Genre $genre): FormAction
	{
		$entityInput = $this->filter([
			'class' => 'str',
			'icon' => 'str',
			'display_order' => 'int',
			'active' => 'uint',
		]);

		if (!$genre->canDelete() && $genre->isUpdate())
		{
			unset($entityInput['class']);
			$entityInput['active'] = 1;
		}

		$form = $this->formAction();
		$form->basicEntitySave($genre, $entityInput);

		$extraInput = $this->filter([
			'title' => 'str',
			'description' => 'str',
		]);

		$form->validate(function (FormAction $form) use ($extraInput): void
		{
			if ($extraInput['title'] === '')
			{
				$form->logError(\XF::phrase('please_enter_valid_title'), 'title');
			}
		});

		$form->apply(function () use ($genre, $extraInput): void
		{
			$titlePhrase = $genre->getMasterPhrase('title');
			$titlePhrase->phrase_text = $extraInput['title'];
			$titlePhrase->save();

			if ($extraInput['description'] !== '')
			{
				$descPhrase = $genre->getMasterPhrase('desc');
				$descPhrase->phrase_text = $extraInput['description'];
				$descPhrase->save();
			}
			elseif ($genre->MasterDescription)
			{
				$genre->MasterDescription->delete();
			}
		});

		return $form;
	}

	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params['genre_id'])
		{
			$genre = $this->assertGenreExists($params['genre_id']);
		}
		else
		{
			/** @var Genre $genre */
			$genre = $this->em()->create('cv6\RPG:Genre');
		}

		$this->genreSaveProcess($genre)->run();

		/** @var PropertyService $genreService */
		$genreService = $this->service('cv6\RPG:Genre\PropertyService');

		if ($genre->property_id == 0 || !$genre->Property)
		{
			$property = $genreService->createProperty($genre);
			$genre->property_id = $property->property_id;
			$genre->save();
		}
		else
		{
			$property = $genre->Property;
			$property->title = \XF::phrase('cv6_rpg_genre_layout_property_title', ['title' => $genre->title]);
			$property->display_order = 1000 + $genre->display_order;
			$property->property_name = Genre::PROPERTY . $genre->class;
			$property->save();
		}

		$genreService->clearCache();

		return $this->redirect($this->buildLink('rpg/genre'));
	}

	public function actionProperty(ParameterBag $params): AbstractReply
	{
		$genre = $this->assertGenreExists($params['genre_id']);
		if (!$genre->Property)
		{
			/** @var PropertyService $genreService */
			$genreService = $this->service('cv6\RPG:Genre\PropertyService');
			$property = $genreService->createProperty($genre);

			$genre->property_id = $property->property_id;
			$genre->save();
		}

		return $this->redirect($this->buildLink('rpg/genre'));
	}

	public function actionRebuildProperties(): AbstractReply
	{
		if ($this->isPost())
		{
			/** @var PropertyService $genreService */
			$genreService = $this->service('cv6\RPG:Genre\PropertyService');
			$error = null;
			$genreService->rebuildPropertys($error);
			if ($error)
			{
				return $this->error($error);
			}
			return $this->redirect($this->buildLink('rpg/genre'));
		}

		return $this->view('cv6\RPG:Genre\Rebuild', 'cv6_rpg_genre_rebuild');
	}

	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$genre = $this->assertGenreExists($params['genre_id']);
		$genreRepo = $this->getGenreRepo();

		$viewParams = [];

		if ($this->isPost())
		{
			$reassignGenreId = $this->filter('reassign_genre_id', 'uint', 0);
			if ($reassignGenreId > 0 && $reassignGenreId !== (int)$genre->genre_id)
			{
				$this->assertGenreExists($reassignGenreId);
				$genreRepo->reassignGenreRelations((int)$genre->genre_id, $reassignGenreId);
			}

			if ($genre->Property)
			{
				$genre->Property->delete();
			}

			/** @var DeletePlugin $plugin */
			$plugin = $this->plugin(DeletePlugin::class);
			$reply = $plugin->actionDelete(
				$genre,
				$this->buildLink('rpg/genre/delete', $genre),
				$this->buildLink('rpg/genre/edit', $genre),
				$this->buildLink('rpg/genre'),
				$genre->title,
				'cv6_rpg_genre_delete',
				$viewParams
			);

			if ($reassignGenreId > 0)
			{
				$genreRepo->rebuildGenreCounters($reassignGenreId);
			}

			return $reply;
		}

		$usedTotal = (int)$genre->setting_count + (int)$genre->map_count + (int)$genre->wireset_count;
		if ($usedTotal > 0)
		{
			$genres = $genreRepo->findGenreForList()->fetch();
			$otherGenres = $genres->filter(function (Genre $item) use ($genre): bool
			{
				return (int)$item->genre_id !== (int)$genre->genre_id;
			});

			if ($otherGenres->count() === 0)
			{
				return $this->error(\XF::phrase('cv6_rpg_genre_cannot_delete_only_genre_in_use'));
			}

			$viewParams = [
				'used' => $usedTotal,
				'other' => $otherGenres,
			];
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$genre,
			$this->buildLink('rpg/genre/delete', $genre),
			$this->buildLink('rpg/genre/edit', $genre),
			$this->buildLink('rpg/genre'),
			$genre->title,
			'cv6_rpg_genre_delete',
			$viewParams
		);
	}

	public function actionSort(): AbstractReply
	{
		$genres = $this->getGenreRepo()->findGenreForList()->fetch();

		if ($this->isPost())
		{
			$sortData = $this->filter('genres', 'json-array');
			/** @var SortPlugin $sorter */
			$sorter = $this->plugin(SortPlugin::class);
			$sorter->sortFlat($sortData, $genres, ['jump' => 10]);

			$this->getGenreRepo()->alignDisplayOrder();

			return $this->redirect($this->buildLink('rpg/genre'));
		}

		return $this->view('cv6\RPG:Genre\Sort', 'cv6_rpg_genre_sort', [
			'genres' => $genres,
		]);
	}

	public function actionSortAbc(): AbstractReply
	{
		/** @var SortService $sortService */
		$sortService = $this->service('cv6\RPG:Genre\SortService');
		$sortService->sortAbc();

		return $this->redirect($this->buildLink('rpg/genre'));
	}

	protected function assertGenreExists($genreId, $with = 'Property', $phraseKey = null): Genre
	{
		/** @var Genre $genre */
		$genre = $this->assertRecordExists('cv6\RPG:Genre', $genreId, $with, $phraseKey);
		return $genre;
	}

	protected function getGenreRepo(): GenreRepository
	{
		/** @var GenreRepository $repo */
		$repo = $this->repository('cv6\RPG:Genre');
		return $repo;
	}
}
