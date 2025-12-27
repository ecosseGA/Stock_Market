<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $trade_id
 * @property int $account_id
 * @property int $symbol_id
 * @property string $trade_type (buy/sell)
 * @property int $quantity
 * @property float $price
 * @property float $total_cost
 * @property int $trade_date
 *
 * RELATIONS
 * @property \IC\StockMarket\Entity\Account $Account
 * @property \IC\StockMarket\Entity\Symbol $Symbol
 */
class Trade extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_trade';
		$structure->shortName = 'IC\StockMarket:Trade';
		$structure->primaryKey = 'trade_id';
		
		$structure->columns = [
			'trade_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'account_id' => ['type' => self::UINT, 'required' => true],
			'symbol_id' => ['type' => self::UINT, 'required' => true],
			'trade_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['buy', 'sell']
			],
			'quantity' => ['type' => self::UINT, 'required' => true],
			'price' => ['type' => self::FLOAT, 'required' => true],
			'total_cost' => ['type' => self::FLOAT, 'required' => true],
			'trade_date' => ['type' => self::UINT, 'default' => \XF::$time]
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
				'primary' => true
			]
		];
		
		return $structure;
	}
	
	/**
	 * Check if this is a buy trade
	 */
	public function isBuy()
	{
		return $this->trade_type === 'buy';
	}
	
	/**
	 * Check if this is a sell trade
	 */
	public function isSell()
	{
		return $this->trade_type === 'sell';
	}
}
