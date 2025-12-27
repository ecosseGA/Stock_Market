<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int stat_id
 * @property int user_id
 * @property int season_id
 * @property int total_trades
 * @property int winning_trades
 * @property int losing_trades
 * @property float total_profit
 * @property float total_loss
 * @property float biggest_win
 * @property float biggest_loss
 * @property int longest_hold_days
 * @property int current_streak
 * @property int best_streak
 * @property int unique_symbols_traded
 * @property int achievement_points
 * @property int last_updated
 *
 * GETTERS
 * @property float win_rate
 * @property float net_profit
 * @property float profit_factor
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \IC\StockMarket\Entity\Season Season
 */
class UserStats extends Entity
{
	/**
	 * Get win rate percentage
	 */
	public function getWinRate()
	{
		if ($this->total_trades == 0)
		{
			return 0;
		}

		return ($this->winning_trades / $this->total_trades) * 100;
	}

	/**
	 * Get net profit/loss
	 */
	public function getNetProfit()
	{
		return $this->total_profit - $this->total_loss;
	}

	/**
	 * Get profit factor (total profit / total loss)
	 */
	public function getProfitFactor()
	{
		if ($this->total_loss == 0)
		{
			return $this->total_profit > 0 ? 999 : 0;
		}

		return $this->total_profit / $this->total_loss;
	}

	/**
	 * Record a trade for statistics
	 */
	public function recordTrade($profitLoss, $holdDays, $symbolId)
	{
		$this->total_trades++;

		if ($profitLoss > 0)
		{
			$this->winning_trades++;
			$this->total_profit += $profitLoss;
			$this->current_streak = max(0, $this->current_streak) + 1;
			
			if ($profitLoss > $this->biggest_win)
			{
				$this->biggest_win = $profitLoss;
			}
		}
		elseif ($profitLoss < 0)
		{
			$this->losing_trades++;
			$this->total_loss += abs($profitLoss);
			$this->current_streak = min(0, $this->current_streak) - 1;
			
			if (abs($profitLoss) > $this->biggest_loss)
			{
				$this->biggest_loss = abs($profitLoss);
			}
		}

		if (abs($this->current_streak) > $this->best_streak)
		{
			$this->best_streak = abs($this->current_streak);
		}

		if ($holdDays > $this->longest_hold_days)
		{
			$this->longest_hold_days = $holdDays;
		}

		// Track unique symbols
		$this->updateUniqueSymbolsCount();

		$this->last_updated = \XF::$time;
		$this->save();
	}

	/**
	 * Update count of unique symbols traded
	 */
	protected function updateUniqueSymbolsCount()
	{
		$count = $this->db()->fetchOne("
			SELECT COUNT(DISTINCT symbol_id)
			FROM xf_ic_sm_trade
			WHERE account_id IN (
				SELECT account_id 
				FROM xf_ic_sm_account 
				WHERE user_id = ? AND season_id = ?
			)
		", [$this->user_id, $this->season_id]);

		$this->unique_symbols_traded = $count;
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_user_stats';
		$structure->shortName = 'IC\StockMarket:UserStats';
		$structure->primaryKey = 'stat_id';
		$structure->columns = [
			'stat_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'season_id' => ['type' => self::UINT, 'required' => true],
			'total_trades' => ['type' => self::UINT, 'default' => 0],
			'winning_trades' => ['type' => self::UINT, 'default' => 0],
			'losing_trades' => ['type' => self::UINT, 'default' => 0],
			'total_profit' => ['type' => self::FLOAT, 'default' => 0],
			'total_loss' => ['type' => self::FLOAT, 'default' => 0],
			'biggest_win' => ['type' => self::FLOAT, 'default' => 0],
			'biggest_loss' => ['type' => self::FLOAT, 'default' => 0],
			'longest_hold_days' => ['type' => self::UINT, 'default' => 0],
			'current_streak' => ['type' => self::INT, 'default' => 0],
			'best_streak' => ['type' => self::UINT, 'default' => 0],
			'unique_symbols_traded' => ['type' => self::UINT, 'default' => 0],
			'achievement_points' => ['type' => self::UINT, 'default' => 0],
			'last_updated' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		$structure->getters = [
			'win_rate' => true,
			'net_profit' => true,
			'profit_factor' => true
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Season' => [
				'entity' => 'IC\StockMarket:Season',
				'type' => self::TO_ONE,
				'conditions' => 'season_id',
				'primary' => true
			]
		];

		return $structure;
	}
}
