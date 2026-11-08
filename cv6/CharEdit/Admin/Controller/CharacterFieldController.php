<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;

class CharacterFieldController extends XFCP_CharacterFieldController
{
	protected function fieldAddEditResponse(\XF\Entity\AbstractField $field)
	{
		$reply = parent::fieldAddEditResponse($field);

		if ($reply instanceof View && \XF::isAddOnActive('cv6/DiceRoller'))
		{
			/** @var \cv6\DiceRoller\Repository\WiresetsRepository $wiresetsRepo */
			$wiresetsRepo = $this->repository('cv6\DiceRoller:Wiresets');
			$reply->setParam('rpgWiresetPairs', $wiresetsRepo->getWiresetsTitlePairs());
		}

		return $reply;
	}

	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractField $field)
	{
		$form = parent::saveAdditionalData($form, $field);

		if ($field->isValidColumn('cv6_rpg_rollable'))
		{
			$isRollable = $this->filter('cv6_rpg_rollable', 'bool');
			$rollConfig = $this->filter('cv6_rpg_roll_config', 'array');

			$cleanConfig = [
				'wireset_override' => trim((string)($rollConfig['wireset_override'] ?? '')),
				'param_target' => trim((string)($rollConfig['param_target'] ?? 'modifier')) ?: 'modifier',
			];

			$form->setup(function () use ($field, $isRollable, $cleanConfig) {
				$field->set('cv6_rpg_rollable', $isRollable);
				$field->set('cv6_rpg_roll_config', $isRollable ? $cleanConfig : []);
			});
		}

		return $form;
	}
}
