<?php

declare(strict_types=1);

namespace cv6\RPG\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

class Wiresets extends AbstractOption
{
	public static function renderCheckbox(Option $option, array $htmlParams): string
	{
		$choices = [];

		if (\XF::isAddOnActive('cv6/DiceRoller') && \XF::db()->getSchemaManager()->tableExists('xf_cv6_wiresets'))
		{
			/** @var \cv6\DiceRoller\Repository\WiresetsRepository $wiresetsRepo */
			$wiresetsRepo = \XF::repository('cv6\DiceRoller:Wiresets');
			$wiresets = $wiresetsRepo->findWiresetsForList()->fetch();

			foreach ($wiresets as $tag => $wireset)
			{
				$label = (string)$wireset->title . ' (' . $tag . ')';
				if (!$wireset->active)
				{
					$label .= ' [' . \XF::phrase('disabled') . ']';
				}
				$choices[(string)$tag] = $label;
			}
		}

		$selectedValue = $option->option_value;
		if (!is_array($selectedValue))
		{
			$selectedValue = is_string($selectedValue) && $selectedValue !== ''
				? array_values(array_filter(array_map('trim', explode(',', $selectedValue))))
				: [];
		}

		return self::getCheckboxRow($option, $htmlParams, $choices, $selectedValue);
	}
}
