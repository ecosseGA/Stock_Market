<?php

namespace IC\StockMarket\Service;

use IC\StockMarket\Entity\Account;
use IC\StockMarket\Entity\Achievement as AchievementEntity;
use XF\Entity\User;
use XF\Service\AbstractService;

/**
 * Service for awarding and checking achievements
 */
class Achievement extends AbstractService
{
	/**
	 * Award an achievement to a user
	 */
	public function awardAchievement(User $user, Account $account, AchievementEntity $achievement): bool
	{
		// Check if already earned (unless repeatable)
		if (!$achievement->is_repeatable) {
			$existing = $this->em()->findOne('IC\StockMarket:UserAchievement', [
				'user_id' => $user->user_id,
				'achievement_id' => $achievement->achievement_id,
				'season_id' => $account->season_id
			]);
			
			if ($existing) {
				return false; // Already earned
			}
		}
		
		// Create user achievement record
		/** @var \IC\StockMarket\Entity\UserAchievement $userAchievement */
		$userAchievement = $this->em()->create('IC\StockMarket:UserAchievement');
		$userAchievement->user_id = $user->user_id;
		$userAchievement->achievement_id = $achievement->achievement_id;
		$userAchievement->earned_date = \XF::$time;
		$userAchievement->season_id = $account->season_id;
		$userAchievement->account_id = $account->account_id;
		$userAchievement->xp_awarded = $achievement->xp_points;
		$userAchievement->save();
		
		// Award XP to season account
		$this->awardSeasonXp($account, $achievement->xp_points);
		
		// Award XP to lifetime career
		$this->awardCareerXp($user, $achievement->xp_points);
		
		// Send achievement alert
		$this->sendAchievementAlert($user, $achievement);
		
		// Check for rank up
		$this->checkRankUp($user, $account);
		
		return true;
	}
	
	/**
	 * Award XP to season account
	 */
	protected function awardSeasonXp(Account $account, int $xp): void
	{
		$oldXp = $account->season_xp;
		$account->season_xp += $xp;
		
		// Update rank
		/** @var ExperienceCalculator $xpCalc */
		$xpCalc = $this->service('IC\StockMarket:ExperienceCalculator');
		$account->season_rank = $xpCalc->calculateSeasonRank($account->season_xp);
		
		$account->save();
	}
	
	/**
	 * Award XP to lifetime career
	 */
	protected function awardCareerXp(User $user, int $xp): void
	{
		/** @var \IC\StockMarket\Repository\UserCareer $careerRepo */
		$careerRepo = $this->repository('IC\StockMarket:UserCareer');
		$career = $careerRepo->getUserCareer($user->user_id, true);
		
		$career->addXp($xp);
		$career->incrementAchievements();
		$career->save();
	}
	
	/**
	 * Check for rank up and send alert if needed
	 */
	protected function checkRankUp(User $user, Account $account): void
	{
		/** @var ExperienceCalculator $xpCalc */
		$xpCalc = $this->service('IC\StockMarket:ExperienceCalculator');
		
		// Check season rank up
		$seasonRank = $xpCalc->calculateSeasonRank($account->season_xp);
		if ($account->season_rank !== $seasonRank) {
			$this->sendRankUpAlert($user, $seasonRank, false);
		}
		
		// Check career rank up
		/** @var \IC\StockMarket\Repository\UserCareer $careerRepo */
		$careerRepo = $this->repository('IC\StockMarket:UserCareer');
		$career = $careerRepo->getUserCareer($user->user_id, false);
		
		if ($career) {
			$careerRank = $xpCalc->calculateCareerRank($career->lifetime_xp);
			if ($career->career_rank !== $careerRank) {
				$this->sendRankUpAlert($user, $careerRank, true);
			}
		}
	}
	
	/**
	 * Send achievement earned alert
	 */
	protected function sendAchievementAlert(User $user, AchievementEntity $achievement): void
	{
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		
		$alertRepo->alert(
			$user,
			0,
			'',
			'ic_sm_user_achievement',
			$achievement->achievement_id,
			'earned',
			[
				'title' => $achievement->title,
				'xp' => $achievement->xp_points
			]
		);
	}
	
	/**
	 * Send rank up alert
	 */
	protected function sendRankUpAlert(User $user, string $rankKey, bool $isCareer): void
	{
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		
		$alertRepo->alert(
			$user,
			0,
			'',
			'ic_sm_rank',
			0,
			'rank_up',
			[
				'rank' => \XF::phrase($rankKey)->render(),
				'is_career' => $isCareer
			]
		);
	}
	
	/**
	 * Check all achievements for a user in a given context
	 */
	public function checkAchievements(Account $account, string $context = 'trade', array $data = []): void
	{
		$user = $account->User;
		
		// Get all active achievements
		$achievements = $this->finder('IC\StockMarket:Achievement')
			->where('is_active', true)
			->fetch();
		
		foreach ($achievements as $achievement) {
			if ($this->checkAchievementCriteria($account, $achievement, $context, $data)) {
				$this->awardAchievement($user, $account, $achievement);
			}
		}
	}
	
	/**
	 * Check if user meets achievement criteria
	 */
	protected function checkAchievementCriteria(Account $account, AchievementEntity $achievement, string $context, array $data): bool
	{
		$key = $achievement->achievement_key;
		$user = $account->User;
		
		// Get trade count once for efficiency
		$tradeCount = null;
		if ($context === 'trade') {
			$tradeCount = $this->db()->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_sm_trade
				WHERE account_id = ?
			', $account->account_id);
		}
		
		// Trade count achievements
		if ($key === 'first_steps' && $context === 'trade') {
			return $tradeCount >= 1;
		}
		if ($key === 'getting_started' && $context === 'trade') {
			return $tradeCount >= 10; // Increased from 5
		}
		if ($key === 'active_trader' && $context === 'trade') {
			return $tradeCount >= 50; // Increased from 10
		}
		if ($key === 'consistent_trader' && $context === 'trade') {
			return $tradeCount >= 250; // Increased from 100
		}
		if ($key === 'volume_king' && $context === 'trade') {
			return $tradeCount >= 1000; // Increased from 500
		}
		
		// First buy/sell
		if ($key === 'first_purchase' && $context === 'trade' && isset($data['trade_type']) && $data['trade_type'] === 'buy') {
			$buyCount = $this->db()->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_sm_trade
				WHERE account_id = ? AND trade_type = ?
			', [$account->account_id, 'buy']);
			return $buyCount == 1;
		}
		if ($key === 'first_sale' && $context === 'trade' && isset($data['trade_type']) && $data['trade_type'] === 'sell') {
			$sellCount = $this->db()->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_sm_trade
				WHERE account_id = ? AND trade_type = ?
			', [$account->account_id, 'sell']);
			return $sellCount == 1;
		}
		
		// Day trading achievements
		if ($key === 'active_beginner' && $context === 'trade') {
			$todayTrades = $this->db()->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_sm_trade
				WHERE account_id = ? AND trade_date >= ?
			', [$account->account_id, strtotime('today')]);
			return $todayTrades >= 5; // Increased from 3
		}
		if ($key === 'day_trader' && $context === 'trade') {
			$todayTrades = $this->db()->fetchOne('
				SELECT COUNT(*)
				FROM xf_ic_sm_trade
				WHERE account_id = ? AND trade_date >= ?
			', [$account->account_id, strtotime('today')]);
			return $todayTrades >= 20; // Increased from 10
		}
		
		// Portfolio value achievements
		if ($context === 'portfolio_update') {
			$value = $account->total_value;
			
			if ($key === 'building_wealth') return $value >= 25000; // Increased from 10000
			if ($key === 'strategic_investor') return $value >= 100000; // Increased from 50000
			if ($key === 'portfolio_millionaire') return $value >= 1000000;
		}
		
		// Profit achievements (need to calculate)
		if ($context === 'portfolio_update') {
			$profit = $account->total_value - $account->initial_balance;
			
			if ($key === 'consistent_profit') return $profit >= 5000; // Increased from 1000
			if ($key === 'profit_champion') return $profit >= 250000; // Increased from 100000
		}
		
		// Diversity achievements
		if ($key === 'diverse_portfolio_5' && $context === 'portfolio_update') {
			$symbolCount = $this->db()->fetchOne('
				SELECT COUNT(DISTINCT symbol_id)
				FROM xf_ic_sm_position
				WHERE account_id = ? AND quantity > 0
			', $account->account_id);
			return $symbolCount >= 10; // Increased from 5
		}
		
		// Market exploration
		if ($key === 'market_explorer' && $context === 'portfolio_update') {
			$marketCount = $this->db()->fetchOne('
				SELECT COUNT(DISTINCT s.market_id)
				FROM xf_ic_sm_trade t
				JOIN xf_ic_sm_symbol s ON s.symbol_id = t.symbol_id
				WHERE t.account_id = ?
			', $account->account_id);
			return $marketCount >= 3; // Increased from 2
		}
		if ($key === 'market_diversification' && $context === 'portfolio_update') {
			$marketCount = $this->db()->fetchOne('
				SELECT COUNT(DISTINCT s.market_id)
				FROM xf_ic_sm_trade t
				JOIN xf_ic_sm_symbol s ON s.symbol_id = t.symbol_id
				WHERE t.account_id = ?
			', $account->account_id);
			return $marketCount >= 5; // Increased from 3
		}
		
		// Not implemented or doesn't match context
		return false;
	}
	
	/**
	 * Get user's earned achievements for a season
	 */
	public function getUserAchievements(int $userId, int $seasonId): array
	{
		return $this->finder('IC\StockMarket:UserAchievement')
			->with('Achievement')
			->where('user_id', $userId)
			->where('season_id', $seasonId)
			->order('earned_date', 'DESC')
			->fetch()
			->toArray();
	}
	
	/**
	 * Get achievement progress for user
	 */
	public function getAchievementProgress(Account $account): array
	{
		$achievements = $this->finder('IC\StockMarket:Achievement')
			->where('is_active', true)
			->order('display_order')
			->fetch();
		
		$earned = $this->finder('IC\StockMarket:UserAchievement')
			->where('user_id', $account->user_id)
			->where('season_id', $account->season_id)
			->fetch()
			->pluckNamed('achievement_id', 'achievement_id');
		
		$progress = [];
		
		foreach ($achievements as $achievement) {
			$progress[$achievement->achievement_id] = [
				'achievement' => $achievement,
				'earned' => isset($earned[$achievement->achievement_id]),
				'earned_date' => $earned[$achievement->achievement_id]->earned_date ?? null
			];
		}
		
		return $progress;
	}
}
