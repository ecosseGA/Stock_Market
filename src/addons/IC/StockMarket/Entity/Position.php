<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $position_id
 * @property int $account_id
 * @property int $symbol_id
 * @property int $quantity
 * @property float $average_price
 * @property float $total_cost
 * @property int $last_updated
 *
 * RELATIONS
 * @property \IC\StockMarket\Entity\Account $Account
 * @property \IC\StockMarket\Entity\Symbol $Symbol
 */
class Position extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_position';
		$structure->shortName = 'IC\StockMarket:Position';
		$structure->primaryKey = 'position_id';
		
		$structure->columns = [
			'position_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'account_id' => ['type' => self::UINT, 'required' => true],
			'symbol_id' => ['type' => self::UINT, 'required' => true],
			'quantity' => ['type' => self::UINT, 'required' => true],
			'average_price' => ['type' => self::FLOAT, 'required' => true],
			'total_cost' => ['type' => self::FLOAT, 'required' => true],
			'last_updated' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		
		$structure->relations = [
			'Account' => [
				'entity' => 'IC\StockMarket:Account',
				'type' => self::TO_ONE,
				'conditions' => 'account_id',
				'primary' => true
			],
			'Symbol' => [
				'entity' => 'IC\StockMarket:Symbol',
				'type' => self::TO_ONE,
				'conditions' => 'symbol_id',
				'primary' => true,
				'with' => 'Quote'
			]
		];
		
		$structure->getters = [
			'current_value' => true,
			'profit_loss' => true,
			'profit_loss_percent' => true
		];
		
		return $structure;
	}
	
	/**
	 * Get current market value
	 */
	public function getCurrentValue()
	{
		$currentPrice = $this->Symbol ? $this->Symbol->getCurrentPrice() : 0;
		return $currentPrice * $this->quantity;
	}
	
	/**
	 * Get profit/loss on this position
	 */
	public function getProfitLoss()
	{
		return $this->getCurrentValue() - $this->total_cost;
	}
	
	/**
	 * Get profit/loss percentage
	 */
	public function getProfitLossPercent()
	{
		if ($this->total_cost == 0) return 0;
		return ($this->getProfitLoss() / $this->total_cost) * 100;
	}
}
