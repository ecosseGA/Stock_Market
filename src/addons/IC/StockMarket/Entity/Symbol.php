<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $symbol_id
 * @property int $market_id
 * @property string $symbol
 * @property string $company_name
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $display_order
 *
 * RELATIONS
 * @property \IC\StockMarket\Entity\Market $Market
 * @property \IC\StockMarket\Entity\Quote $Quote
 */
class Symbol extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_symbol';
		$structure->shortName = 'IC\StockMarket:Symbol';
		$structure->primaryKey = 'symbol_id';
		
		$structure->columns = [
			'symbol_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'market_id' => ['type' => self::UINT, 'required' => true],
			'symbol' => ['type' => self::STR, 'maxLength' => 20, 'required' => true],
			'company_name' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'is_featured' => ['type' => self::BOOL, 'default' => false],
			'display_order' => ['type' => self::UINT, 'default' => 0]
		];
		
		$structure->relations = [
			'Market' => [
				'entity' => 'IC\StockMarket:Market',
				'type' => self::TO_ONE,
				'conditions' => 'market_id',
				'primary' => true
			],
			'Quote' => [
				'entity' => 'IC\StockMarket:Quote',
				'type' => self::TO_ONE,
				'conditions' => 'symbol_id'
			],
			'Watchlist' => [
				'entity' => 'IC\StockMarket:Watchlist',
				'type' => self::TO_MANY,
				'conditions' => 'symbol_id',
				'key' => 'user_id'
			]
		];
		
		return $structure;
	}
	
	/**
	 * Get current price from quote
	 */
	public function getCurrentPrice()
	{
		return $this->Quote ? $this->Quote->price : 0;
	}
	
	/**
	 * Get symbol for Yahoo Finance lookup
	 * Yahoo Finance doesn't use market prefixes, just the symbol
	 */
	public function getFullSymbol()
	{
		// For most US stocks, just return the symbol
		// Yahoo Finance handles AAPL, MSFT, GOOGL, etc. without exchange prefixes
		return $this->symbol;
	}
	
	/**
	 * Get symbol formatted for TradingView widget
	 * TradingView uses exchange prefix format: EXCHANGE:SYMBOL
	 * 
	 * @return string
	 */
	public function getTradingViewSymbol()
	{
		$symbol = $this->symbol;
		
		// Tokyo Stock Exchange: 6503.T → TSE:6503
		if (preg_match('/^(\d+)\.T$/', $symbol, $matches)) {
			return 'TSE:' . $matches[1];
		}
		
		// London Stock Exchange: VOD.L → LSE:VOD
		if (preg_match('/^([A-Z]+)\.L$/', $symbol, $matches)) {
			return 'LSE:' . $matches[1];
		}
		
		// German/Frankfurt (XETRA): VOW3.DE → XETRA:VOW3
		if (preg_match('/^(.+)\.DE$/', $symbol, $matches)) {
			return 'XETRA:' . $matches[1];
		}
		
		// French (Euronext Paris): MC.PA → EURONEXT:MC
		if (preg_match('/^(.+)\.PA$/', $symbol, $matches)) {
			return 'EURONEXT:' . $matches[1];
		}
		
		// Australian (ASX): BHP.AX → ASX:BHP
		if (preg_match('/^(.+)\.AX$/', $symbol, $matches)) {
			return 'ASX:' . $matches[1];
		}
		
		// Canadian (TSX): RY.TO → TSX:RY
		if (preg_match('/^(.+)\.TO$/', $symbol, $matches)) {
			return 'TSX:' . $matches[1];
		}
		
		// Indian (NSE): TCS.NS → NSE:TCS
		if (preg_match('/^(.+)\.NS$/', $symbol, $matches)) {
			return 'NSE:' . $matches[1];
		}
		
		// US stocks (no suffix) - return as-is
		// TradingView handles AAPL, GOOGL, MSFT without exchange prefix
		return $symbol;
	}
	
	/**
	 * Check if TradingView's free widget supports this symbol
	 * TradingView's embedded widget has exchange coverage for major markets
	 * 
	 * @return bool
	 */
	public function isTradingViewSupported()
	{
		$symbol = $this->symbol;
		
		// Tokyo Stock Exchange - Limited support in free widget
		if (preg_match('/\.T$/', $symbol)) {
			return false;
		}
		
		// London Stock Exchange - Limited support in free widget
		if (preg_match('/\.L$/', $symbol)) {
			return false;
		}
		
		// German/Frankfurt (XETRA) - Supported ✅
		if (preg_match('/\.DE$/', $symbol)) {
			return true;
		}
		
		// French (Euronext Paris) - Supported ✅
		if (preg_match('/\.PA$/', $symbol)) {
			return true;
		}
		
		// Australian (ASX) - Supported ✅
		if (preg_match('/\.AX$/', $symbol)) {
			return true;
		}
		
		// Canadian (TSX) - Supported ✅
		if (preg_match('/\.TO$/', $symbol)) {
			return true;
		}
		
		// Indian (NSE) - Supported ✅
		if (preg_match('/\.NS$/', $symbol)) {
			return true;
		}
		
		// US stocks (NYSE, NASDAQ) - Fully supported ✅
		return true;
	}
}
