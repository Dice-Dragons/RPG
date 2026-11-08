<?php

declare(strict_types=1);

namespace cv6\RPG\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class PublisherRepository extends Repository
{
	public function findPublisherForList($onlyActive = false, $byTitle = false): Finder
	{
		$finder = $this->finder('cv6\RPG:Publisher');
		if ($byTitle === true)
		{
			$finder->order('title');
		}
		else
		{
			$finder->order('display_order')->order('title');
		}
		if ($onlyActive === true)
		{
			$finder->where('active', true);
		}
		return $finder;
	}

	public function getPublisherListData($onlyActive = false): array
	{
		$publisher = $this->findPublisherForList($onlyActive)->fetch();

		return [
			'totalpublisher' => $publisher->count(),
			'publisher' => $publisher,
		];
	}

	public function getPublisherData()
	{
		return $this->findPublisherForList()->fetch();
	}

	public function getPublisherDataAlphabetical($origin = false): array
	{
		$abcPublisher = [];
		$allPublishers = $this->findPublisherForList(true, $origin)->fetch();
		foreach ($allPublishers as $publisher)
		{
			$initial = strtoupper(substr((string)$publisher->title, 0, 1));
			if (ctype_digit($initial))
			{
				$initial = '#';
			}
			elseif (!ctype_alnum($initial))
			{
				$initial = '?';
			}
			if (!array_key_exists($initial, $abcPublisher))
			{
				$abcPublisher[$initial] = [];
			}
			$abcPublisher[$initial][] = $publisher;
		}
		ksort($abcPublisher);
		return [
			'totalpublisher' => $allPublishers->count(),
			'publisher' => $abcPublisher,
		];
	}

	public function getPublisherTitlePairs(): array
	{
		return $this->findPublisherForList()->order('display_order')->order('title')->fetch()->pluckNamed('title', 'publisher_id');
	}

	public function sortAbc(): void
	{
		$this->db()->update(
			'xf_cv6_publisher',
			['display_order' => 10],
			false
		);
	}
}
