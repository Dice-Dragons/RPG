<?php

declare(strict_types=1);

namespace cv6\RPG\Finder;

use cv6\RPG\Entity\CampaignContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<CampaignContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<CampaignContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method CampaignContent|null fetchOne(?int $offset = null)
 * @extends Finder<CampaignContent>
 */
class CampaignContentFinder extends Finder
{
	public function forCampaign(int $campaignId, ?string $contentType = null, bool $onlyPlayersVisible = false): self
	{
		$this->where('campaign_id', $campaignId);

		if ($contentType !== null)
		{
			$this->where('content_type', $contentType);
		}

		if ($onlyPlayersVisible)
		{
			$this->where('visibility', CampaignContent::VISIBILITY_PLAYERS);
		}

		$this->order(['display_order', 'link_id']);
		return $this;
	}
}
