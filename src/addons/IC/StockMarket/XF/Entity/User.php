<?php

namespace IC\StockMarket\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Extends the XF User entity to add Stock Market permission methods
 * 
 * This ensures permissions work correctly with multiple usergroups (primary + secondary)
 * by using XenForo's built-in hasPermission() method which properly aggregates
 * permissions across all user groups.
 */
class User extends XFCP_User
{
	/**
	 * Check if user can view the stock market
	 * 
	 * @param null $error
	 * @return bool
	 */
	public function canViewStockMarket(&$error = null)
	{
		return $this->hasPermission('icStockMarket', 'view');
	}

	/**
	 * Check if user can trade stocks (buy and sell)
	 * 
	 * @param null $error
	 * @return bool
	 */
	public function canTradeStocks(&$error = null)
	{
		// Must be able to view AND have trade permission
		if (!$this->canViewStockMarket($error))
		{
			return false;
		}

		return $this->hasPermission('icStockMarket', 'trade');
	}

	/**
	 * Helper method to check Stock Market permission
	 * 
	 * @param string $permission
	 * @return bool
	 */
	public function hasStockMarketPermission($permission)
	{
		return $this->hasPermission('icStockMarket', $permission);
	}

	/**
	 * @param Structure $structure
	 * @return Structure
	 */
	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		return $structure;
	}
}
