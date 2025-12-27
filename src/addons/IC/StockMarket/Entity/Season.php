<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $season_id
 * @property string $season_name
 * @property int $start_date
 * @property int|null $end_date
 * @property bool $is_active
 * @property float $starting_balance
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\IC\StockMarket\Entity\Account[] $Accounts
 */
class Season extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_season';
		$structure->shortName = 'IC\StockMarket:Season';
		$structure->primaryKey = 'season_id';
		
		$structure->columns = [
			'season_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'season_name' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'start_date' => ['type' => self::UINT, 'required' => true],
			'end_date' => ['type' => self::UINT, 'nullable' => true, 'default' => null],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'starting_balance' => ['type' => self::FLOAT, 'default' => 10000]
		];
		
		$structure->relations = [
			'Accounts' => [
				'entity' => 'IC\StockMarket:Account',
				'type' => self::TO_MANY,
				'conditions' => 'season_id'
			]
		];
		
		return $structure;
	}
	
	/**
	 * Check if season is currently active
	 */
	public function isActive()
	{
		if (!$this->is_active) return false;
		if ($this->end_date && $this->end_date < \XF::$time) return false;
		
		return true;
	}
	
	/**
	 * Get duration in days
	 */
	public function getDurationDays()
	{
		$end = $this->end_date ?: \XF::$time;
		return floor(($end - $this->start_date) / 86400);
	}
}
