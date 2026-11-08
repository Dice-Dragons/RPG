<?php

declare(strict_types=1);

namespace cv6\RPG\Finder;

use cv6\RPG\Entity\Publisher;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Publisher> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Publisher> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Publisher|null fetchOne(?int $offset = null)
 * @extends Finder<Publisher>
 */
class PublisherFinder extends Finder
{
}
