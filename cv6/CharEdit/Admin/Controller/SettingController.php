<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Admin\Controller;

use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;

class SettingController extends XFCP_SettingController
{
	public function settingAddEdit(\cv6\CharEdit\Entity\Setting $setting)
	{
		$reply = parent::settingAddEdit($setting);

		if ($reply instanceof View && \XF::isAddOnActive('cv6/DiceRoller'))
		{
			/** @var \cv6\DiceRoller\Repository\WiresetsRepository $wiresetsRepo */
			$wiresetsRepo = $this->repository('cv6\DiceRoller:Wiresets');
			$reply->setParam('rpgWiresetPairs', $wiresetsRepo->getWiresetsTitlePairs());
		}

		return $reply;
	}

	protected function settingSaveProcess(\cv6\CharEdit\Entity\Setting $setting)
	{
		$form = parent::settingSaveProcess($setting);

		if ($setting->isValidColumn('cv6_rpg_wireset'))
		{
			$wiresetTag = $this->filter('cv6_rpg_wireset', 'str');
			$form->setup(function () use ($setting, $wiresetTag) {
				$setting->set('cv6_rpg_wireset', $wiresetTag);
			});
		}

		return $form;
	}
}
