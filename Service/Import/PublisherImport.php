<?php

declare(strict_types=1);

namespace cv6\RPG\Service\Import;

use cv6\RPG\Entity\Publisher;
use XF\Service\AbstractService;

class PublisherImport extends AbstractService
{
	public function parseUploadedFile(\XF\Http\Upload $upload, array &$errors = []): ?array
	{
		if (!$upload->isValid($errors))
		{
			return null;
		}

		$file = $upload->getTempFile();
		$fileName = $upload->getFileName();

		if (str_ends_with(strtolower($fileName), '.zip'))
		{
			return $this->parseZipArchive($file, $errors);
		}

		$content = file_get_contents($file);
		return $this->parseContent($content, $errors);
	}

	public function parseZipArchive(string $zipFilePath, array &$errors = []): ?array
	{
		$zip = new \ZipArchive();
		if ($zip->open($zipFilePath) !== true)
		{
			$errors[] = \XF::phrase('provided_file_is_not_valid_zip');
			return null;
		}

		$jsonContent = null;
		for ($i = 0; $i < $zip->numFiles; $i++)
		{
			$stat = $zip->statIndex($i);
			$entryName = $stat['name'];
			if (str_ends_with(strtolower($entryName), '.json'))
			{
				$jsonContent = $zip->getFromIndex($i);
				break;
			}
		}

		if ($jsonContent === null)
		{
			$zip->close();
			$errors[] = \XF::phrase('no_items_could_be_found_in_file');
			return null;
		}

		$publishers = $this->parseContent($jsonContent, $errors);
		if (!$publishers)
		{
			$zip->close();
			return null;
		}

		$assetDir = \XF::getRootDirectory() . '/data/assets/cv6publisher';
		if (!is_dir($assetDir))
		{
			\XF\Util\File::createDirectory($assetDir, 0777);
		}

		foreach ($publishers as &$pub)
		{
			$logoUrl = $pub['logo_url'] ?? '';
			if ($logoUrl && (str_starts_with($logoUrl, 'images/') || !str_contains($logoUrl, '/')))
			{
				$imageFileName = basename($logoUrl);
				$zipEntry = 'images/' . $imageFileName;

				$imgStream = $zip->getFromName($zipEntry);
				if ($imgStream === false)
				{
					$imgStream = $zip->getFromName($imageFileName);
				}

				if ($imgStream !== false)
				{
					$targetFile = $assetDir . '/' . $imageFileName;
					file_put_contents($targetFile, $imgStream);
					$pub['logo_url'] = 'data/assets/cv6publisher/' . $imageFileName;
				}
				else
				{
					$pub['logo_url'] = '';
				}
			}
		}
		unset($pub);

		$zip->close();
		return $publishers;
	}

	public function parseContent(string $content, array &$errors = []): ?array
	{
		$content = trim($content);

		if (str_starts_with($content, '{') || str_starts_with($content, '['))
		{
			$json = json_decode($content, true);
			if (is_array($json))
			{
				if (isset($json['publishers']) && is_array($json['publishers']))
				{
					return $json['publishers'];
				}
				if (isset($json[0]) && is_array($json[0]))
				{
					return $json;
				}
			}
		}

		$errors[] = \XF::phrase('provided_file_is_not_valid_json');
		return null;
	}

	public function importPublishers(array $publishersData, array &$errors = []): array
	{
		$imported = [];
		$db = $this->db();
		$db->beginTransaction();

		try
		{
			foreach ($publishersData as $item)
			{
				$title = trim($item['title'] ?? '');
				if ($title === '')
				{
					continue;
				}

				/** @var Publisher|null $existing */
				$existing = $this->em()->findOne('cv6\RPG:Publisher', ['title' => $title]);
				if (!$existing && !empty($item['url_slug']))
				{
					$existing = $this->em()->findOne('cv6\RPG:Publisher', ['url_slug' => $item['url_slug']]);
				}

				$publisher = $existing ?: $this->em()->create('cv6\RPG:Publisher');
				$publisher->title = $title;
				if (!empty($item['url_slug']))
				{
					$publisher->url_slug = $item['url_slug'];
				}
				$publisher->description = (string)($item['description'] ?? '');
				$publisher->logo_url = (string)($item['logo_url'] ?? '');
				$publisher->url = (string)($item['url'] ?? '');
				$publisher->active = !empty($item['active']);
				$publisher->display_order = (int)($item['display_order'] ?? 1);

				if ($publisher->save(true, false))
				{
					$imported[] = $publisher;
				}
			}

			$db->commit();
		}
		catch (\Exception $e)
		{
			$db->rollback();
			$errors[] = $e->getMessage();
		}

		return $imported;
	}
}
