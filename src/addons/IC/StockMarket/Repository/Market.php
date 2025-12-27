<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class Market extends Repository
{
	/**
	 * Get all active markets
	 */
	public function findActiveMarkets()
	{
		return $this->finder('IC\StockMarket:Market')
			->where('is_active', 1)
			->order('display_order');
	}
	
	/**
	 * Get all markets (including inactive) - for admin use
	 */
	public function findAllMarkets()
	{
		return $this->finder('IC\StockMarket:Market')
			->order('display_order');
	}
	
	/**
	 * Get market by code
	 */
	public function getMarketByCode($code)
	{
		return $this->finder('IC\StockMarket:Market')
			->where('market_code', $code)
			->fetchOne();
	}
	
	/**
	 * Get or create default market (for initial setup)
	 */
	public function getOrCreateDefaultMarket()
	{
		$market = $this->getMarketByCode('NYSE');
		
		if (!$market) {
			$market = $this->em->create('IC\StockMarket:Market');
			$market->market_code = 'NYSE';
			$market->market_name = 'New York Stock Exchange';
			$market->country_code = 'US';
			$market->timezone = 'America/New_York';
			$market->is_active = true;
			$market->display_order = 1;
			$market->save();
		}
		
		return $market;
	}
}
