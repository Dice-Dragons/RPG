<?php

declare(strict_types=1);

namespace cv6\RPG\Cli\Command\Rebuild;

use cv6\Core\Cli\Command\cv6CommandTrait;
use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildGenreCounter extends AbstractRebuildCommand
{
	use cv6CommandTrait;

	protected function getInternalShortCode(): string
	{
		return 'rpg';
	}

	protected function getRebuildName(): string
	{
		return 'rebuild-genre-counter';
	}

	protected function getRebuildDescription(): string
	{
		return 'Rebuilds the RPG Suite genre counters (settings, battlemaps, wiresets)';
	}

	protected function getRebuildClass(): string
	{
		return 'cv6\RPG:GenreCounterRebuild';
	}
}
