<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $order_id
 * @property int $account_id
 * @property int $symbol_id
 * @property string $order_type (market/limit)
 * @property string $trade_type (buy/sell)
 * @property int $quantity
 * @property float|null $limit_price
 * @property string $status (pending/filled/cancelled)
 * @property int $created_date
 * @property int|null $filled_date
 *
 * RELATIONS
 * @property \IC\StockMarket\Entity\Account $Account
 * @property \IC\StockMarket\Entity\Symbol $Symbol
 */
class Order extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_order';
		$structure->shortName = 'IC\StockMarket:Order';
		$structure->primaryKey = 'order_id';
		
		$structure->columns = [
			'order_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'account_id' => ['type' => self::UINT, 'required' => true],
			'symbol_id' => ['type' => self::UINT, 'required' => true],
			'order_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['market', 'limit']
			],
			'trade_type' => ['type' => self::STR, 'required' => true,
				'allowedValues' => ['buy', 'sell']
			],
			'quantity' => ['type' => self::UINT, 'required' => true],
			'limit_price' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'status' => ['type' => self::STR, 'default' => 'pending',
				'allowedValues' => ['pending', 'filled', 'cancelled']
			],
			'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'filled_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null]
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
		
		return $structure;
	}
	
	/**
	 * Check if order is pending
	 */
	public function isPending()
	{
		return $this->status === 'pending';
	}
	
	/**
	 * Check if limit order should execute at current price
	 */
	public function shouldExecute($currentPrice)
	{
		if ($this->order_type !== 'limit' || !$this->isPending()) {
			return false;
		}
		
		if ($this->trade_type === 'buy') {
			return $currentPrice <= $this->limit_price;
		} else {
			return $currentPrice >= $this->limit_price;
		}
	}
}
