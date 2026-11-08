<?php

declare(strict_types=1);

namespace cv6\RPG\Cli\Command;

use cv6\RPG\Setup;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AbstractCommand;

class SyncSchema extends AbstractCommand
{
	protected function configure(): void
	{
		$this
			->setName('dd-rpg:sync-schema')
			->setDescription('Synchronizes [DD] RPG Suite bridge columns across installed RPG modules.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$output->writeln('<info>Synchronizing [DD] RPG Suite database schema & bridge columns...</info>');

		$addOn = \XF::app()->addOnManager()->getById('cv6/RPG');
		if (!$addOn)
		{
			$output->writeln('<error>Add-on cv6/RPG is not found.</error>');
			return 1;
		}

		$setup = new Setup($addOn, \XF::app());
		$setup->syncBridgeSchema();

		$output->writeln('<info>Schema synchronization completed successfully.</info>');
		return 0;
	}
}
