<?php

declare(strict_types=1);

namespace cv6\RPG\Finder;

use cv6\RPG\Entity\Genre;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Genre> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Genre> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Genre|null fetchOne(?int $offset = null)
 * @extends Finder<Genre>
 */
class GenreFinder extends Finder
{
	public function onlyActive(): self
	{
		$this->where('active', 1);
		return $this;
	}

	public function byGeneratorType(string $generatorType): self
	{
		$this->where('generator_type', $generatorType);
		return $this;
	}
}
