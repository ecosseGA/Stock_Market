<?php

namespace IC\StockMarket;

use XF\Container;

/**
 * Event listener class for Stock Market addon
 * 
 * This class handles XenForo code event hooks to integrate the addon
 * with the XenForo lifecycle and other addons.
 */
class Listener
{
	/**
	 * App setup - register custom containers
	 * 
	 * @param \XF\App $app
	 */
	public static function appSetup(\XF\App $app)
	{
		// Future: Register custom containers like active season, market hours, etc.
	}

	/**
	 * User content change initialization
	 * Handles username changes across Stock Market tables
	 * 
	 * @param \XF\Service\User\ContentChange $changeService
	 * @param array $updates
	 */
	public static function userContentChangeInit(\XF\Service\User\ContentChange $changeService, array &$updates)
	{
		// Update usernames in Trade table
		$updates['xf_ic_sm_trade'] = ['user_id', 'username'];
		
		// Update usernames in Account table
		$updates['xf_ic_sm_account'] = ['user_id', 'username'];
		
		// Future: Add more tables as needed
	}

	/**
	 * User delete cleanup initialization
	 * Handles cleanup when a user is deleted
	 * 
	 * @param \XF\Service\User\DeleteCleanUp $deleteService
	 * @param array $deletes
	 */
	public static function userDeleteCleanInit(\XF\Service\User\DeleteCleanUp $deleteService, array &$deletes)
	{
		// Delete user's watchlist entries
		$deletes['xf_ic_sm_watchlist'] = 'user_id = ?';
		
		// Note: Don't delete accounts/trades as they're historical records
		// Instead, they're handled by userContentChangeInit username updates
	}

	/**
	 * User merge combine
	 * Handles merging user data when users are merged
	 * 
	 * @param \XF\Entity\User $target
	 * @param \XF\Entity\User $source
	 * @param \XF\Service\User\Merge $mergeService
	 */
	public static function userMergeCombine(
		\XF\Entity\User $target, 
		\XF\Entity\User $source, 
		\XF\Service\User\Merge $mergeService
	)
	{
		// Future: Merge trading statistics, achievement points, etc.
		// For now, the database foreign keys will handle the basic merge
	}

	/**
	 * User criteria extension
	 * Adds custom user criteria for promotions, trophies, etc.
	 * 
	 * @param string $rule
	 * @param array $data
	 * @param \XF\Entity\User $user
	 * @param mixed $returnValue
	 */
	public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
	{
		// Future: Add criteria like:
		// - ic_sm_total_trades (total number of trades)
		// - ic_sm_portfolio_value (current portfolio value)
		// - ic_sm_achievement_points (achievement points earned)
		// - ic_sm_win_rate (trading win rate percentage)
		
		// Example implementation (not active yet):
		/*
		switch ($rule)
		{
			case 'ic_sm_total_trades':
				// Check if user has made X trades
				$stats = $user->StockMarketStats ?? null;
				if ($stats && $stats->total_trades >= $data['trades'])
				{
					$returnValue = true;
				}
				break;
		}
		*/
	}

	/**
	 * User searcher orders
	 * Adds custom sorting options to user search
	 * 
	 * @param \XF\Searcher\User $userSearcher
	 * @param array $sortOrders
	 */
	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		// Future: Add sort options like:
		// - Portfolio value
		// - Total trades
		// - Achievement points
		// - Win rate
		
		// Example:
		// $sortOrders['ic_sm_portfolio_value'] = \XF::phrase('ic_sm_portfolio_value');
	}

	/**
	 * Templater setup
	 * Registers custom template functions
	 * 
	 * @param Container $container
	 * @param \XF\Template\Templater $templater
	 */
	public static function templaterSetup(Container $container, \XF\Template\Templater &$templater)
	{
		// Future: Add custom template functions like:
		// - format_price (format stock prices with proper decimals)
		// - format_change (format price changes with + and color)
		// - market_status (check if market is open)
		
		// Example:
		// $templater->addFunction('sm_format_price', [self::class, 'formatPrice']);
	}

	/**
	 * Helper method to get visitor as Stock Market extended User entity
	 * 
	 * @return \IC\StockMarket\XF\Entity\User
	 */
	public static function visitor()
	{
		/** @var \IC\StockMarket\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		return $visitor;
	}
}
