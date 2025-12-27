<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class Symbol extends Repository
{
	/**
	 * Find active symbols with quotes
	 */
	public function findActiveSymbolsWithQuotes()
	{
		return $this->finder('IC\StockMarket:Symbol')
			->with('Quote')
			->with('Market')
			->where('is_active', 1)
			->where('Market.is_active', 1);  // Only show symbols from active markets
			// No default order - controller will apply sort
	}
	
	/**
	 * Find featured symbols
	 */
	public function findFeaturedSymbols($limit = 10)
	{
		return $this->finder('IC\StockMarket:Symbol')
			->with('Quote')
			->with('Market')
			->where('is_active', 1)
			->where('is_featured', 1)
			->where('Market.is_active', 1)  // Only show symbols from active markets
			->order('display_order')
			->limit($limit);
	}
	
	/**
	 * Find top gainers
	 */
	public function findTopGainers($limit = 10)
	{
		return $this->finder('IC\StockMarket:Symbol')
			->with('Quote')
			->with('Market')
			->where('is_active', 1)
			->where('Market.is_active', 1)  // Only show symbols from active markets
			->where('Quote.change_percent', '>', 0)
			->order('Quote.change_percent', 'DESC')
			->limit($limit);
	}
	
	/**
	 * Find top losers
	 */
	public function findTopLosers($limit = 10)
	{
		return $this->finder('IC\StockMarket:Symbol')
			->with('Quote')
			->with('Market')
			->where('is_active', 1)
			->where('Market.is_active', 1)  // Only show symbols from active markets
			->where('Quote.change_percent', '<', 0)
			->order('Quote.change_percent', 'ASC')
			->limit($limit);
	}
	
	/**
	 * Get symbol by ticker
	 */
	public function getSymbolByTicker($ticker, $marketId = null)
	{
		$finder = $this->finder('IC\StockMarket:Symbol')
			->where('symbol', $ticker);
		
		if ($marketId) {
			$finder->where('market_id', $marketId);
		}
		
		return $finder->fetchOne();
	}
	
	/**
	 * Search symbols by ticker or company name
	 */
	public function searchSymbols($query, $limit = 20)
	{
		return $this->finder('IC\StockMarket:Symbol')
			->with('Quote')
			->with('Market')
			->where('is_active', 1)
			->where('Market.is_active', 1)  // Only show symbols from active markets
			->whereSql('symbol LIKE ? OR company_name LIKE ?', [
				'%' . $query . '%',
				'%' . $query . '%'
			])
			->order('symbol')
			->limit($limit);
	}
}
