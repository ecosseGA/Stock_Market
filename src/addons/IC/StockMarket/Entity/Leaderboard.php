<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $entry_id
 * @property int $season_id
 * @property int $user_id
 * @property int $account_id
 * @property int $rank
 * @property float $total_value
 * @property float $return_percent
 * @property int $last_updated
 *
 * RELATIONS
 * @property \IC\StockMarket\Entity\Season $Season
 * @property \XF\Entity\User $User
 * @property \IC\StockMarket\Entity\Account $Account
 */
class Leaderboard extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_leaderboard';
		$structure->shortName = 'IC\StockMarket:Leaderboard';
		$structure->primaryKey = 'entry_id';
		
		$structure->columns = [
			'entry_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'season_id' => ['type' => self::UINT, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'account_id' => ['type' => self::UINT, 'required' => true],
			'rank' => ['type' => self::UINT, 'required' => true],
			'total_value' => ['type' => self::FLOAT, 'required' => true],
			'return_percent' => ['type' => self::FLOAT, 'required' => true],
			'last_updated' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		
		$structure->relations = [
			'Season' => [
				'entity' => 'IC\StockMarket:Season',
				'type' => self::TO_ONE,
				'conditions' => 'season_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Account' => [
				'entity' => 'IC\StockMarket:Account',
				'type' => self::TO_ONE,
				'conditions' => 'account_id'
			]
		];
		
		return $structure;
	}
	
	/**
	 * Get medal/badge based on rank
	 */
	public function getMedal()
	{
		switch ($this->rank) {
			case 1: return '🥇';
			case 2: return '🥈';
			case 3: return '🥉';
			default: return '';
		}
	}
	
	/**
	 * Get profit/loss amount
	 */
	public function getProfitLoss()
	{
		if (!$this->Account || !$this->Season) {
			return 0;
		}
		
		$starting = $this->Season->starting_balance;
		return $this->total_value - $starting;
	}
	
	/**
	 * Get profit/loss percent (alias for return_percent)
	 */
	public function getProfitLossPercent()
	{
		return $this->return_percent;
	}
	
	/**
	 * Getter for profit_loss (for templates)
	 */
	public function get($key)
	{
		if ($key === 'profit_loss') {
			return $this->getProfitLoss();
		}
		if ($key === 'profit_loss_percent') {
			return $this->getProfitLossPercent();
		}
		return parent::get($key);
	}
}
