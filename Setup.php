<?php

declare(strict_types=1);

namespace cv6\RPG;

use cv6\Core\Setup\SetupTrait;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	use SetupTrait;

	public function installStep1(): void
	{
		$this->createTables();
	}

	public function installStep2(): void
	{
		$this->dropColumnIfExists('xf_cv6_genre', 'generator_type');

		$this->addColumnsIfMissing('xf_cv6_genre', [
			'icon' => function (Alter $table): void
			{
				$table->addColumn('icon', 'varchar', 100)->setDefault('')->after('class');
			},
			'map_count' => function (Alter $table): void
			{
				$table->addColumn('map_count', 'int')->setDefault(0)->after('setting_count');
			},
			'wireset_count' => function (Alter $table): void
			{
				$table->addColumn('wireset_count', 'int')->setDefault(0)->after('map_count');
			},
		]);

		$this->seedAndAlignGenres();

		$this->addColumnIfMissing('xf_cv6_setting', 'cv6_rpg_wireset', function (Alter $table): void
		{
			$table->addColumn('cv6_rpg_wireset', 'varchar', 25)->setDefault('');
		});

		$this->addColumnIfMissing('xf_cv6_character', 'cv6_rpg_dice_set', function (Alter $table): void
		{
			$table->addColumn('cv6_rpg_dice_set', 'varchar', 50)->nullable()->setDefault(null);
		});

		$this->addColumnsIfMissing('xf_cv6_character_field', [
			'cv6_rpg_rollable' => function (Alter $table): void
			{
				$table->addColumn('cv6_rpg_rollable', 'bool')->setDefault(0);
			},
			'cv6_rpg_roll_config' => function (Alter $table): void
			{
				$table->addColumn('cv6_rpg_roll_config', 'mediumblob')->nullable()->setDefault(null);
			},
		]);

		$this->addColumnIfMissing('xf_cv6_rolls', 'cv6_character_id', function (Alter $table): void
		{
			$table->addColumn('cv6_character_id', 'int')->setDefault(0)->after('user_id');
			$table->addKey('cv6_character_id');
		});
	}

	public function postInstall(array &$stateChanges): void
	{
		$this->ensureDefaultWidgets();
		$this->reassignGenreProperties();
	}

	public function postUpgrade($previousVersion, array &$stateChanges): void
	{
		$this->ensureDefaultWidgets();
		$this->reassignGenreProperties();
	}

	protected function reassignGenreProperties(): void
	{
		if (!$this->tableExists('xf_cv6_genre'))
		{
			return;
		}

		try
		{
			/** @var \cv6\RPG\Service\Genre\PropertyService $propertyService */
			$propertyService = \XF::service('cv6\RPG:Genre\PropertyService');
			$propertyService->reassignProperties();
		}
		catch (\Exception $e)
		{
		}
	}

	public function uninstallStep1(): void
	{
		$this->deleteWidget('cv6_rpg_dashboard_genres');
		$this->dropColumnIfExists('xf_cv6_rolls', 'cv6_character_id');
		$this->dropColumnIfExists('xf_cv6_character_field', ['cv6_rpg_rollable', 'cv6_rpg_roll_config']);
		$this->dropColumnIfExists('xf_cv6_character', 'cv6_rpg_dice_set');
		$this->dropColumnIfExists('xf_cv6_setting', 'cv6_rpg_wireset');
		$this->dropColumnIfExists('xf_cv6_genre', ['icon', 'map_count', 'wireset_count']);
	}

	public function uninstallStep2(): void
	{
		$this->dropTables();
	}

	public function syncBridgeSchema(): void
	{
		$this->installStep1();
		$this->installStep2();
		$this->ensureDefaultWidgets();
		$this->reassignGenreProperties();
	}

	protected function ensureDefaultWidgets(): void
	{
		$db = $this->db();
		$defExists = $db->fetchOne(
			"SELECT definition_id FROM xf_widget_definition WHERE definition_id = 'cv6_rpg_genres'"
		);
		$posExists = $db->fetchOne(
			"SELECT position_id FROM xf_widget_position WHERE position_id = 'cv6_rpg_dashboard_sidenav'"
		);
		$widgetExists = $db->fetchOne(
			"SELECT widget_id FROM xf_widget WHERE widget_key = 'cv6_rpg_dashboard_genres'"
		);

		if ($defExists && $posExists && !$widgetExists)
		{
			$this->createWidget(
				'cv6_rpg_dashboard_genres',
				'cv6_rpg_genres',
				[
					'positions' => [
						'cv6_rpg_dashboard_sidenav' => 10,
					],
					'options' => [
						'layout' => 'sidenav',
						'target_route' => 'rpg',
						'count_field' => 'total_count',
						'show_all' => true,
					],
				],
				'Filter by genre'
			);
		}
		elseif ($widgetExists)
		{
			/** @var \XF\Entity\Widget|null $genreWidget */
			$genreWidget = $this->app()->em()->find('XF:Widget', $widgetExists);
			if ($genreWidget)
			{
				$opts = $genreWidget->options;
				if (($opts['target_route'] ?? '') === 'char/setting/list')
				{
					$opts['target_route'] = 'rpg';
					$opts['count_field'] = 'total_count';
					$genreWidget->options = $opts;
					$genreWidget->save(false);
				}
			}
		}

		$defaultWidgetTitles = [
			'widget.cv6_rpg_dashboard_genres' => 'Filter by genre',
			'widget.cv6_rpg_dashboard_battlemaps' => 'BattleMaps',
			'widget.cv6_rpg_dashboard_handouts' => 'Handouts',
		];
		/** @var \XF\Service\Phrase\CompileService $compileService */
		$compileService = $this->app()->service('XF:Phrase\Compile');
		foreach ($defaultWidgetTitles as $phraseTitle => $defaultText)
		{
			/** @var \XF\Entity\Phrase|null $phrase */
			$phrase = $this->app()->finder('XF:Phrase')
				->where('language_id', 0)
				->where('title', $phraseTitle)
				->fetchOne();
			if ($phrase)
			{
				if ($phrase->phrase_text === '')
				{
					$phrase->phrase_text = $defaultText;
					$phrase->save(false);
				}
				else
				{
					$compileService->recompile($phrase);
				}
			}
		}

		/** @var \XF\Service\Phrase\GroupService $groupService */
		$groupService = $this->app()->service('XF:Phrase\Group');
		$groupService->compilePhraseGroup('widget');

		/** @var \XF\Repository\WidgetRepository $widgetRepo */
		$widgetRepo = $this->app()->repository('XF:Widget');
		$widgetRepo->rebuildWidgetCache();
	}

	protected function seedAndAlignGenres(): void
	{
		if (!$this->tableExists('xf_cv6_genre'))
		{
			return;
		}

		$db = $this->db();
		$sm = $this->schemaManager();

		if ($sm->columnExists('xf_cv6_genre', 'title'))
		{
			$legacyGenres = $db->fetchAll("SELECT genre_id, title FROM xf_cv6_genre WHERE title != '' AND title IS NOT NULL");
			foreach ($legacyGenres as $row)
			{
				$phraseTitle = 'cv6_ce_genre_title_' . (int)$row['genre_id'];
				$db->query("
					INSERT IGNORE INTO xf_phrase
						(language_id, title, phrase_text, global_cache, addon_id, version_id, version_string)
					VALUES
						(0, ?, ?, 0, '', 0, '')
				", [$phraseTitle, $row['title']]);
			}
		}

		if ($sm->columnExists('xf_cv6_genre', 'description'))
		{
			$legacyDescs = $db->fetchAll("SELECT genre_id, description FROM xf_cv6_genre WHERE description != '' AND description IS NOT NULL");
			foreach ($legacyDescs as $row)
			{
				$phraseTitle = 'cv6_ce_genre_desc_' . (int)$row['genre_id'];
				$db->query("
					INSERT IGNORE INTO xf_phrase
						(language_id, title, phrase_text, global_cache, addon_id, version_id, version_string)
					VALUES
						(0, ?, ?, 0, '', 0, '')
				", [$phraseTitle, $row['description']]);
			}
		}

		$this->dropColumnIfExists('xf_cv6_genre', ['title', 'description']);

		$count = (int)$db->fetchOne('SELECT COUNT(*) FROM xf_cv6_genre');
		if ($count === 0)
		{
			$defaultGenres = [
				['genre_id' => 1, 'class' => 'default', 'icon' => 'fa-dice-d20 fad', 'active' => 1, 'property_id' => 0, 'display_order' => 10, 'setting_count' => 0, 'map_count' => 0, 'wireset_count' => 0],
				['genre_id' => 3, 'class' => 'fantasy', 'icon' => 'fa-dragon fad', 'active' => 1, 'property_id' => 0, 'display_order' => 30, 'setting_count' => 0, 'map_count' => 0, 'wireset_count' => 0],
				['genre_id' => 5, 'class' => 'cyberpunk', 'icon' => 'fa-microchip fad', 'active' => 1, 'property_id' => 0, 'display_order' => 20, 'setting_count' => 0, 'map_count' => 0, 'wireset_count' => 0],
				['genre_id' => 6, 'class' => 'scifi', 'icon' => 'fa-rocket fad', 'active' => 1, 'property_id' => 0, 'display_order' => 40, 'setting_count' => 0, 'map_count' => 0, 'wireset_count' => 0],
				['genre_id' => 7, 'class' => 'pa', 'icon' => 'fa-radiation fad', 'active' => 1, 'property_id' => 0, 'display_order' => 50, 'setting_count' => 0, 'map_count' => 0, 'wireset_count' => 0],
				['genre_id' => 8, 'class' => 'horror', 'icon' => 'fa-ghost fad', 'active' => 1, 'property_id' => 0, 'display_order' => 60, 'setting_count' => 0, 'map_count' => 0, 'wireset_count' => 0],
			];

			foreach ($defaultGenres as $row)
			{
				$db->insert('xf_cv6_genre', $row, false, 'class = VALUES(class)');
			}
		}
		else
		{
			$defaultIcons = [
				'default' => 'fa-dice-d20 fad',
				'fantasy' => 'fa-dragon fad',
				'cyberpunk' => 'fa-microchip fad',
				'scifi' => 'fa-rocket fad',
				'pa' => 'fa-radiation fad',
				'horror' => 'fa-ghost fad',
			];
			foreach ($defaultIcons as $class => $icon)
			{
				$db->update('xf_cv6_genre', ['icon' => $icon], "class = ? AND icon = ''", [$class]);
			}
		}

		$defaultTitles = [
			1 => 'Universal',
			3 => 'Fantasy',
			5 => 'Cyberpunk',
			6 => 'Sci-Fi',
			7 => 'Post-Apocalyptic',
			8 => 'Horror',
		];
		foreach ($defaultTitles as $gId => $enTitle)
		{
			$db->query("
				INSERT IGNORE INTO xf_phrase
					(language_id, title, phrase_text, global_cache, addon_id, version_id, version_string)
				VALUES
					(0, ?, ?, 0, '', 0, '')
			", ['cv6_ce_genre_title_' . $gId, $enTitle]);
		}

		if ($this->tableExists('xf_cv6_setting'))
		{
			$db->query("
				UPDATE xf_cv6_genre AS g
				SET setting_count = (
					SELECT COUNT(*)
					FROM xf_cv6_setting AS s
					WHERE s.genre_id = g.genre_id AND s.active = 1
				)
			");
		}

		if ($this->tableExists('xf_cv6_bm_map') && $this->columnExists('xf_cv6_bm_map', 'genre_id'))
		{
			if ($this->columnExists('xf_cv6_bm_map', 'genre'))
			{
				$db->query("
					UPDATE xf_cv6_bm_map AS m
					INNER JOIN xf_cv6_genre AS g ON (g.class = m.genre)
					SET m.genre_id = g.genre_id
					WHERE m.genre_id = 0
				");
			}

			$db->query("
				UPDATE xf_cv6_genre AS g
				SET map_count = (
					SELECT COUNT(*)
					FROM xf_cv6_bm_map AS m
					WHERE m.genre_id = g.genre_id AND m.map_state = 'visible'
				)
			");
		}

		if ($this->tableExists('xf_cv6_wiresets'))
		{
			$this->addColumnIfMissing('xf_cv6_wiresets', 'genre_id', function (Alter $table): void
			{
				$table->addColumn('genre_id', 'int', 10)->unsigned()->setDefault(0)->after('description');
				$table->addKey('genre_id');
			});

			$this->migrateLegacyWiresetGroups();

			$db->query("
				UPDATE xf_cv6_genre AS g
				SET wireset_count = (
					SELECT COUNT(*)
					FROM xf_cv6_wiresets AS w
					WHERE w.genre_id = g.genre_id AND w.active = 1
				)
			");
		}
	}

	protected function migrateLegacyWiresetGroups(): void
	{
		$db = $this->db();
		$sm = $this->schemaManager();

		if (!$sm->tableExists('xf_cv6_wiresets') || !$sm->columnExists('xf_cv6_wiresets', 'genre_id'))
		{
			return;
		}

		$groupTable = null;
		if ($sm->tableExists('xf_cv6_groups_wiresets'))
		{
			$groupTable = 'xf_cv6_groups_wiresets';
		}
		elseif ($sm->tableExists('xf_cv6_groups'))
		{
			$groupTable = 'xf_cv6_groups';
		}

		if ($groupTable !== null && $sm->columnExists('xf_cv6_wiresets', 'group_id'))
		{
			$groups = $db->fetchAll("SELECT * FROM {$groupTable} ORDER BY group_id ASC");
			foreach ($groups as $group)
			{
				$groupId = (int)($group['group_id'] ?? 0);
				if ($groupId <= 1)
				{
					continue;
				}

				$phraseText = (string)$db->fetchOne(
					"SELECT phrase_text FROM xf_phrase WHERE language_id = 0 AND title = ?",
					['cv6_wireset_group_title.' . $groupId]
				);
				$groupTitle = trim($phraseText !== '' ? $phraseText : (string)($group['title'] ?? ''));
				if ($groupTitle === '' || strcasecmp($groupTitle, 'Default') === 0)
				{
					continue;
				}

				$slug = strtolower((string)preg_replace('/[^a-z0-9_]+/i', '_', $groupTitle));
				$slug = trim($slug, '_');
				if ($slug === '')
				{
					$slug = 'group_' . $groupId;
				}
				$slug = substr($slug, 0, 25);

				$targetGenreId = (int)$db->fetchOne(
					"SELECT genre_id FROM xf_cv6_genre WHERE LOWER(class) = ?",
					[$slug]
				);

				if ($targetGenreId <= 0)
				{
					$matchedPhraseTitle = (string)$db->fetchOne(
						"SELECT title FROM xf_phrase WHERE language_id = 0 AND title LIKE 'cv6_ce_genre_title_%' AND LOWER(phrase_text) = ?",
						[strtolower($groupTitle)]
					);
					if ($matchedPhraseTitle !== '' && preg_match('/^cv6_ce_genre_title_(\d+)$/', $matchedPhraseTitle, $m))
					{
						$targetGenreId = (int)$m[1];
					}
				}

				if ($targetGenreId <= 0)
				{
					$db->insert('xf_cv6_genre', [
						'class' => $slug,
						'icon' => 'fa-dice-d20 fad',
						'active' => 1,
						'property_id' => 0,
						'display_order' => (int)($group['display_order'] ?? 50),
						'setting_count' => 0,
						'map_count' => 0,
						'wireset_count' => 0,
					]);
					$targetGenreId = (int)$db->lastInsertId();

					$db->query("
						INSERT IGNORE INTO xf_phrase
							(language_id, title, phrase_text, global_cache, addon_id, version_id, version_string)
						VALUES
							(0, ?, ?, 0, '', 0, '')
					", ['cv6_ce_genre_title_' . $targetGenreId, $groupTitle]);
				}

				if ($targetGenreId > 0)
				{
					$db->update(
						'xf_cv6_wiresets',
						['genre_id' => $targetGenreId],
						'group_id = ? AND genre_id = 0',
						[$groupId]
					);
				}
			}
		}

		$genreByClass = $db->fetchPairs("SELECT class, genre_id FROM xf_cv6_genre");
		$mappingByClass = [
			'fantasy' => ['dsa', 'dsa5', 'dnd', 'pathfinder_1e', 'sotdl', 'witcher'],
			'cyberpunk' => ['sr5'],
			'scifi' => ['Alien'],
			'pa' => ['myz', 'heredium'],
			'horror' => ['wod', 'nwod', 'ahnenblut', 'hexxen'],
			'default' => ['sw', 'fate', 'ore'],
		];

		foreach ($mappingByClass as $class => $tags)
		{
			if (!empty($genreByClass[$class]))
			{
				$genreId = (int)$genreByClass[$class];
				$db->update(
					'xf_cv6_wiresets',
					['genre_id' => $genreId],
					'genre_id = 0 AND tag IN (' . $db->quote($tags) . ')'
				);
			}
		}
	}

	public function getTables($tableName = null): array
	{
		$tables = [];

		$tables['xf_cv6_genre'] = function (Create $table): void
		{
			$table->addColumn('genre_id', 'int', 10)->autoIncrement();
			$table->addColumn('class', 'varchar', 25);
			$table->addColumn('icon', 'varchar', 100)->setDefault('');
			$table->addColumn('active', 'bool')->setDefault(1);
			$table->addColumn('property_id', 'int', 10)->setDefault(0);
			$table->addColumn('display_order', 'int', 10)->setDefault(1);
			$table->addColumn('setting_count', 'int', 10)->setDefault(0);
			$table->addColumn('map_count', 'int', 10)->setDefault(0);
			$table->addColumn('wireset_count', 'int', 10)->setDefault(0);
			$table->addPrimaryKey('genre_id');
			$table->addUniqueKey('class');
		};

		$tables['xf_cv6_publisher'] = function (Create $table): void
		{
			$table->addColumn('publisher_id', 'int', 10)->autoIncrement();
			$table->addColumn('title', 'varchar', 50);
			$table->addColumn('url_slug', 'varchar', 50)->nullable()->setDefault(null);
			$table->addColumn('description', 'text');
			$table->addColumn('logo_url', 'varchar', 250)->setDefault('');
			$table->addColumn('url', 'varchar', 250)->setDefault('');
			$table->addColumn('license_notice', 'mediumtext')->nullable();
			$table->addColumn('active', 'bool')->setDefault(1);
			$table->addColumn('display_order', 'int', 10)->setDefault(1);
			$table->addPrimaryKey('publisher_id');
			$table->addUniqueKey('url_slug');
		};

		$tables['xf_cv6_rpg_campaign_content'] = function (Create $table): void
		{
			$table->addColumn('link_id', 'int', 10)->autoIncrement();
			$table->addColumn('campaign_id', 'int', 10);
			$table->addColumn('content_type', 'varchar', 25);
			$table->addColumn('content_id', 'int', 10);
			$table->addColumn('visibility', 'varchar', 20)->setDefault('gm_only');
			$table->addColumn('title_override', 'varchar', 150)->setDefault('');
			$table->addColumn('notes', 'text')->nullable();
			$table->addColumn('display_order', 'int', 10)->setDefault(10);
			$table->addColumn('added_user_id', 'int', 10)->setDefault(0);
			$table->addColumn('added_date', 'int', 10)->setDefault(0);
			$table->addPrimaryKey('link_id');
			$table->addUniqueKey(['campaign_id', 'content_type', 'content_id'], 'campaign_content');
			$table->addKey(['campaign_id', 'content_type', 'display_order'], 'campaign_type_order');
			$table->addKey(['content_type', 'content_id'], 'content_lookup');
		};

		return $tables;
	}
}
