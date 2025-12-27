<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $account_id
 * @property int $user_id
 * @property int $season_id
 * @property float $cash_balance
 * @property float $portfolio_value
 * @property float $total_value
 * @property float $initial_balance
 * @property int $created_date
 * @property int $season_xp
 * @property string|null $season_rank
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \IC\StockMarket\Entity\Season $Season
 * @property \XF\Mvc\Entity\AbstractCollection|\IC\StockMarket\Entity\Position[] $Positions
 * @property \XF\Mvc\Entity\AbstractCollection|\IC\StockMarket\Entity\Trade[] $Trades
 * @property \IC\StockMarket\Entity\UserCareer $Career
 */
class Account extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_account';
		$structure->shortName = 'IC\StockMarket:Account';
		$structure->primaryKey = 'account_id';
		
		$structure->columns = [
			'account_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'season_id' => ['type' => self::UINT, 'default' => 1],
			'cash_balance' => ['type' => self::FLOAT, 'default' => 10000],
			'portfolio_value' => ['type' => self::FLOAT, 'default' => 0],
			'total_value' => ['type' => self::FLOAT, 'default' => 10000],
			'initial_balance' => ['type' => self::FLOAT, 'default' => 10000],
			'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'season_xp' => ['type' => self::UINT, 'default' => 0],
			'season_rank' => ['type' => self::STR, 'maxLength' => 50, 'nullable' => true, 'default' => null]
		];
		
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Season' => [
				'entity' => 'IC\StockMarket:Season',
				'type' => self::TO_ONE,
				'conditions' => 'season_id'
			],
			'Positions' => [
				'entity' => 'IC\StockMarket:Position',
				'type' => self::TO_MANY,
				'conditions' => 'account_id'
			],
			'Trades' => [
				'entity' => 'IC\StockMarket:Trade',
				'type' => self::TO_MANY,
				'conditions' => 'account_id',
				'order' => ['trade_date', 'DESC']
			],
			'Career' => [
				'entity' => 'IC\StockMarket:UserCareer',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];
		
		$structure->getters = [
			'profit_loss' => true,
			'profit_loss_percent' => true,
			'actual_cash_balance' => true,
			'cash_balance_display' => true,
			'total_value_display' => true
		];
		
		return $structure;
	}
	
	/**
	 * Get profit/loss amount
	 */
	public function getProfitLoss()
	{
		$starting = $this->Season ? $this->Season->starting_balance : 10000;
		return $this->total_value - $starting;
	}
	
	/**
	 * Get profit/loss percentage
	 */
	public function getProfitLossPercent()
	{
		$starting = $this->Season ? $this->Season->starting_balance : 10000;
		if ($starting == 0) return 0;
		
		return (($this->total_value - $starting) / $starting) * 100;
	}
	
	/**
	 * Check if account can afford a purchase
	 */
	public function canAfford($amount)
	{
		return $this->getActualCashBalance() >= $amount;
	}
	
	/**
	 * Check if using external currency (e.g., DBTech Credits)
	 */
	public function isUsingExternalCurrency()
	{
		$currencyColumn = \XF::options()->icStockMarket_currency_column ?? '';
		return !empty($currencyColumn);
	}
	
	/**
	 * Get actual cash balance (from external currency or built-in)
	 */
	public function getActualCashBalance()
	{
		if (!$this->isUsingExternalCurrency()) {
			return $this->cash_balance;
		}
		
		// Get from external currency column
		if (!$this->User) {
			return 0;
		}
		
		$currencyHandler = new \IC\StockMarket\Service\CurrencyHandler(\XF::app());
		return $currencyHandler->getUserBalance($this->User, $this->season_id);
	}
	
	/**
	 * Get display cash balance (for templates)
	 */
	public function getCashBalanceDisplay()
	{
		return $this->getActualCashBalance();
	}
	
	/**
	 * Get display total value (for templates)
	 */
	public function getTotalValueDisplay()
	{
		if ($this->isUsingExternalCurrency()) {
			return $this->getActualCashBalance() + $this->portfolio_value;
		}
		
		return $this->total_value;
	}
	
	/**
	 * Recalculate and update portfolio value and total value
	 */
	public function updatePortfolioValue()
	{
		$portfolioValue = 0;
		
		// Sum up all position values
		foreach ($this->Positions as $position) {
			if ($position->Symbol && $position->Symbol->Quote) {
				$portfolioValue += $position->quantity * $position->Symbol->Quote->price;
			}
		}
		
		$this->portfolio_value = $portfolioValue;
		$this->total_value = $this->getActualCashBalance() + $portfolioValue;
		$this->save();
		
		return $this->total_value;
	}
}
