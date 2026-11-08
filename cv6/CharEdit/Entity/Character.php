<?php

declare(strict_types=1);

namespace cv6\RPG\cv6\CharEdit\Entity;

use XF\Mvc\Entity\Structure;

class Character extends XFCP_Character
{
	/**
	 * Returns all rollable fields for this character with their current effective values.
	 *
	 * @return array<string, array{field_id: string, title: string, value: int|float|string, formatted_value: string, wireset: string, param_target: string}>
	 */
	public function getRollableFields(): array
	{
		if (!$this->canViewFullCharacterSheet())
		{
			return [];
		}

		$set = $this->custom_fields;
		if (!$set)
		{
			return [];
		}

		$rollableFieldRows = $this->db()->fetchAllKeyed(
			'SELECT field_id, cv6_rpg_rollable, cv6_rpg_roll_config
			FROM xf_cv6_character_field
			WHERE cv6_rpg_rollable = 1
			ORDER BY display_order ASC, field_id ASC',
			'field_id'
		);

		if (empty($rollableFieldRows))
		{
			return [];
		}

		$defSet = $set->getDefinitionSet();
		$settingWireset = $this->Setting ? (string)$this->Setting->cv6_rpg_wireset : '';
		$result = [];

		foreach ($rollableFieldRows as $fieldId => $row)
		{
			if (!isset($defSet[$fieldId]))
			{
				continue;
			}

			$fDef = $defSet[$fieldId];
			if (!empty($fDef->invisible))
			{
				continue;
			}

			$dGroup = $fDef->display_group ?? 'basics';
			if ($dGroup === 'setting' && (int)($fDef->setting_id ?? 0) !== (int)$this->setting_id)
			{
				continue;
			}

			$rawVal = method_exists($set, 'getEffectiveValue')
				? $set->getEffectiveValue($fieldId)
				: $set->getFieldValue($fieldId);

			// Check if there is a companion _mod field (e.g. str -> str_mod) and prefer _mod if numeric
			$modFieldId = $fieldId . '_mod';
			$effectiveModifier = $rawVal;
			if (isset($defSet[$modFieldId]))
			{
				$modVal = method_exists($set, 'getEffectiveValue')
					? $set->getEffectiveValue($modFieldId)
					: $set->getFieldValue($modFieldId);
				if ($modVal !== null && $modVal !== '' && is_numeric($modVal))
				{
					$effectiveModifier = (int)$modVal;
				}
			}

			if ($effectiveModifier === null || $effectiveModifier === '')
			{
				$effectiveModifier = 0;
			}
			else if (is_numeric($effectiveModifier))
			{
				$effectiveModifier = (int)$effectiveModifier;
			}

			$config = [];
			if (!empty($row['cv6_rpg_roll_config']))
			{
				$decoded = is_array($row['cv6_rpg_roll_config'])
					? $row['cv6_rpg_roll_config']
					: json_decode((string)$row['cv6_rpg_roll_config'], true);
				if (is_array($decoded))
				{
					$config = $decoded;
				}
			}

			$wiresetTag = !empty($config['wireset_override'])
				? (string)$config['wireset_override']
				: $settingWireset;
			$paramTarget = !empty($config['param_target'])
				? (string)$config['param_target']
				: 'modifier';

			$formatted = is_numeric($effectiveModifier)
				? (((int)$effectiveModifier >= 0 ? '+' : '') . (int)$effectiveModifier)
				: (string)$effectiveModifier;

			$result[$fieldId] = [
				'field_id' => (string)$fieldId,
				'title' => (string)$fDef->title,
				'value' => $effectiveModifier,
				'raw_value' => $rawVal,
				'formatted_value' => $formatted,
				'wireset' => $wiresetTag,
				'param_target' => $paramTarget,
			];
		}

		return $result;
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		$structure->columns['cv6_rpg_dice_set'] = [
			'type' => self::STR,
			'maxLength' => 50,
			'nullable' => true,
			'default' => null,
		];

		$structure->getters['rollable_fields'] = 'getRollableFields';

		return $structure;
	}
}
