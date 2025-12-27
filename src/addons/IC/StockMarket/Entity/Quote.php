<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $quote_id
 * @property int $symbol_id
 * @property float $price
 * @property float|null $change_amount
 * @property float|null $change_percent
 * @property int|null $volume
 * @property int $last_updated
 *
 * RELATIONS
 * @property \IC\StockMarket\Entity\Symbol $Symbol
 */
class Quote extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_quote';
		$structure->shortName = 'IC\StockMarket:Quote';
		$structure->primaryKey = 'quote_id';
		
		$structure->columns = [
			'quote_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'symbol_id' => ['type' => self::UINT, 'required' => true],
			'price' => ['type' => self::FLOAT, 'required' => true],
			'change_amount' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'change_percent' => ['type' => self::FLOAT, 'nullable' => true, 'default' => null],
			'volume' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'last_updated' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		
		$structure->relations = [
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
	 * Check if quote is up (positive change)
	 */
	public function isUp()
	{
		return $this->change_amount > 0;
	}
	
	/**
	 * Check if quote is down (negative change)
	 */
	public function isDown()
	{
		return $this->change_amount < 0;
	}
	
	/**
	 * Get formatted change with + or - sign
	 */
	public function getFormattedChange()
	{
		if ($this->change_amount === null) return '';
		
		$sign = $this->change_amount >= 0 ? '+' : '';
		return $sign . number_format($this->change_amount, 2);
	}
	
	/**
	 * Get formatted percent change
	 */
	public function getFormattedPercent()
	{
		if ($this->change_percent === null) return '';
		
		$sign = $this->change_percent >= 0 ? '+' : '';
		return $sign . number_format($this->change_percent, 2) . '%';
	}
}
