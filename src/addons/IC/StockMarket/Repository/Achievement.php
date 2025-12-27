<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class Achievement extends Repository
{
	/**
	 * @return \IC\StockMarket\Finder\Achievement
	 */
	public function findAchievementsForList()
	{
		return $this->finder('IC\StockMarket:Achievement')
			->order(['achievement_category', 'display_order']);
	}

	/**
	 * @return \IC\StockMarket\Finder\Achievement
	 */
	public function findActiveAchievements()
	{
		return $this->finder('IC\StockMarket:Achievement')
			->where('is_active', 1)
			->order(['achievement_category', 'display_order']);
	}

	/**
	 * Get achievements grouped by category
	 *
	 * @return array
	 */
	public function getAchievementsByCategory()
	{
		$achievements = $this->findActiveAchievements()->fetch();
		return $achievements->groupBy('achievement_category');
	}
	
	/**
	 * Rebuild achievement progress for all users
	 * This will check all achievements for all users who have trading accounts
	 */
	public function rebuildAchievementUserCache(array $userIds = null)
	{
		$db = $this->db();
		
		if ($userIds === null)
		{
			// Get all accounts that have trades
			$accounts = $db->fetchAll("
				SELECT DISTINCT a.account_id, a.user_id
				FROM xf_ic_sm_account a
				INNER JOIN xf_ic_sm_trade t ON t.account_id = a.account_id
			");
			
			if (!$accounts)
			{
				// No accounts with trades found
				return 0;
			}
		}
		
		$achievementService = $this->app()->service('IC\StockMarket:Achievement');
		$updated = 0;
		
		// Check achievements for each account
		foreach ($accounts as $accountData)
		{
			/** @var \IC\StockMarket\Entity\Account $account */
			$account = $this->finder('IC\StockMarket:Account')
				->where('account_id', $accountData['account_id'])
				->fetchOne();
			
			if ($account)
			{
				// This will check and award any missing achievements
				$achievementService->checkAchievements($account, 'rebuild');
				$updated++;
			}
		}
		
		return $updated;
	}
	
	/**
	 * Get total achievement count
	 */
	public function getTotalAchievementCount()
	{
		return $this->findActiveAchievements()->total();
	}
}
