<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class UserCareer extends Repository
{
	/**
	 * Get or create a user career record
	 */
	public function getUserCareer(int $userId, bool $create = true): ?\IC\StockMarket\Entity\UserCareer
	{
		$career = $this->em->find('IC\StockMarket:UserCareer', $userId);
		
		if (!$career && $create) {
			/** @var \IC\StockMarket\Entity\UserCareer $career */
			$career = $this->em->create('IC\StockMarket:UserCareer');
			$career->user_id = $userId;
			$career->created_date = \XF::$time;
			$career->last_updated = \XF::$time;
			$career->save();
		}
		
		return $career;
	}
	
	/**
	 * Get top careers by lifetime XP
	 */
	public function findTopCareersByXp(int $limit = 10)
	{
		return $this->finder('IC\StockMarket:UserCareer')
			->with('User')
			->where('lifetime_xp', '>', 0)
			->order('lifetime_xp', 'DESC')
			->limit($limit);
	}
	
	/**
	 * Get career leaderboard
	 */
	public function getCareerLeaderboard(int $limit = 100): array
	{
		$careers = $this->findTopCareersByXp($limit)->fetch();
		
		$leaderboard = [];
		$rank = 1;
		
		foreach ($careers as $career) {
			$leaderboard[] = [
				'rank' => $rank++,
				'user_id' => $career->user_id,
				'username' => $career->User->username ?? 'Unknown',
				'lifetime_xp' => $career->lifetime_xp,
				'career_rank' => $career->career_rank,
				'achievements_earned' => $career->achievements_earned,
				'total_trades' => $career->total_trades
			];
		}
		
		return $leaderboard;
	}
	
	/**
	 * Get user's career rank position
	 */
	public function getUserCareerRank(int $userId): ?int
	{
		$career = $this->getUserCareer($userId, false);
		
		if (!$career) {
			return null;
		}
		
		// Count how many users have more XP
		$rank = $this->db()->fetchOne('
			SELECT COUNT(*) + 1
			FROM xf_ic_sm_user_career
			WHERE lifetime_xp > ?
		', $career->lifetime_xp);
		
		return (int)$rank;
	}
	
	/**
	 * Update all career ranks (useful for bulk updates)
	 */
	public function updateAllCareerRanks()
	{
		/** @var \IC\StockMarket\Service\ExperienceCalculator $xpCalc */
		$xpCalc = \XF::app()->service('IC\StockMarket:ExperienceCalculator');
		
		$careers = $this->finder('IC\StockMarket:UserCareer')->fetch();
		
		foreach ($careers as $career) {
			$career->career_rank = $xpCalc->calculateCareerRank($career->lifetime_xp);
			$career->saveIfChanged();
		}
	}
}
