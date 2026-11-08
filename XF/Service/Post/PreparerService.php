<?php

declare(strict_types=1);

namespace cv6\RPG\XF\Service\Post;

class PreparerService extends XFCP_PreparerService
{
	public function afterInsert()
	{
		parent::afterInsert();
		$this->syncPostCharacterToRolls();
	}

	public function afterUpdate()
	{
		parent::afterUpdate();
		$this->syncPostCharacterToRolls();
	}

	protected function syncPostCharacterToRolls(): void
	{
		$post = $this->post;
		if (!$post || !$post->post_id)
		{
			return;
		}

		if ($post->isValidColumn('cv6_character_id'))
		{
			$charId = (int)$post->get('cv6_character_id');
			if ($charId > 0)
			{
				\XF::db()->update(
					'xf_cv6_rolls',
					['cv6_character_id' => $charId],
					'post_id = ? AND cv6_character_id = 0',
					[$post->post_id]
				);
			}
		}
	}
}
