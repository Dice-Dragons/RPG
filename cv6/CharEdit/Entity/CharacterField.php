<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Entity;

use XF\Mvc\Entity\Structure;

class CharacterField extends XFCP_CharacterField
{
	public function isRpgRollable(): bool
	{
		return (bool)$this->cv6_rpg_rollable;
	}

	public function getRpgRollConfig(): array
	{
		return is_array($this->cv6_rpg_roll_config) ? $this->cv6_rpg_roll_config : [];
	}

	public function getRpgWiresetOverride(): string
	{
		$cfg = $this->getRpgRollConfig();
		return (string)($cfg['wireset_override'] ?? '');
	}

	public function getRpgParamTarget(): string
	{
		$cfg = $this->getRpgRollConfig();
		$target = (string)($cfg['param_target'] ?? 'modifier');
		return $target !== '' ? $target : 'modifier';
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['cv6_rpg_rollable'] = [
			'type' => self::BOOL,
			'default' => false,
		];
		$structure->columns['cv6_rpg_roll_config'] = [
			'type' => self::JSON_ARRAY,
			'default' => [],
		];

		$structure->getters['is_rpg_rollable'] = 'isRpgRollable';
		$structure->getters['rpg_roll_config'] = 'getRpgRollConfig';

		return $structure;
	}
}
