<?php

declare(strict_types=1);

namespace cv6\RPG\Service\Genre;

use cv6\RPG\Entity\Genre;

class SortService extends \XF\Service\AbstractService
{
	protected ?Genre $genre = null;

	public function sortAbc(): void
	{
		$this->db()->update(
			'xf_cv6_genre',
			['display_order' => 100],
			false
		);
	}
}
