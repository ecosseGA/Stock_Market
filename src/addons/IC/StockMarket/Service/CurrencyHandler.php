<?php

namespace IC\StockMarket\Service;

use XF\App;
use XF\Entity\User;

/**
 * Handles currency operations - can integrate with external credit systems
 * or use built-in cash_balance
 */
class CurrencyHandler
{
	protected $app;
	
	public function __construct(App $app)
	{
		$this->app = $app;
	}
	
	/**
	 * Get user's available balance
	 * 
	 * @param User $user
	 * @param int $seasonId Optional season ID (for built-in system)
	 * @return float
	 */
	public function getUserBalance(User $user, $seasonId = null)
	{
		// Check if using external currency column (e.g., DBTech Credits)
		$currencyColumn = \XF::options()->icStockMarket_currency_column ?? '';
		
		if (!empty($currencyColumn) && $this->columnExists($currencyColumn)) {
			// Use external currency system - fetch fresh from database
			return $this->getBalanceFromColumn($user, $currencyColumn);
		}
		
		// Use built-in account system
		if (!$seasonId) {
			$seasonRepo = $this->app->repository('IC\StockMarket:Season');
			$season = $seasonRepo->getActiveSeason();
			$seasonId = $season ? $season->season_id : null;
		}
		
		if (!$seasonId) {
			return 0;
		}
		
		$accountRepo = $this->app->repository('IC\StockMarket:Account');
		$account = $accountRepo->getUserAccount($user->user_id, $seasonId);
		
		return $account ? $account->cash_balance : 0;
	}
	
	/**
	 * Get balance from specific xf_user column
	 */
	protected function getBalanceFromColumn(User $user, $columnName)
	{
		try {
			$db = $this->app->db();
			$balance = $db->fetchOne("
				SELECT {$columnName} 
				FROM xf_user 
				WHERE user_id = ?
			", $user->user_id);
			
			return floatval($balance ?? 0);
		} catch (\Exception $e) {
			\XF::logException($e, false, 'Stock Market: Error reading balance from column ' . $columnName);
			return 0;
		}
	}
	
	/**
	 * Adjust user's balance (add or subtract)
	 * 
	 * @param User $user
	 * @param float $amount Positive to add, negative to subtract
	 * @param int $seasonId Optional season ID
	 * @param string $description Transaction description
	 * @return bool Success
	 */
	public function adjustBalance(User $user, $amount, $seasonId = null, $description = '')
	{
		// Check if using external currency column (e.g., DBTech Credits)
		$currencyColumn = \XF::options()->icStockMarket_currency_column ?? '';
		
		if (!empty($currencyColumn) && $this->columnExists($currencyColumn)) {
			// Use external currency system
			return $this->adjustBalanceInColumn($user, $amount, $currencyColumn, $description);
		}
		
		// Use built-in account system
		if (!$seasonId) {
			$seasonRepo = $this->app->repository('IC\StockMarket:Season');
			$season = $seasonRepo->getActiveSeason();
			$seasonId = $season ? $season->season_id : null;
		}
		
		if (!$seasonId) {
			return false;
		}
		
		$accountRepo = $this->app->repository('IC\StockMarket:Account');
		$account = $accountRepo->getUserAccount($user->user_id, $seasonId);
		
		if (!$account) {
			// Auto-create account if it doesn't exist
			$account = $accountRepo->getOrCreateAccountForUser($user->user_id, $seasonId);
		}
		
		// Update balance atomically
		$db = $this->app->db();
		$db->query("
			UPDATE xf_ic_sm_account 
			SET cash_balance = cash_balance + ?
			WHERE account_id = ?
			AND (cash_balance + ?) >= 0
		", [$amount, $account->account_id, $amount]);
		
		// Verify update succeeded
		$account = $accountRepo->getUserAccount($user->user_id, $seasonId);
		return true;
	}
	
	/**
	 * Adjust balance in external currency column
	 */
	protected function adjustBalanceInColumn(User $user, $amount, $columnName, $description)
	{
		try {
			$db = $this->app->db();
			
			// Get balance before update
			$beforeBalance = $this->getBalanceFromColumn($user, $columnName);
			
			// Use database-level arithmetic to avoid race conditions
			$db->query("
				UPDATE xf_user 
				SET {$columnName} = {$columnName} + ?
				WHERE user_id = ?
				AND ({$columnName} + ?) >= 0
			", [$amount, $user->user_id, $amount]);
			
			// Get balance after update to verify
			$afterBalance = $this->getBalanceFromColumn($user, $columnName);
			
			// Check if update succeeded
			if ($beforeBalance === $afterBalance) {
				if ($beforeBalance + $amount < 0) {
					\XF::logError('Stock Market: Insufficient funds for user ' . $user->user_id . 
						' (has: ' . $beforeBalance . ', needs: ' . abs($amount) . ')');
					return false;
				}
				
				\XF::logError('Stock Market: Failed to adjust balance for user ' . $user->user_id);
				return false;
			}
			
			return true;
		} catch (\Exception $e) {
			\XF::logException($e, false, 'Stock Market: Error adjusting balance in column ' . $columnName);
			return false;
		}
	}
	
	/**
	 * Check if user can afford an amount
	 */
	public function canAfford(User $user, $amount, $seasonId = null)
	{
		return $this->getUserBalance($user, $seasonId) >= $amount;
	}
	
	/**
	 * Get currency name for display
	 */
	public function getCurrencyName()
	{
		$currencyColumn = \XF::options()->icStockMarket_currency_column ?? '';
		
		if (!empty($currencyColumn)) {
			// Extract readable name from column name
			// e.g. "dbtech_credits_points" -> "Points"
			$parts = explode('_', $currencyColumn);
			return ucfirst(end($parts));
		}
		
		return 'Cash';
	}
	
	/**
	 * Format currency amount for display
	 */
	public function formatCurrency($amount, $decimals = 2)
	{
		return '$' . number_format($amount, $decimals);
	}
	
	/**
	 * Check if a column exists in xf_user table
	 */
	protected function columnExists($columnName)
	{
		try {
			$db = $this->app->db();
			$columns = $db->fetchAllColumn("SHOW COLUMNS FROM xf_user");
			return in_array($columnName, $columns);
		} catch (\Exception $e) {
			return false;
		}
	}
	
	/**
	 * Check if using external currency system
	 */
	public function isUsingExternalCurrency()
	{
		$currencyColumn = \XF::options()->icStockMarket_currency_column ?? '';
		return !empty($currencyColumn) && $this->columnExists($currencyColumn);
	}
}
