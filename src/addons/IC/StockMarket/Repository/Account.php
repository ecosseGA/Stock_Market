<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class Account extends Repository
{
	/**
	 * Get or create account for user in current season
	 */
	public function getOrCreateAccountForUser($userId, $seasonId = null)
	{
		if ($seasonId === null) {
			/** @var \IC\StockMarket\Repository\Season $seasonRepo */
			$seasonRepo = $this->repository('IC\StockMarket:Season');
			$season = $seasonRepo->getActiveSeason();
			$seasonId = $season ? $season->season_id : 1;
		}
		
		$account = $this->finder('IC\StockMarket:Account')
			->where('user_id', $userId)
			->where('season_id', $seasonId)
			->with('User')  // Always fetch User relation for DBTech Credits
			->fetchOne();
		
		if (!$account) {
			/** @var \IC\StockMarket\Entity\Season $season */
			$season = $this->em->find('IC\StockMarket:Season', $seasonId);
			$startingBalance = $season ? $season->starting_balance : 10000;
			
			$account = $this->em->create('IC\StockMarket:Account');
			$account->user_id = $userId;
			$account->season_id = $seasonId;
			$account->cash_balance = $startingBalance;
			$account->portfolio_value = 0;
			$account->total_value = $startingBalance;
			$account->initial_balance = $startingBalance;  // Track starting balance
			$account->created_date = \XF::$time;
			$account->save();
			
			// Fetch the account again with User relation
			$account = $this->finder('IC\StockMarket:Account')
				->where('account_id', $account->account_id)
				->with('User')
				->fetchOne();
		}
		
		return $account;
	}
	
	/**
	 * Get user's account for a season
	 */
	public function getUserAccount($userId, $seasonId = null)
	{
		if ($seasonId === null) {
			/** @var \IC\StockMarket\Repository\Season $seasonRepo */
			$seasonRepo = $this->repository('IC\StockMarket:Season');
			$season = $seasonRepo->getActiveSeason();
			$seasonId = $season ? $season->season_id : null;
		}
		
		if (!$seasonId) {
			return null;
		}
		
		return $this->finder('IC\StockMarket:Account')
			->where('user_id', $userId)
			->where('season_id', $seasonId)
			->with('User')  // Always fetch User relation for DBTech Credits
			->fetchOne();
	}
	
	/**
	 * Update account values (cash + portfolio)
	 */
	public function recalculateAccountValue($accountId)
	{
		/** @var \IC\StockMarket\Entity\Account $account */
		$account = $this->em->find('IC\StockMarket:Account', $accountId);
		
		if (!$account) {
			return false;
		}
		
		// Calculate portfolio value from positions
		$portfolioValue = 0;
		foreach ($account->Positions as $position) {
			$portfolioValue += $position->getCurrentValue();
		}
		
		$account->portfolio_value = $portfolioValue;
		$account->total_value = $account->cash_balance + $portfolioValue;
		$account->save();
		
		return true;
	}
}
