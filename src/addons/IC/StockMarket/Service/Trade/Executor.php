<?php

namespace IC\StockMarket\Service\Trade;

use IC\StockMarket\Entity\Account;
use IC\StockMarket\Entity\Symbol;
use XF\App;

/**
 * Service for executing buy/sell trades
 */
class Executor
{
	protected $app;
	protected $account;
	protected $symbol;
	protected $currencyHandler;
	
	public function __construct(App $app, Account $account, Symbol $symbol)
	{
		$this->app = $app;
		$this->account = $account;
		$this->symbol = $symbol;
		$this->currencyHandler = new \IC\StockMarket\Service\CurrencyHandler($app);
	}
	
	/**
	 * Execute a buy order
	 * 
	 * @param int $quantity Number of shares to buy
	 * @param float $price Price per share
	 * @return bool Success
	 */
	public function buy($quantity, $price)
	{
		// Check if market is open
		$market = $this->symbol->Market;
		if (!$market || !$market->isMarketOpen()) {
			return false;
		}
		
		$totalCost = $quantity * $price;
		
		// Get user
		$user = $this->account->User;
		if (!$user) {
			\XF::logError('Stock Market: User not found for account ' . $this->account->account_id);
			return false;
		}
		
		// Check if user can afford (using CurrencyHandler which handles external currency)
		if (!$this->currencyHandler->canAfford($user, $totalCost, $this->account->season_id)) {
			return false;
		}
		
		$db = $this->app->db();
		$db->beginTransaction();
		
		try {
			// Deduct currency
			$description = sprintf(
				'Buy %d shares of %s at $%.2f',
				$quantity,
				$this->symbol->symbol,
				$price
			);
			
			if (!$this->currencyHandler->adjustBalance($user, -$totalCost, $this->account->season_id, $description)) {
				$db->rollback();
				return false;
			}
			
			// Update position (create or add to existing)
			$position = $this->app->finder('IC\StockMarket:Position')
				->where('account_id', $this->account->account_id)
				->where('symbol_id', $this->symbol->symbol_id)
				->fetchOne();
			
			if ($position) {
				// Update existing position
				$oldQuantity = $position->quantity;
				$newQuantity = $oldQuantity + $quantity;
				
				// Calculate new weighted average price
				$position->average_price = (($position->average_price * $oldQuantity) + ($price * $quantity)) / $newQuantity;
				$position->quantity = $newQuantity;
				$position->total_cost = $position->average_price * $newQuantity;
				$position->last_updated = \XF::$time;
			} else {
				// Create new position
				$position = $this->app->em()->create('IC\StockMarket:Position');
				$position->account_id = $this->account->account_id;
				$position->symbol_id = $this->symbol->symbol_id;
				$position->quantity = $quantity;
				$position->average_price = $price;
				$position->total_cost = $price * $quantity;
				$position->last_updated = \XF::$time;
			}
			$position->save();
			
			// Record trade
			$trade = $this->app->em()->create('IC\StockMarket:Trade');
			$trade->account_id = $this->account->account_id;
			$trade->symbol_id = $this->symbol->symbol_id;
			$trade->trade_type = 'buy';
			$trade->quantity = $quantity;
			$trade->price = $price;
			$trade->total_cost = $totalCost;
			$trade->trade_date = \XF::$time;
			$trade->save();
			
			// Update account cash balance (if using built-in system)
			if (!$this->currencyHandler->isUsingExternalCurrency()) {
				$this->account->cash_balance -= $totalCost;
				$this->account->save();
			}
			
			// Update portfolio value and total value
			$this->account->updatePortfolioValue();
			
			// Check for achievements
			/** @var \IC\StockMarket\Service\Achievement $achievementService */
			$achievementService = $this->app->service('IC\StockMarket:Achievement');
			$achievementService->checkAchievements($this->account, 'trade', ['trade_type' => 'buy']);
			$achievementService->checkAchievements($this->account, 'portfolio_update');
			
			$db->commit();
			return true;
			
		} catch (\Exception $e) {
			$db->rollback();
			\XF::logException($e, false, 'Stock Market: Buy trade failed');
			return false;
		}
	}
	
	/**
	 * Execute a sell order
	 * 
	 * @param int $quantity Number of shares to sell
	 * @param float $price Price per share
	 * @return bool Success
	 */
	public function sell($quantity, $price)
	{
		// Check if market is open
		$market = $this->symbol->Market;
		if (!$market || !$market->isMarketOpen()) {
			return false;
		}
		
		$totalValue = $quantity * $price;
		
		// Get user
		$user = $this->account->User;
		if (!$user) {
			\XF::logError('Stock Market: User not found for account ' . $this->account->account_id);
			return false;
		}
		
		// Check if user has enough shares
		$position = $this->app->finder('IC\StockMarket:Position')
			->where('account_id', $this->account->account_id)
			->where('symbol_id', $this->symbol->symbol_id)
			->fetchOne();
		
		if (!$position || $position->quantity < $quantity) {
			return false;
		}
		
		$db = $this->app->db();
		$db->beginTransaction();
		
		try {
			// Add currency
			$description = sprintf(
				'Sell %d shares of %s at $%.2f',
				$quantity,
				$this->symbol->symbol,
				$price
			);
			
			if (!$this->currencyHandler->adjustBalance($user, $totalValue, $this->account->season_id, $description)) {
				$db->rollback();
				return false;
			}
			
			// Update position
			$remainingQuantity = $position->quantity - $quantity;
			
			if ($remainingQuantity <= 0) {
				// Delete position if no shares left
				// Don't modify it first - just delete
				$position->delete();
			} else {
				// Update position with remaining shares
				$position->quantity = $remainingQuantity;
				$position->total_cost = $position->average_price * $remainingQuantity;
				$position->last_updated = \XF::$time;
				$position->save();
			}
			
			// Record trade
			$trade = $this->app->em()->create('IC\StockMarket:Trade');
			$trade->account_id = $this->account->account_id;
			$trade->symbol_id = $this->symbol->symbol_id;
			$trade->trade_type = 'sell';
			$trade->quantity = $quantity;
			$trade->price = $price;
			$trade->total_cost = $totalValue;
			$trade->trade_date = \XF::$time;
			$trade->save();
			
			// Update account cash balance (if using built-in system)
			if (!$this->currencyHandler->isUsingExternalCurrency()) {
				$this->account->cash_balance += $totalValue;
				$this->account->save();
			}
			
			// Update portfolio value and total value
			$this->account->updatePortfolioValue();
			
			// Check for achievements
			/** @var \IC\StockMarket\Service\Achievement $achievementService */
			$achievementService = $this->app->service('IC\StockMarket:Achievement');
			$achievementService->checkAchievements($this->account, 'trade', ['trade_type' => 'sell']);
			$achievementService->checkAchievements($this->account, 'portfolio_update');
			
			$db->commit();
			return true;
			
		} catch (\Exception $e) {
			$db->rollback();
			\XF::logException($e, false, 'Stock Market: Sell trade failed');
			return false;
		}
	}
}
