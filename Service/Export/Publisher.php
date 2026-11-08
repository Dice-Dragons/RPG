<?php

declare(strict_types=1);

namespace cv6\RPG\Service\Export;

use cv6\RPG\Entity\Publisher as PublisherEntity;
use XF\Mvc\Entity\Finder;
use XF\Service\AbstractService;

class Publisher extends AbstractService
{
	public function exportPublishers($publishers, bool $withImages = false): array
	{
		if ($publishers instanceof Finder)
		{
			$publishers = $publishers->fetch();
		}

		$exportData = [];
		foreach ($publishers as $publisher)
		{
			if ($publisher instanceof PublisherEntity)
			{
				$exportData[] = $this->exportPublisher($publisher, $withImages);
			}
			elseif (is_array($publisher))
			{
				$exportData[] = $this->exportPublisherFromArray($publisher, $withImages);
			}
		}

		if (empty($exportData))
		{
			throw new \XF\PrintableException(\XF::phrase('cv6_rpg_please_select_at_least_one_publisher_to_export')->render());
		}

		return [
			'export_type' => 'cv6_publishers',
			'version' => \XF::$versionId,
			'publishers' => $exportData,
		];
	}

	public function exportPublishersZip($publishers): array
	{
		if ($publishers instanceof Finder)
		{
			$publishers = $publishers->fetch();
		}

		$tempFile = \XF\Util\File::getTempFile();
		$zip = new \ZipArchive();
		if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true)
		{
			throw new \XF\PrintableException('Could not create temporary zip archive.');
		}

		$exportData = [];
		$client = \XF::app()->http()->client();

		foreach ($publishers as $publisher)
		{
			$pData = $this->exportPublisher($publisher, true);
			$logoUrl = (string)$publisher->logo_url;

			if ($logoUrl !== '')
			{
				$imageContent = null;
				$pathOnly = parse_url($logoUrl, PHP_URL_PATH);
				$filename = $pathOnly ? basename($pathOnly) : '';

				$localPath = \XF::getRootDirectory() . '/' . ltrim($logoUrl, '/');
				if (file_exists($localPath) && is_file($localPath))
				{
					$imageContent = file_get_contents($localPath);
				}
				elseif (str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://'))
				{
					try
					{
						$response = $client->get($logoUrl, ['timeout' => 5]);
						if ($response->getStatusCode() === 200)
						{
							$imageContent = (string)$response->getBody();
						}
					}
					catch (\Exception $e)
					{
					}
				}

				if ($imageContent !== null && $filename !== '')
				{
					$zip->addFromString('images/' . $filename, $imageContent);
					$pData['logo_url'] = 'images/' . $filename;
				}
				else
				{
					$pData['logo_url'] = '';
				}
			}
			else
			{
				$pData['logo_url'] = '';
			}

			$exportData[] = $pData;
		}

		$json = json_encode([
			'export_type' => 'cv6_publishers',
			'version' => \XF::$versionId,
			'publishers' => $exportData,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$zip->addFromString('publishers.json', $json);
		$zip->close();

		$zipData = file_get_contents($tempFile);
		@unlink($tempFile);

		return [
			'zipData' => $zipData,
			'count' => count($exportData),
		];
	}

	public function exportPublisher(PublisherEntity $publisher, bool $withImages = false): array
	{
		return [
			'title' => (string)$publisher->title,
			'url_slug' => (string)($publisher->url_slug ?: ''),
			'description' => (string)$publisher->description,
			'logo_url' => $withImages ? (string)($publisher->logo_url ?: '') : '',
			'url' => (string)($publisher->url ?: ''),
			'active' => (bool)$publisher->active,
			'display_order' => (int)$publisher->display_order,
		];
	}

	public function exportPublisherFromArray(array $publisher, bool $withImages = false): array
	{
		return [
			'title' => (string)($publisher['title'] ?? ''),
			'url_slug' => (string)($publisher['url_slug'] ?? ''),
			'description' => (string)($publisher['description'] ?? ''),
			'logo_url' => $withImages ? (string)($publisher['logo_url'] ?? '') : '',
			'url' => (string)($publisher['url'] ?? ''),
			'active' => !empty($publisher['active']),
			'display_order' => (int)($publisher['display_order'] ?? 1),
		];
	}
}
