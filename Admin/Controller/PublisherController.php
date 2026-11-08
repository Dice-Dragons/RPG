<?php

declare(strict_types=1);

namespace cv6\RPG\Admin\Controller;

use cv6\RPG\Entity\Publisher;
use cv6\RPG\Repository\PublisherRepository;
use cv6\RPG\Service\Export\Publisher as PublisherExportService;
use cv6\RPG\Service\Import\PublisherImport;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\DeletePlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\SortPlugin;
use XF\ControllerPlugin\TogglePlugin;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class PublisherController extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		if (!\XF::visitor()->hasAdminPermission('cv6ManageRpg') && !\XF::visitor()->hasAdminPermission('cv6ManagePublisher'))
		{
			$this->assertAdminPermission('cv6ManageRpg');
		}
	}

	public function actionIndex(): AbstractReply
	{
		$publisherData = $this->getPublisherRepo()->getPublisherListData();

		$viewParams = [
			'publisherData' => $publisherData,
			'exportView' => $this->filter('export', 'bool'),
		];
		return $this->view('cv6\RPG:Publisher\Listing', 'cv6_rpg_publisher_list', $viewParams);
	}

	public function actionToggle(): AbstractReply
	{
		/** @var TogglePlugin $plugin */
		$plugin = $this->plugin(TogglePlugin::class);
		return $plugin->actionToggle('cv6\RPG:Publisher');
	}

	public function publisherAddEdit(Publisher $publisher): AbstractReply
	{
		return $this->view('cv6\RPG:Publisher\Edit', 'cv6_rpg_publisher_edit', [
			'publisher' => $publisher,
		]);
	}

	public function actionEdit(ParameterBag $params): AbstractReply
	{
		$publisher = $this->assertPublisherExists($params['publisher_id']);
		return $this->publisherAddEdit($publisher);
	}

	public function actionAdd(): AbstractReply
	{
		/** @var Publisher $publisher */
		$publisher = $this->em()->create('cv6\RPG:Publisher');
		return $this->publisherAddEdit($publisher);
	}

	protected function publisherSaveProcess(Publisher $publisher): FormAction
	{
		$entityInput = $this->filter([
			'title' => 'str',
			'url_slug' => 'str',
			'url' => 'str',
			'active' => 'uint',
			'display_order' => 'uint',
			'logo_url' => 'str',
			'license_notice' => 'str',
		]);

		if ($entityInput['url_slug'] === '')
		{
			$entityInput['url_slug'] = null;
		}

		/** @var EditorPlugin $editorPlugin */
		$editorPlugin = $this->plugin(EditorPlugin::class);
		$description = $editorPlugin->fromInput('description');

		$form = $this->formAction();
		$form->basicEntitySave($publisher, $entityInput);
		$form->setup(function () use ($publisher, $description): void
		{
			$publisher->description = $description;
		});

		return $form;
	}

	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params['publisher_id'])
		{
			$publisher = $this->assertPublisherExists($params['publisher_id']);
		}
		else
		{
			/** @var Publisher $publisher */
			$publisher = $this->em()->create('cv6\RPG:Publisher');
		}

		$this->publisherSaveProcess($publisher)->run();

		return $this->redirect($this->buildLink('rpg/publisher'));
	}

	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$publisherId = (int)$params['publisher_id'];
		$publisher = $this->assertPublisherExists($publisherId);
		$publisherRepo = $this->getPublisherRepo();

		$viewParams = [];

		if ($this->isPost())
		{
			$reassignPublisherId = $this->filter('reassign_publisher_id', 'uint');
			if ($reassignPublisherId > 0 && $reassignPublisherId !== $publisherId)
			{
				$this->assertPublisherExists($reassignPublisherId);
				$publisherRepo->reassignPublisherRelations($publisherId, $reassignPublisherId);
			}
		}
		else
		{
			$usedSettings = $publisherRepo->countUsedSettings($publisherId);
			if ($usedSettings > 0)
			{
				$publishers = $publisherRepo->findPublisherForList(true)->fetch();
				$otherPublishers = $publishers->filter(function (Publisher $item) use ($publisher): bool
				{
					return (int)$item->publisher_id !== (int)$publisher->publisher_id;
				});
				if ($otherPublishers->count() === 0)
				{
					return $this->error(\XF::phrase('cv6_rpg_publisher_cannot_delete_only_publisher_in_use'));
				}
				$viewParams = [
					'used' => $usedSettings,
					'other' => $otherPublishers,
				];
			}
		}

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDelete(
			$publisher,
			$this->buildLink('rpg/publisher/delete', $publisher),
			$this->buildLink('rpg/publisher/edit', $publisher),
			$this->buildLink('rpg/publisher'),
			$publisher->title,
			'cv6_rpg_publisher_delete',
			$viewParams
		);
	}

	public function actionSort(): AbstractReply
	{
		$publisherRepo = $this->getPublisherRepo();
		$publishers = $publisherRepo->getPublisherData();

		if ($this->isPost())
		{
			$sortData = $this->filter('publishers', 'json-array');

			/** @var SortPlugin $sorter */
			$sorter = $this->plugin(SortPlugin::class);
			$sorter->sortFlat($sortData, $publishers, ['jump' => 10]);

			return $this->redirect($this->buildLink('rpg/publisher'));
		}

		return $this->view('cv6\RPG:Publisher\Sort', 'cv6_rpg_publisher_sort', [
			'publisher' => $publishers,
		]);
	}

	public function actionAbcSort(): AbstractReply
	{
		$this->getPublisherRepo()->sortAbc();
		return $this->redirect($this->buildLink('rpg/publisher'));
	}

	public function actionExport(): AbstractReply
	{
		$publisherIds = $this->filter('export', 'array-uint');
		if (empty($publisherIds))
		{
			return $this->error(\XF::phrase('cv6_rpg_please_select_at_least_one_publisher_to_export'));
		}

		$publishers = $this->finder('cv6\RPG:Publisher')
			->where('publisher_id', $publisherIds)
			->order(['display_order', 'title'])
			->fetch();

		if (!$publishers->count())
		{
			return $this->error(\XF::phrase('cv6_rpg_please_select_at_least_one_publisher_to_export'));
		}

		$withImages = $this->filter('with_images', 'bool');

		/** @var PublisherExportService $exportService */
		$exportService = $this->service('cv6\RPG:Export\Publisher');

		$this->setResponseType('raw');

		if ($withImages)
		{
			$zipResult = $exportService->exportPublishersZip($publishers);
			$fileName = $publishers->count() === 1
				? 'publisher-' . ($publishers->first()->url_slug ?: $publishers->first()->publisher_id) . '.zip'
				: 'publishers-export.zip';

			return $this->view('cv6\RPG:Export\Publisher', '', [
				'isZip' => true,
				'zipData' => $zipResult['zipData'],
				'fileName' => $fileName,
			]);
		}

		$exportData = $exportService->exportPublishers($publishers, false);
		$fileName = $publishers->count() === 1
			? 'publisher-' . ($publishers->first()->url_slug ?: $publishers->first()->publisher_id) . '.json'
			: 'publishers-export.json';

		$json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return $this->view('cv6\RPG:Export\Publisher', '', [
			'isZip' => false,
			'json' => $json,
			'fileName' => $fileName,
		]);
	}

	public function actionImport(): AbstractReply
	{
		if ($this->isPost())
		{
			if ($this->filter('confirm', 'bool'))
			{
				$input = $this->filter([
					'import' => 'array-uint',
					'publishers' => 'array',
				]);

				$toImport = [];
				foreach ($input['import'] as $idx)
				{
					if (!empty($input['publishers'][$idx]) && is_array($input['publishers'][$idx]))
					{
						$toImport[] = $input['publishers'][$idx];
					}
				}

				if (empty($toImport))
				{
					return $this->error(\XF::phrase('cv6_rpg_please_select_at_least_one_publisher_to_import'));
				}

				$errors = [];
				/** @var PublisherImport $importer */
				$importer = $this->service('cv6\RPG:Import\PublisherImport');
				$imported = $importer->importPublishers($toImport, $errors);

				if (!empty($errors))
				{
					return $this->error($errors);
				}

				return $this->redirect(
					$this->buildLink('rpg/publisher'),
					\XF::phrase('cv6_rpg_publishers_imported_successfully', ['count' => count($imported)])
				);
			}

			$upload = $this->request->getFile('upload', false, false);
			if (!$upload)
			{
				return $this->error(\XF::phrase('please_upload_valid_file'));
			}

			$errors = [];
			/** @var PublisherImport $importer */
			$importer = $this->service('cv6\RPG:Import\PublisherImport');
			$publishers = $importer->parseUploadedFile($upload, $errors);

			if ($errors)
			{
				return $this->error($errors);
			}

			if (empty($publishers))
			{
				return $this->error(\XF::phrase('no_items_could_be_found_in_file'));
			}

			return $this->view('cv6\RPG:Publisher\ImportForm', 'cv6_rpg_publisher_import_form', [
				'publishers' => $publishers,
			]);
		}

		return $this->view('cv6\RPG:Publisher\Import', 'cv6_rpg_publisher_import');
	}

	protected function assertPublisherExists($id, $with = null, $phraseKey = null): Publisher
	{
		/** @var Publisher $publisher */
		$publisher = $this->assertRecordExists('cv6\RPG:Publisher', $id, $with, $phraseKey);
		return $publisher;
	}

	protected function getPublisherRepo(): PublisherRepository
	{
		/** @var PublisherRepository $repo */
		$repo = $this->repository('cv6\RPG:Publisher');
		return $repo;
	}
}
