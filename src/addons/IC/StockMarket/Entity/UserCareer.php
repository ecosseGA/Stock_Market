<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Tracks lifetime career statistics and XP for a user
 * 
 * COLUMNS
 * @property int user_id
 * @property int lifetime_xp
 * @property string|null career_rank
 * @property int achievements_earned
 * @property int seasons_participated
 * @property int total_trades
 * @property int created_date
 * @property int last_updated
 * 
 * RELATIONS
 * @property \XF\Entity\User User
 */
class UserCareer extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_user_career';
		$structure->shortName = 'IC\StockMarket:UserCareer';
		$structure->primaryKey = 'user_id';
		
		$structure->columns = [
			'user_id' => ['type' => self::UINT, 'required' => true],
			'lifetime_xp' => ['type' => self::UINT, 'default' => 0],
			'career_rank' => ['type' => self::STR, 'maxLength' => 50, 'nullable' => true, 'default' => null],
			'achievements_earned' => ['type' => self::UINT, 'default' => 0],
			'seasons_participated' => ['type' => self::UINT, 'default' => 0],
			'total_trades' => ['type' => self::UINT, 'default' => 0],
			'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'last_updated' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		
		return $structure;
	}
	
	/**
	 * Get formatted XP display (e.g., 1486 -> "14.86")
	 */
	public function getFormattedXp(): string
	{
		return number_format($this->lifetime_xp / 100, 2);
	}
	
	/**
	 * Update career rank based on lifetime XP
	 */
	public function updateRank()
	{
		/** @var \IC\StockMarket\Service\ExperienceCalculator $xpCalc */
		$xpCalc = \XF::app()->service('IC\StockMarket:ExperienceCalculator');
		$this->career_rank = $xpCalc->calculateCareerRank($this->lifetime_xp);
	}
	
	/**
	 * Add XP to lifetime total
	 */
	public function addXp(int $xp)
	{
		$this->lifetime_xp += $xp;
		$this->updateRank();
		$this->last_updated = \XF::$time;
	}
	
	/**
	 * Increment achievement counter
	 */
	public function incrementAchievements()
	{
		$this->achievements_earned++;
		$this->last_updated = \XF::$time;
	}
	
	/**
	 * Increment trade counter
	 */
	public function incrementTrades(int $count = 1)
	{
		$this->total_trades += $count;
		$this->last_updated = \XF::$time;
	}
}
