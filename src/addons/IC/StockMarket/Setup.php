<?php

namespace IC\StockMarket;

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

	// ################################## INSTALL ###########################################

	public function installStep1()
	{
		$sm = $this->schemaManager();

		foreach ($this->getTables() as $tableName => $callback)
		{
			$sm->createTable($tableName, $callback);
		}
	}

	public function installStep2()
	{
		// Insert default season
		$this->db()->insert('xf_ic_sm_season', [
			'season_id' => 1,
			'season_name' => 'Season 1',
			'start_date' => \XF::$time,
			'is_active' => 1,
			'starting_balance' => 10000.00
		]);
	}

	public function installStep3()
	{
		// Insert global markets
		$markets = [
			[
				'market_code' => 'NYSE',
				'market_name' => 'New York Stock Exchange',
				'country_code' => 'US',
				'timezone' => 'America/New_York',
				'is_active' => 1,
				'display_order' => 10
			],
			[
				'market_code' => 'TSE',
				'market_name' => 'Tokyo Stock Exchange',
				'country_code' => 'JP',
				'timezone' => 'Asia/Tokyo',
				'is_active' => 1,
				'display_order' => 20
			],
			[
				'market_code' => 'LSE',
				'market_name' => 'London Stock Exchange',
				'country_code' => 'GB',
				'timezone' => 'Europe/London',
				'is_active' => 1,
				'display_order' => 30
			]
		];

		foreach ($markets as $market)
		{
			$this->db()->insert('xf_ic_sm_market', $market);
		}
	}

	public function installStep4()
	{
		// Get market IDs
		$nyseId = $this->db()->fetchOne("SELECT market_id FROM xf_ic_sm_market WHERE market_code = 'NYSE'");
		$tseId = $this->db()->fetchOne("SELECT market_id FROM xf_ic_sm_market WHERE market_code = 'TSE'");
		$lseId = $this->db()->fetchOne("SELECT market_id FROM xf_ic_sm_market WHERE market_code = 'LSE'");

		// US Stocks (NYSE/NASDAQ)
		$usStocks = [
			['symbol' => 'AAPL', 'company_name' => 'Apple Inc.'],
			['symbol' => 'MSFT', 'company_name' => 'Microsoft Corporation'],
			['symbol' => 'GOOGL', 'company_name' => 'Alphabet Inc. Class A'],
			['symbol' => 'AMZN', 'company_name' => 'Amazon.com Inc.'],
			['symbol' => 'TSLA', 'company_name' => 'Tesla Inc.'],
			['symbol' => 'META', 'company_name' => 'Meta Platforms Inc.'],
			['symbol' => 'NVDA', 'company_name' => 'NVIDIA Corporation'],
			['symbol' => 'JPM', 'company_name' => 'JPMorgan Chase & Co.'],
			['symbol' => 'V', 'company_name' => 'Visa Inc.'],
			['symbol' => 'WMT', 'company_name' => 'Walmart Inc.'],
			['symbol' => 'JNJ', 'company_name' => 'Johnson & Johnson'],
			['symbol' => 'PG', 'company_name' => 'Procter & Gamble Co.'],
			['symbol' => 'MA', 'company_name' => 'Mastercard Inc.'],
			['symbol' => 'HD', 'company_name' => 'Home Depot Inc.'],
			['symbol' => 'DIS', 'company_name' => 'Walt Disney Co.'],
			['symbol' => 'BAC', 'company_name' => 'Bank of America Corp.'],
			['symbol' => 'NFLX', 'company_name' => 'Netflix Inc.'],
			['symbol' => 'COST', 'company_name' => 'Costco Wholesale Corp.'],
			['symbol' => 'ORCL', 'company_name' => 'Oracle Corporation'],
			['symbol' => 'INTC', 'company_name' => 'Intel Corporation']
		];

		// Japanese Stocks (TSE) - Yahoo Finance format: SYMBOL.T
		$japanStocks = [
			['symbol' => '7203.T', 'company_name' => 'Toyota Motor Corporation'],
			['symbol' => '6758.T', 'company_name' => 'Sony Group Corporation'],
			['symbol' => '9984.T', 'company_name' => 'SoftBank Group Corp.'],
			['symbol' => '7974.T', 'company_name' => 'Nintendo Co., Ltd.'],
			['symbol' => '6861.T', 'company_name' => 'Keyence Corporation'],
			['symbol' => '8306.T', 'company_name' => 'Mitsubishi UFJ Financial Group'],
			['symbol' => '9983.T', 'company_name' => 'Fast Retailing Co., Ltd.'],
			['symbol' => '6501.T', 'company_name' => 'Hitachi, Ltd.'],
			['symbol' => '4063.T', 'company_name' => 'Shin-Etsu Chemical Co., Ltd.'],
			['symbol' => '6702.T', 'company_name' => 'Fujitsu Limited'],
			['symbol' => '8058.T', 'company_name' => 'Mitsubishi Corporation'],
			['symbol' => '6098.T', 'company_name' => 'Recruit Holdings Co., Ltd.'],
			['symbol' => '4901.T', 'company_name' => 'FUJIFILM Holdings Corporation'],
			['symbol' => '4502.T', 'company_name' => 'Takeda Pharmaceutical Company Limited'],
			['symbol' => '9433.T', 'company_name' => 'KDDI Corporation']
		];

		// UK Stocks (LSE) - Yahoo Finance format: SYMBOL.L
		$ukStocks = [
			['symbol' => 'TSCO.L', 'company_name' => 'Tesco PLC'],
			['symbol' => 'BP.L', 'company_name' => 'BP p.l.c.'],
			['symbol' => 'HSBA.L', 'company_name' => 'HSBC Holdings plc'],
			['symbol' => 'AZN.L', 'company_name' => 'AstraZeneca PLC'],
			['symbol' => 'GSK.L', 'company_name' => 'GSK plc'],
			['symbol' => 'ULVR.L', 'company_name' => 'Unilever PLC'],
			['symbol' => 'RIO.L', 'company_name' => 'Rio Tinto Group'],
			['symbol' => 'DGE.L', 'company_name' => 'Diageo plc'],
			['symbol' => 'VOD.L', 'company_name' => 'Vodafone Group Plc'],
			['symbol' => 'LSEG.L', 'company_name' => 'London Stock Exchange Group plc'],
			['symbol' => 'SHEL.L', 'company_name' => 'Shell plc'],
			['symbol' => 'BARC.L', 'company_name' => 'Barclays PLC'],
			['symbol' => 'LLOY.L', 'company_name' => 'Lloyds Banking Group plc'],
			['symbol' => 'NWG.L', 'company_name' => 'NatWest Group plc'],
			['symbol' => 'REL.L', 'company_name' => 'RELX PLC']
		];

		$displayOrder = 0;
		foreach ($usStocks as $stock)
		{
			$this->db()->insert('xf_ic_sm_symbol', [
				'market_id' => $nyseId,
				'symbol' => $stock['symbol'],
				'company_name' => $stock['company_name'],
				'is_active' => 1,
				'is_featured' => 0,
				'display_order' => $displayOrder++
			]);
		}

		$displayOrder = 0;
		foreach ($japanStocks as $stock)
		{
			$this->db()->insert('xf_ic_sm_symbol', [
				'market_id' => $tseId,
				'symbol' => $stock['symbol'],
				'company_name' => $stock['company_name'],
				'is_active' => 1,
				'is_featured' => 0,
				'display_order' => $displayOrder++
			]);
		}

		$displayOrder = 0;
		foreach ($ukStocks as $stock)
		{
			$this->db()->insert('xf_ic_sm_symbol', [
				'market_id' => $lseId,
				'symbol' => $stock['symbol'],
				'company_name' => $stock['company_name'],
				'is_active' => 1,
				'is_featured' => 0,
				'display_order' => $displayOrder++
			]);
		}
	}

	public function installStep5()
	{
		// Insert default achievements
		$achievements = [
			// TRADING MILESTONES
			[
				'achievement_key' => 'first_trade',
				'achievement_category' => 'trading',
				'points' => 10,
				'credits_reward' => 50,
				'is_repeatable' => 0,
				'display_order' => 10
			],
			[
				'achievement_key' => 'ten_trades',
				'achievement_category' => 'trading',
				'points' => 25,
				'credits_reward' => 100,
				'is_repeatable' => 0,
				'display_order' => 20
			],
			[
				'achievement_key' => 'fifty_trades',
				'achievement_category' => 'trading',
				'points' => 50,
				'credits_reward' => 250,
				'is_repeatable' => 0,
				'display_order' => 30
			],
			[
				'achievement_key' => 'hundred_trades',
				'achievement_category' => 'trading',
				'points' => 100,
				'credits_reward' => 500,
				'is_repeatable' => 0,
				'display_order' => 40
			],
			
			// PERFORMANCE
			[
				'achievement_key' => 'first_profit',
				'achievement_category' => 'performance',
				'points' => 15,
				'credits_reward' => 75,
				'is_repeatable' => 0,
				'display_order' => 110
			],
			[
				'achievement_key' => 'profitable_trader',
				'achievement_category' => 'performance',
				'points' => 50,
				'credits_reward' => 200,
				'is_repeatable' => 0,
				'display_order' => 120
			],
			[
				'achievement_key' => 'portfolio_growth_10',
				'achievement_category' => 'performance',
				'points' => 30,
				'credits_reward' => 150,
				'is_repeatable' => 0,
				'display_order' => 130
			],
			[
				'achievement_key' => 'portfolio_growth_25',
				'achievement_category' => 'performance',
				'points' => 50,
				'credits_reward' => 300,
				'is_repeatable' => 0,
				'display_order' => 140
			],
			[
				'achievement_key' => 'portfolio_growth_50',
				'achievement_category' => 'performance',
				'points' => 75,
				'credits_reward' => 500,
				'is_repeatable' => 0,
				'display_order' => 150
			],
			[
				'achievement_key' => 'portfolio_growth_100',
				'achievement_category' => 'performance',
				'points' => 150,
				'credits_reward' => 1000,
				'is_repeatable' => 0,
				'display_order' => 160
			],
			
			// HOLDING
			[
				'achievement_key' => 'diamond_hands',
				'achievement_category' => 'holding',
				'points' => 40,
				'credits_reward' => 200,
				'is_repeatable' => 0,
				'display_order' => 210
			],
			[
				'achievement_key' => 'long_term_investor',
				'achievement_category' => 'holding',
				'points' => 75,
				'credits_reward' => 400,
				'is_repeatable' => 0,
				'display_order' => 220
			],
			[
				'achievement_key' => 'diversification_master',
				'achievement_category' => 'holding',
				'points' => 60,
				'credits_reward' => 300,
				'is_repeatable' => 0,
				'display_order' => 230
			],
			
			// SOCIAL
			[
				'achievement_key' => 'top_ten_percent',
				'achievement_category' => 'social',
				'points' => 50,
				'credits_reward' => 250,
				'is_repeatable' => 1,
				'display_order' => 310
			],
			[
				'achievement_key' => 'top_trader',
				'achievement_category' => 'social',
				'points' => 200,
				'credits_reward' => 1000,
				'is_repeatable' => 1,
				'display_order' => 320
			]
		];

		foreach ($achievements as $achievement)
		{
			$this->db()->insert('xf_ic_sm_achievement', $achievement);
		}
	}

	public function installStep6()
	{
		// Set default permissions for Registered usergroup (user_group_id = 2)
		// This ensures all registered users can view and trade by default
		
		$db = $this->db();
		
		// Check if permissions already exist to avoid duplicates
		$existingView = $db->fetchOne("
			SELECT permission_value
			FROM xf_permission_entry
			WHERE user_group_id = 2
			AND permission_group_id = 'icStockMarket'
			AND permission_id = 'view'
		");
		
		$existingTrade = $db->fetchOne("
			SELECT permission_value
			FROM xf_permission_entry
			WHERE user_group_id = 2
			AND permission_group_id = 'icStockMarket'
			AND permission_id = 'trade'
		");
		
		// Insert view permission if it doesn't exist
		if ($existingView === false)
		{
			$db->insert('xf_permission_entry', [
				'user_group_id' => 2,
				'user_id' => 0,
				'permission_group_id' => 'icStockMarket',
				'permission_id' => 'view',
				'permission_value' => 'allow',
				'permission_value_int' => 0
			]);
		}
		
		// Insert trade permission if it doesn't exist
		if ($existingTrade === false)
		{
			$db->insert('xf_permission_entry', [
				'user_group_id' => 2,
				'user_id' => 0,
				'permission_group_id' => 'icStockMarket',
				'permission_id' => 'trade',
				'permission_value' => 'allow',
				'permission_value_int' => 0
			]);
		}
		
		// Rebuild permission cache to apply changes
		\XF::runOnce('rebuildPermissionCache', function()
		{
			/** @var \XF\Repository\PermissionCombination $permissionRepo */
			$permissionRepo = \XF::repository('XF:PermissionCombination');
			$permissionRepo->rebuildCombinationCache();
		});
		
		// Copy flag images from _output to data directory
		$this->copyDataFiles();
	}

	// ################################## UNINSTALL ###########################################

	public function uninstallStep1()
	{
		$sm = $this->schemaManager();

		foreach (array_keys($this->getTables()) as $tableName)
		{
			$sm->dropTable($tableName);
		}
	}

	// ################################## UPGRADE ###########################################

	public function upgrade1008002Step1()
	{
		// Fix decimal column sizes for high-value stocks (e.g. BRK.A at $400k+)
		$this->alterTable('xf_ic_sm_quote', function (Alter $table) {
			$table->changeColumn('price', 'decimal', '15,2');
			$table->changeColumn('change_amount', 'decimal', '15,2')->nullable();
		});

		$this->alterTable('xf_ic_sm_position', function (Alter $table) {
			$table->changeColumn('average_price', 'decimal', '15,2');
		});

		$this->alterTable('xf_ic_sm_trade', function (Alter $table) {
			$table->changeColumn('price', 'decimal', '15,2');
			$table->changeColumn('total_cost', 'decimal', '15,2');
		});
	}

	public function upgrade1020001Step1()
	{
		// Fix decimal precision for better price accuracy
		// DECIMAL(20,6) allows for prices up to $99,999,999,999,999.999999
		// This fixes "Out of range value" errors for volatile penny stocks
		$this->alterTable('xf_ic_sm_quote', function (Alter $table) {
			$table->changeColumn('price', 'decimal', '20,6');
			$table->changeColumn('change_amount', 'decimal', '20,6')->nullable();
		});

		$this->alterTable('xf_ic_sm_position', function (Alter $table) {
			$table->changeColumn('average_price', 'decimal', '20,6');
			$table->changeColumn('total_cost', 'decimal', '20,6');
		});

		$this->alterTable('xf_ic_sm_trade', function (Alter $table) {
			$table->changeColumn('price', 'decimal', '20,6');
			$table->changeColumn('total_cost', 'decimal', '20,6');
		});

		$this->alterTable('xf_ic_sm_account', function (Alter $table) {
			$table->changeColumn('cash_balance', 'decimal', '20,6');
			$table->changeColumn('portfolio_value', 'decimal', '20,6');
			$table->changeColumn('total_value', 'decimal', '20,6');
		});
	}

	public function upgrade1020003Step1()
	{
		// Fix change_percent column - was too small (10,4)
		// DECIMAL(12,4) allows for larger percentage changes (up to 99,999,999.9999%)
		// This fixes "Out of range value for column 'change_percent'" errors
		$this->alterTable('xf_ic_sm_quote', function (Alter $table) {
			$table->changeColumn('change_percent', 'decimal', '12,4')->nullable();
		});
	}

	public function upgrade1022006Step1()
	{
		// Add total_cost column to trade table if it doesn't exist
		// Some early installations might be missing this column
		$sm = $this->schemaManager();
		
		if (!$sm->columnExists('xf_ic_sm_trade', 'total_cost')) {
			$this->alterTable('xf_ic_sm_trade', function (Alter $table) {
				$table->addColumn('total_cost', 'decimal', '20,6')->setDefault(0);
			});
		}
		
		// Also ensure position table has total_cost
		if (!$sm->columnExists('xf_ic_sm_position', 'total_cost')) {
			$this->alterTable('xf_ic_sm_position', function (Alter $table) {
				$table->addColumn('total_cost', 'decimal', '20,6')->setDefault(0);
			});
		}
	}

	public function upgrade1022006Step2()
	{
		// Populate total_cost for existing records
		$this->db()->query("
			UPDATE xf_ic_sm_position 
			SET total_cost = average_price * quantity
			WHERE total_cost = 0 OR total_cost IS NULL
		");
		
		$this->db()->query("
			UPDATE xf_ic_sm_trade 
			SET total_cost = price * quantity
			WHERE total_cost = 0 OR total_cost IS NULL
		");
	}

	public function upgrade1027000Step1()
	{
		// Add initial_balance column to track starting balance per user
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_ic_sm_account', function(Alter $table)
		{
			$table->addColumn('initial_balance', 'decimal', '20,6')->setDefault(10000)->after('total_value');
		});
	}

	public function upgrade1027000Step2()
	{
		// Populate initial_balance for existing accounts
		// For existing accounts, use season starting_balance as initial value
		$this->db()->query("
			UPDATE xf_ic_sm_account a
			JOIN xf_ic_sm_season s ON a.season_id = s.season_id
			SET a.initial_balance = s.starting_balance
			WHERE a.initial_balance = 0 OR a.initial_balance IS NULL
		");
	}

	public function upgrade1050005Step1()
	{
		// CRITICAL FIX: Ensure initial_balance column exists
		// This is a catch-all for users upgrading from older versions
		// The column should have been added in v1.27.0, but if someone
		// jumped from pre-1.27.0 to v1.50.x, they might be missing it
		
		$sm = $this->schemaManager();
		
		// Check if table exists
		if (!$sm->tableExists('xf_ic_sm_account'))
		{
			return; // Table doesn't exist yet, will be created in install
		}
		
		// Check if column exists by querying information_schema
		$db = $this->db();
		$columnExists = $db->fetchOne("
			SELECT COUNT(*) 
			FROM information_schema.COLUMNS 
			WHERE 
				TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = 'xf_ic_sm_account'
				AND COLUMN_NAME = 'initial_balance'
		");
		
		// Add column if missing
		if (!$columnExists)
		{
			$sm->alterTable('xf_ic_sm_account', function(Alter $table)
			{
				$table->addColumn('initial_balance', 'decimal', '20,6')->setDefault(10000)->after('total_value');
			});
			
			// Populate initial_balance for existing accounts
			$db->query("
				UPDATE xf_ic_sm_account a
				JOIN xf_ic_sm_season s ON a.season_id = s.season_id
				SET a.initial_balance = s.starting_balance
				WHERE a.initial_balance = 0 OR a.initial_balance IS NULL
			");
		}
	}

	public function upgrade1050007Step1()
	{
		// CRITICAL FIX: Ensure total_cost column exists in xf_ic_sm_trade
		// Also removes old 'total_amount' column if it exists
		
		$sm = $this->schemaManager();
		
		// Check if table exists
		if (!$sm->tableExists('xf_ic_sm_trade'))
		{
			return; // Table doesn't exist yet, will be created in install
		}
		
		$db = $this->db();
		
		// Check if old 'total_amount' column exists
		$hasOldColumn = $db->fetchOne("
			SELECT COUNT(*) 
			FROM information_schema.COLUMNS 
			WHERE 
				TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = 'xf_ic_sm_trade'
				AND COLUMN_NAME = 'total_amount'
		");
		
		// Check if new 'total_cost' column exists
		$hasNewColumn = $db->fetchOne("
			SELECT COUNT(*) 
			FROM information_schema.COLUMNS 
			WHERE 
				TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = 'xf_ic_sm_trade'
				AND COLUMN_NAME = 'total_cost'
		");
		
		if ($hasOldColumn && $hasNewColumn)
		{
			// BOTH exist - drop the old one
			$db->query("ALTER TABLE xf_ic_sm_trade DROP COLUMN total_amount");
		}
		else if ($hasOldColumn && !$hasNewColumn)
		{
			// Only old exists - rename it
			$db->query("
				ALTER TABLE xf_ic_sm_trade 
				CHANGE COLUMN total_amount total_cost DECIMAL(20,6)
			");
		}
		else if (!$hasOldColumn && !$hasNewColumn)
		{
			// Neither exists - add the new one
			$sm->alterTable('xf_ic_sm_trade', function(Alter $table)
			{
				$table->addColumn('total_cost', 'decimal', '20,6')->after('price');
			});
			
			// Populate total_cost for existing trades
			$db->query("
				UPDATE xf_ic_sm_trade
				SET total_cost = quantity * price
				WHERE total_cost IS NULL OR total_cost = 0
			");
		}
		// If only new column exists, do nothing - already correct
	}

	// ################################## v1.51.0 - GLOBAL MARKETS ###########################################

	public function upgrade1051000Step1()
	{
		// Add trading hours columns to markets table for Global Markets feature
		
		$sm = $this->schemaManager();
		
		if (!$sm->tableExists('xf_ic_sm_market'))
		{
			return;
		}
		
		$sm->alterTable('xf_ic_sm_market', function(Alter $table)
		{
			// Market trading hours (in market's local timezone)
			$table->addColumn('market_open_time', 'varchar', 10)->setDefault('09:30')->after('timezone');
			$table->addColumn('market_close_time', 'varchar', 10)->setDefault('16:00')->after('market_open_time');
			
			// Extended hours (optional)
			$table->addColumn('pre_market_open', 'varchar', 10)->nullable()->after('market_close_time');
			$table->addColumn('after_hours_close', 'varchar', 10)->nullable()->after('pre_market_open');
			
			// Trading days (1=Monday, 7=Sunday; comma-separated)
			$table->addColumn('trading_days', 'varchar', 20)->setDefault('1,2,3,4,5')->after('after_hours_close');
		});
	}

	public function upgrade1051000Step2()
	{
		// Set proper trading hours for the 3 global markets
		
		$db = $this->db();
		
		// Check if markets exist
		$hasMarkets = $db->fetchOne("SELECT COUNT(*) FROM xf_ic_sm_market");
		
		if ($hasMarkets > 0)
		{
			// Update NYSE (New York Stock Exchange)
			$db->query("
				UPDATE xf_ic_sm_market 
				SET market_open_time = '09:30',
					market_close_time = '16:00',
					pre_market_open = '04:00',
					after_hours_close = '20:00',
					trading_days = '1,2,3,4,5'
				WHERE market_code = 'NYSE'
			");
			
			// Update LSE (London Stock Exchange)
			$db->query("
				UPDATE xf_ic_sm_market 
				SET market_open_time = '08:00',
					market_close_time = '16:30',
					pre_market_open = NULL,
					after_hours_close = NULL,
					trading_days = '1,2,3,4,5'
				WHERE market_code = 'LSE'
			");
			
			// Update TSE (Tokyo Stock Exchange)
			$db->query("
				UPDATE xf_ic_sm_market 
				SET market_open_time = '09:00',
					market_close_time = '15:00',
					pre_market_open = NULL,
					after_hours_close = NULL,
					trading_days = '1,2,3,4,5'
				WHERE market_code = 'TSE'
			");
		}
	}

	public function upgrade1053020Step1()
	{
		// Clean up duplicate phrases from old versions
		// This fixes the issue where wrong messages were shown for watchlist actions
		
		$db = $this->db();
		
		// Delete old version 1.37.0 duplicate phrases
		// Keep only the current version (1.53.0 and newer)
		$db->query("
			DELETE FROM xf_phrase 
			WHERE addon_id = 'IC/StockMarket'
			  AND title IN ('ic_sm_added_to_watchlist', 'ic_sm_removed_from_watchlist')
			  AND version_id < 1053000
		");
	}

	public function upgrade1053026Step1()
	{
		// Remove watchlist feature entirely
		// Drop the watchlist table
		
		$sm = $this->schemaManager();
		$sm->dropTable('xf_ic_sm_watchlist');
	}
	
	// ################################## v1.54.0 - XP & GAMIFICATION #######################
	
	public function upgrade1054000Step1()
	{
		// Add XP and difficulty tier to achievement table
		$sm = $this->schemaManager();
		
		// Check if table exists
		if (!$sm->tableExists('xf_ic_sm_achievement')) {
			// Table doesn't exist, create it
			$sm->createTable('xf_ic_sm_achievement', function(Create $table) {
				$table->addColumn('achievement_id', 'int')->autoIncrement();
				$table->addColumn('achievement_key', 'varchar', 50);
				$table->addColumn('achievement_category', 'enum')->values(['trading', 'performance', 'holding', 'social']);
				$table->addColumn('xp_points', 'int')->setDefault(10);
				$table->addColumn('difficulty_tier', 'enum')
					->values(['easy', 'medium', 'hard', 'very_hard', 'epic', 'legendary'])
					->setDefault('easy');
				$table->addColumn('points', 'int')->setDefault(0);
				$table->addColumn('credits_reward', 'int')->setDefault(0);
				$table->addColumn('trophy_id', 'int')->nullable();
				$table->addColumn('badge_id', 'int')->nullable();
				$table->addColumn('is_repeatable', 'tinyint')->setDefault(0);
				$table->addColumn('is_active', 'tinyint')->setDefault(1);
				$table->addColumn('display_order', 'int')->setDefault(0);
				$table->addUniqueKey('achievement_key');
				$table->addKey('achievement_category');
				$table->addKey('is_active');
				$table->addKey('difficulty_tier', 'idx_difficulty');
			});
		} else {
			// Table exists, just add columns if they don't exist
			$sm->alterTable('xf_ic_sm_achievement', function(Alter $table) {
				$table->addColumn('xp_points', 'int')->setDefault(10)->after('achievement_category');
				$table->addColumn('difficulty_tier', 'enum')
					->values(['easy', 'medium', 'hard', 'very_hard', 'epic', 'legendary'])
					->setDefault('easy')
					->after('xp_points');
				$table->addKey('difficulty_tier', 'idx_difficulty');
			});
		}
	}
	
	public function upgrade1054000Step2()
	{
		// Add XP awarded to user_achievement table
		$sm = $this->schemaManager();
		
		// Check if table exists
		if (!$sm->tableExists('xf_ic_sm_user_achievement')) {
			// Table doesn't exist, create it
			$sm->createTable('xf_ic_sm_user_achievement', function(Create $table) {
				$table->addColumn('user_achievement_id', 'int')->autoIncrement();
				$table->addColumn('user_id', 'int');
				$table->addColumn('achievement_id', 'int');
				$table->addColumn('earned_date', 'int');
				$table->addColumn('xp_awarded', 'int')->setDefault(0);
				$table->addColumn('season_id', 'int')->nullable();
				$table->addColumn('account_id', 'int')->nullable();
				$table->addColumn('progress_data', 'mediumblob')->nullable();
				$table->addKey(['user_id', 'earned_date']);
				$table->addKey('achievement_id');
				$table->addKey('season_id');
			});
		} else {
			// Table exists, just add columns if they don't exist
			$sm->alterTable('xf_ic_sm_user_achievement', function(Alter $table) {
				$table->addColumn('xp_awarded', 'int')->setDefault(0)->after('earned_date');
				$table->addColumn('account_id', 'int')->nullable()->after('season_id');
			});
		}
	}
	
	public function upgrade1054000Step3()
	{
		// Add season XP to account table
		$sm = $this->schemaManager();
		
		// Check if table exists
		if (!$sm->tableExists('xf_ic_sm_account')) {
			// This is a critical error - account table should exist
			throw new \XF\Db\Exception('Account table does not exist. Please run a fresh installation.');
		}
		
		$sm->alterTable('xf_ic_sm_account', function(Alter $table) {
			$table->addColumn('season_xp', 'int')->setDefault(0)->after('created_date');
			$table->addColumn('season_rank', 'varchar', 50)->nullable()->after('season_xp');
			$table->addKey('season_xp', 'idx_season_xp');
		});
	}
	
	public function upgrade1054000Step4()
	{
		// Create user_career table for lifetime stats
		$sm = $this->schemaManager();
		
		// Only create if it doesn't exist
		if (!$sm->tableExists('xf_ic_sm_user_career')) {
			$sm->createTable('xf_ic_sm_user_career', function(Create $table) {
				$table->addColumn('user_id', 'int')->primaryKey();
				$table->addColumn('lifetime_xp', 'int')->setDefault(0);
				$table->addColumn('career_rank', 'varchar', 50)->nullable();
				$table->addColumn('achievements_earned', 'int')->setDefault(0);
				$table->addColumn('seasons_participated', 'int')->setDefault(0);
				$table->addColumn('total_trades', 'int')->setDefault(0);
				$table->addColumn('created_date', 'int');
				$table->addColumn('last_updated', 'int');
				$table->addKey('lifetime_xp', 'idx_lifetime_xp');
			});
		}
	}
	
	public function upgrade1054000Step5()
	{
		// Set XP values for existing achievements
		$achievements = [
			// Easy achievements (10 XP)
			'first_trade' => ['xp' => 10, 'tier' => 'easy'],
			'first_buy' => ['xp' => 10, 'tier' => 'easy'],
			'first_sell' => ['xp' => 10, 'tier' => 'easy'],
			'early_bird' => ['xp' => 10, 'tier' => 'easy'],
			'night_owl' => ['xp' => 10, 'tier' => 'easy'],
			
			// Medium achievements (25 XP)
			'ten_trades' => ['xp' => 25, 'tier' => 'medium'],
			'fifty_trades' => ['xp' => 25, 'tier' => 'medium'],
			
			// Hard achievements (50 XP)
			'hundred_trades' => ['xp' => 50, 'tier' => 'hard'],
			'five_hundred_trades' => ['xp' => 50, 'tier' => 'hard'],
			'thousand_trades' => ['xp' => 100, 'tier' => 'very_hard'],
			
			// Very hard achievements (100 XP)
			'portfolio_10k' => ['xp' => 50, 'tier' => 'hard'],
			'portfolio_50k' => ['xp' => 100, 'tier' => 'very_hard'],
			'portfolio_100k' => ['xp' => 100, 'tier' => 'very_hard'],
			'portfolio_500k' => ['xp' => 250, 'tier' => 'epic'],
			'portfolio_1m' => ['xp' => 250, 'tier' => 'epic'],
			
			// Profit achievements
			'profit_1k' => ['xp' => 25, 'tier' => 'medium'],
			'profit_10k' => ['xp' => 50, 'tier' => 'hard'],
			'profit_50k' => ['xp' => 100, 'tier' => 'very_hard'],
			'profit_100k' => ['xp' => 250, 'tier' => 'epic']
		];
		
		foreach ($achievements as $key => $data) {
			$this->db()->update('xf_ic_sm_achievement', [
				'xp_points' => $data['xp'],
				'difficulty_tier' => $data['tier']
			], 'achievement_key = ?', $key);
		}
	}
	
	public function upgrade1054000Step6()
	{
		// Insert 25 new achievements with proper XP values
		$newAchievements = [
			// Easy Tier (10 XP)
			[
				'achievement_key' => 'first_steps',
				'achievement_category' => 'trading',
				'xp_points' => 10,
				'difficulty_tier' => 'easy',
				'points' => 10,
				'display_order' => 5
			],
			[
				'achievement_key' => 'first_purchase',
				'achievement_category' => 'trading',
				'xp_points' => 10,
				'difficulty_tier' => 'easy',
				'points' => 10,
				'display_order' => 11
			],
			[
				'achievement_key' => 'first_sale',
				'achievement_category' => 'trading',
				'xp_points' => 10,
				'difficulty_tier' => 'easy',
				'points' => 10,
				'display_order' => 12
			],
			[
				'achievement_key' => 'getting_started',
				'achievement_category' => 'trading',
				'xp_points' => 10,
				'difficulty_tier' => 'easy',
				'points' => 15,
				'display_order' => 15
			],
			[
				'achievement_key' => 'active_beginner',
				'achievement_category' => 'trading',
				'xp_points' => 10,
				'difficulty_tier' => 'easy',
				'points' => 15,
				'display_order' => 16
			],
			
			// Medium Tier (25 XP)
			[
				'achievement_key' => 'active_trader',
				'achievement_category' => 'trading',
				'xp_points' => 25,
				'difficulty_tier' => 'medium',
				'points' => 25,
				'display_order' => 25
			],
			[
				'achievement_key' => 'diverse_portfolio_5',
				'achievement_category' => 'holding',
				'xp_points' => 25,
				'difficulty_tier' => 'medium',
				'points' => 25,
				'display_order' => 205
			],
			[
				'achievement_key' => 'building_wealth',
				'achievement_category' => 'performance',
				'xp_points' => 25,
				'difficulty_tier' => 'medium',
				'points' => 25,
				'display_order' => 125
			],
			[
				'achievement_key' => 'market_explorer',
				'achievement_category' => 'trading',
				'xp_points' => 25,
				'difficulty_tier' => 'medium',
				'points' => 25,
				'display_order' => 305
			],
			[
				'achievement_key' => 'consistent_profit',
				'achievement_category' => 'performance',
				'xp_points' => 25,
				'difficulty_tier' => 'medium',
				'points' => 30,
				'display_order' => 135
			],
			
			// Hard Tier (50 XP)
			[
				'achievement_key' => 'day_trader',
				'achievement_category' => 'trading',
				'xp_points' => 50,
				'difficulty_tier' => 'hard',
				'points' => 50,
				'display_order' => 45
			],
			[
				'achievement_key' => 'consistent_trader',
				'achievement_category' => 'trading',
				'xp_points' => 50,
				'difficulty_tier' => 'hard',
				'points' => 50,
				'display_order' => 35
			],
			[
				'achievement_key' => 'bull_run',
				'achievement_category' => 'performance',
				'xp_points' => 50,
				'difficulty_tier' => 'hard',
				'points' => 50,
				'display_order' => 145
			],
			[
				'achievement_key' => 'market_diversification',
				'achievement_category' => 'trading',
				'xp_points' => 50,
				'difficulty_tier' => 'hard',
				'points' => 50,
				'display_order' => 310
			],
			[
				'achievement_key' => 'strategic_investor',
				'achievement_category' => 'performance',
				'xp_points' => 50,
				'difficulty_tier' => 'hard',
				'points' => 60,
				'display_order' => 155
			],
			
			// Very Hard Tier (100 XP)
			[
				'achievement_key' => 'high_roller',
				'achievement_category' => 'trading',
				'xp_points' => 100,
				'difficulty_tier' => 'very_hard',
				'points' => 100,
				'display_order' => 55
			],
			[
				'achievement_key' => 'portfolio_millionaire',
				'achievement_category' => 'performance',
				'xp_points' => 100,
				'difficulty_tier' => 'very_hard',
				'points' => 200,
				'display_order' => 165
			],
			[
				'achievement_key' => 'diamond_hands_30',
				'achievement_category' => 'holding',
				'xp_points' => 100,
				'difficulty_tier' => 'very_hard',
				'points' => 75,
				'display_order' => 215
			],
			[
				'achievement_key' => 'profit_champion',
				'achievement_category' => 'performance',
				'xp_points' => 100,
				'difficulty_tier' => 'very_hard',
				'points' => 150,
				'display_order' => 175
			],
			[
				'achievement_key' => 'volume_king',
				'achievement_category' => 'trading',
				'xp_points' => 100,
				'difficulty_tier' => 'very_hard',
				'points' => 150,
				'display_order' => 65
			],
			
			// Epic Tier (250 XP)
			[
				'achievement_key' => 'top_trader',
				'achievement_category' => 'social',
				'xp_points' => 250,
				'difficulty_tier' => 'epic',
				'points' => 250,
				'display_order' => 405
			],
			[
				'achievement_key' => 'perfect_week',
				'achievement_category' => 'performance',
				'xp_points' => 250,
				'difficulty_tier' => 'epic',
				'points' => 200,
				'display_order' => 185
			],
			[
				'achievement_key' => 'market_dominator',
				'achievement_category' => 'social',
				'xp_points' => 250,
				'difficulty_tier' => 'epic',
				'points' => 250,
				'display_order' => 415
			],
			
			// Legendary Tier (500 XP)
			[
				'achievement_key' => 'season_champion',
				'achievement_category' => 'social',
				'xp_points' => 500,
				'difficulty_tier' => 'legendary',
				'points' => 500,
				'display_order' => 425
			],
			[
				'achievement_key' => 'trading_legend',
				'achievement_category' => 'performance',
				'xp_points' => 500,
				'difficulty_tier' => 'legendary',
				'points' => 1000,
				'display_order' => 999
			]
		];
		
		foreach ($newAchievements as $achievement) {
			// Check if achievement already exists
			$existing = $this->db()->fetchOne('
				SELECT achievement_id 
				FROM xf_ic_sm_achievement 
				WHERE achievement_key = ?
			', $achievement['achievement_key']);
			
			if (!$existing) {
				$this->db()->insert('xf_ic_sm_achievement', $achievement);
			}
		}
	}

	public function upgrade1058010Step1()
	{
		// Copy flag images on upgrade to 1.58.10
		$this->copyDataFiles();
	}

	protected function copyDataFiles()
	{
		// Copy flag images from _output to data directory
		$sourceDir = $this->addOn->getAddOnDirectory() . DIRECTORY_SEPARATOR . '_output' . DIRECTORY_SEPARATOR . 'images';
		$targetDir = \XF::getRootDirectory() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'IC' . DIRECTORY_SEPARATOR . 'StockMarket' . DIRECTORY_SEPARATOR . 'images';
		
		if (is_dir($sourceDir))
		{
			$this->copyDirectory($sourceDir, $targetDir);
		}
	}

	protected function copyDirectory($source, $target)
	{
		if (!is_dir($target))
		{
			@mkdir($target, 0755, true);
		}

		if (!is_dir($source))
		{
			return;
		}

		$dir = opendir($source);
		while (($file = readdir($dir)) !== false)
		{
			if ($file == '.' || $file == '..')
			{
				continue;
			}

			$sourcePath = $source . DIRECTORY_SEPARATOR . $file;
			$targetPath = $target . DIRECTORY_SEPARATOR . $file;

			if (is_dir($sourcePath))
			{
				$this->copyDirectory($sourcePath, $targetPath);
			}
			else
			{
				@copy($sourcePath, $targetPath);
			}
		}
		closedir($dir);
	}

	// ################################## TABLES ###########################################

	protected function getTables(): array
	{
		$tables = [];

		$tables['xf_ic_sm_season'] = function (Create $table) {
			$table->addColumn('season_id', 'int')->autoIncrement();
			$table->addColumn('season_name', 'varchar', 100);
			$table->addColumn('start_date', 'int');
			$table->addColumn('end_date', 'int')->nullable();
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('starting_balance', 'decimal', '15,2')->setDefault(10000);
		};

		$tables['xf_ic_sm_market'] = function (Create $table) {
			$table->addColumn('market_id', 'int')->autoIncrement();
			$table->addColumn('market_code', 'varchar', 10);
			$table->addColumn('market_name', 'varchar', 100);
			$table->addColumn('country_code', 'varchar', 5);
			$table->addColumn('timezone', 'varchar', 50);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addUniqueKey('market_code');
		};

		$tables['xf_ic_sm_symbol'] = function (Create $table) {
			$table->addColumn('symbol_id', 'int')->autoIncrement();
			$table->addColumn('market_id', 'int');
			$table->addColumn('symbol', 'varchar', 20);
			$table->addColumn('company_name', 'varchar', 255);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('is_featured', 'tinyint')->setDefault(0);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addKey('market_id');
			$table->addUniqueKey(['market_id', 'symbol']);
		};

		$tables['xf_ic_sm_quote'] = function (Create $table) {
			$table->addColumn('quote_id', 'int')->autoIncrement();
			$table->addColumn('symbol_id', 'int');
			$table->addColumn('price', 'decimal', '20,6');
			$table->addColumn('change_amount', 'decimal', '20,6')->nullable();
			$table->addColumn('change_percent', 'decimal', '12,4')->nullable();
			$table->addColumn('volume', 'bigint')->nullable();
			$table->addColumn('last_updated', 'int');
			$table->addUniqueKey('symbol_id');
			$table->addKey('last_updated');
		};

		$tables['xf_ic_sm_account'] = function (Create $table) {
			$table->addColumn('account_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('season_id', 'int')->setDefault(1);
			$table->addColumn('cash_balance', 'decimal', '20,6')->setDefault(10000);
			$table->addColumn('portfolio_value', 'decimal', '20,6')->setDefault(0);
			$table->addColumn('total_value', 'decimal', '20,6')->setDefault(10000);
			$table->addColumn('initial_balance', 'decimal', '20,6')->setDefault(10000);
			$table->addColumn('created_date', 'int');
			$table->addColumn('season_xp', 'int')->setDefault(0);
			$table->addColumn('season_rank', 'varchar', 50)->nullable();
			$table->addUniqueKey(['user_id', 'season_id']);
			$table->addKey('season_id');
			$table->addKey('total_value');
			$table->addKey('season_xp');
		};

		$tables['xf_ic_sm_position'] = function (Create $table) {
			$table->addColumn('position_id', 'int')->autoIncrement();
			$table->addColumn('account_id', 'int');
			$table->addColumn('symbol_id', 'int');
			$table->addColumn('quantity', 'int');
			$table->addColumn('average_price', 'decimal', '20,6');
			$table->addColumn('total_cost', 'decimal', '20,6');
			$table->addColumn('last_updated', 'int');
			$table->addUniqueKey(['account_id', 'symbol_id']);
			$table->addKey('symbol_id');
		};

		$tables['xf_ic_sm_trade'] = function (Create $table) {
			$table->addColumn('trade_id', 'int')->autoIncrement();
			$table->addColumn('account_id', 'int');
			$table->addColumn('symbol_id', 'int');
			$table->addColumn('trade_type', 'enum')->values(['buy', 'sell']);
			$table->addColumn('quantity', 'int');
			$table->addColumn('price', 'decimal', '20,6');
			$table->addColumn('total_cost', 'decimal', '20,6');
			$table->addColumn('trade_date', 'int');
			$table->addKey('account_id');
			$table->addKey(['symbol_id', 'trade_date']);
		};

		$tables['xf_ic_sm_watchlist'] = function (Create $table) {
			$table->addColumn('watchlist_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('symbol_id', 'int');
			$table->addColumn('added_date', 'int');
			$table->addUniqueKey(['user_id', 'symbol_id']);
			$table->addKey('user_id');
		};

		$tables['xf_ic_sm_order'] = function (Create $table) {
			$table->addColumn('order_id', 'int')->autoIncrement();
			$table->addColumn('account_id', 'int');
			$table->addColumn('symbol_id', 'int');
			$table->addColumn('order_type', 'enum')->values(['market', 'limit']);
			$table->addColumn('trade_type', 'enum')->values(['buy', 'sell']);
			$table->addColumn('quantity', 'int');
			$table->addColumn('limit_price', 'decimal', '10,2')->nullable();
			$table->addColumn('status', 'enum')->values(['pending', 'filled', 'cancelled'])->setDefault('pending');
			$table->addColumn('created_date', 'int');
			$table->addColumn('filled_date', 'int')->nullable();
			$table->addKey('account_id');
			$table->addKey(['symbol_id', 'status']);
		};

		$tables['xf_ic_sm_leaderboard'] = function (Create $table) {
			$table->addColumn('entry_id', 'int')->autoIncrement();
			$table->addColumn('season_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('account_id', 'int');
			$table->addColumn('rank', 'int');
			$table->addColumn('total_value', 'decimal', '15,2');
			$table->addColumn('return_percent', 'decimal', '10,4');
			$table->addColumn('last_updated', 'int');
			$table->addUniqueKey(['season_id', 'user_id']);
			$table->addKey(['season_id', 'rank']);
		};

		$tables['xf_ic_sm_achievement'] = function (Create $table) {
			$table->addColumn('achievement_id', 'int')->autoIncrement();
			$table->addColumn('achievement_key', 'varchar', 50);
			$table->addColumn('achievement_category', 'enum')->values(['trading', 'performance', 'holding', 'social']);
			$table->addColumn('xp_points', 'int')->setDefault(10);
			$table->addColumn('difficulty_tier', 'enum')->values(['easy', 'medium', 'hard', 'very_hard', 'epic', 'legendary'])->setDefault('easy');
			$table->addColumn('points', 'int')->setDefault(0);
			$table->addColumn('credits_reward', 'int')->setDefault(0);
			$table->addColumn('trophy_id', 'int')->nullable();
			$table->addColumn('badge_id', 'int')->nullable();
			$table->addColumn('is_repeatable', 'tinyint')->setDefault(0);
			$table->addColumn('is_active', 'tinyint')->setDefault(1);
			$table->addColumn('display_order', 'int')->setDefault(0);
			$table->addUniqueKey('achievement_key');
			$table->addKey('achievement_category');
			$table->addKey('is_active');
			$table->addKey('difficulty_tier');
		};

		$tables['xf_ic_sm_user_achievement'] = function (Create $table) {
			$table->addColumn('user_achievement_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('achievement_id', 'int');
			$table->addColumn('earned_date', 'int');
			$table->addColumn('xp_awarded', 'int')->setDefault(0);
			$table->addColumn('season_id', 'int')->nullable();
			$table->addColumn('account_id', 'int')->nullable();
			$table->addColumn('progress_data', 'mediumblob')->nullable();
			$table->addKey(['user_id', 'earned_date']);
			$table->addKey('achievement_id');
			$table->addKey('season_id');
		};

		$tables['xf_ic_sm_user_career'] = function (Create $table) {
			$table->addColumn('user_id', 'int')->primaryKey();
			$table->addColumn('lifetime_xp', 'int')->setDefault(0);
			$table->addColumn('career_rank', 'varchar', 50)->nullable();
			$table->addColumn('achievements_earned', 'int')->setDefault(0);
			$table->addColumn('seasons_participated', 'int')->setDefault(0);
			$table->addColumn('total_trades', 'int')->setDefault(0);
			$table->addColumn('created_date', 'int');
			$table->addColumn('last_updated', 'int');
			$table->addKey('lifetime_xp', 'idx_lifetime_xp');
		};

		$tables['xf_ic_sm_user_stats'] = function (Create $table) {
			$table->addColumn('stat_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('season_id', 'int');
			$table->addColumn('total_trades', 'int')->setDefault(0);
			$table->addColumn('winning_trades', 'int')->setDefault(0);
			$table->addColumn('losing_trades', 'int')->setDefault(0);
			$table->addColumn('total_profit', 'decimal', '20,6')->setDefault(0);
			$table->addColumn('total_loss', 'decimal', '20,6')->setDefault(0);
			$table->addColumn('biggest_win', 'decimal', '20,6')->setDefault(0);
			$table->addColumn('biggest_loss', 'decimal', '20,6')->setDefault(0);
			$table->addColumn('longest_hold_days', 'int')->setDefault(0);
			$table->addColumn('current_streak', 'int')->setDefault(0);
			$table->addColumn('best_streak', 'int')->setDefault(0);
			$table->addColumn('unique_symbols_traded', 'int')->setDefault(0);
			$table->addColumn('achievement_points', 'int')->setDefault(0);
			$table->addColumn('last_updated', 'int');
			$table->addUniqueKey(['user_id', 'season_id']);
			$table->addKey('achievement_points');
		};

		return $tables;
	}
}
