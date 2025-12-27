<?php

namespace IC\StockMarket\Service;

use IC\StockMarket\Entity\Symbol;
use XF\App;

/**
 * Service for updating stock quotes from Yahoo Finance
 */
class QuoteUpdater
{
	protected $app;
	protected $yahooFinance;
	
	public function __construct(App $app)
	{
		$this->app = $app;
		$this->yahooFinance = new YahooFinance($app);
	}
	
	/**
	 * Update quotes for all active symbols
	 * 
	 * @return array Stats about the update
	 */
	public function updateAllQuotes()
	{
		$symbolRepo = $this->app->repository('IC\StockMarket:Symbol');
		$symbols = $symbolRepo->finder('IC\StockMarket:Symbol')
			->where('is_active', 1)
			->with('Market')
			->fetch();
		
		if ($symbols->count() === 0) {
			return [
				'total' => 0,
				'updated' => 0,
				'failed' => 0
			];
		}
		
		// Group symbols by market to get full symbol (e.g., AAPL for NYSE)
		$symbolList = [];
		$symbolMap = [];
		
		foreach ($symbols as $symbol) {
			$fullSymbol = $symbol->getFullSymbol();
			$symbolList[] = $fullSymbol;
			$symbolMap[$fullSymbol] = $symbol;
		}
		
		// Fetch quotes from Yahoo Finance (in batches of 50)
		$updated = 0;
		$failed = 0;
		
		$batches = array_chunk($symbolList, 50, true);
		
		foreach ($batches as $batch) {
			$quotes = $this->yahooFinance->fetchQuotes($batch);
			
			foreach ($batch as $fullSymbol) {
				$symbol = $symbolMap[$fullSymbol];
				
				if (isset($quotes[$fullSymbol])) {
					$quoteData = $quotes[$fullSymbol];
					
					if ($this->updateQuote($symbol, $quoteData)) {
						$updated++;
					} else {
						$failed++;
					}
				} else {
					// No quote data returned from Yahoo Finance
					$failed++;
				}
			}
		}
		
		return [
			'total' => count($symbolList),
			'updated' => $updated,
			'failed' => $failed
		];
	}
	
	/**
	 * Update quote for a specific symbol
	 * 
	 * @param Symbol $symbol
	 * @return bool Success
	 */
	public function updateSymbolQuote(Symbol $symbol)
	{
		$fullSymbol = $symbol->getFullSymbol();
		$quoteData = $this->yahooFinance->fetchQuote($fullSymbol);
		
		if (!$quoteData) {
			return false;
		}
		
		return $this->updateQuote($symbol, $quoteData);
	}
	
	/**
	 * Save quote data to database
	 * 
	 * @param Symbol $symbol
	 * @param array $quoteData
	 * @return bool
	 */
	protected function updateQuote(Symbol $symbol, array $quoteData)
	{
		try {
			// Find existing quote or create new one
			$quote = $this->app->em()->findOne('IC\StockMarket:Quote', [
				'symbol_id' => $symbol->symbol_id
			]);
			
			if (!$quote) {
				$quote = $this->app->em()->create('IC\StockMarket:Quote');
				$quote->symbol_id = $symbol->symbol_id;
			}
			
			// Validate data before saving
			if (!is_numeric($quoteData['price']) || $quoteData['price'] < 0) {
				// Invalid price data - silently fail (Yahoo Finance issue)
				return false;
			}
			
			// Update quote data with bounds checking
			$quote->price = min($quoteData['price'], 9999999999999.99); // Max for DECIMAL(15,2)
			$quote->change_amount = isset($quoteData['change_amount']) && is_numeric($quoteData['change_amount']) 
				? max(min($quoteData['change_amount'], 9999999999999.99), -9999999999999.99)
				: 0;
			$quote->change_percent = isset($quoteData['change_percent']) && is_numeric($quoteData['change_percent'])
				? max(min($quoteData['change_percent'], 999999.9999), -999999.9999)
				: 0;
			$quote->volume = isset($quoteData['volume']) && is_numeric($quoteData['volume'])
				? $quoteData['volume']
				: 0;
			$quote->last_updated = $quoteData['timestamp'];
			
			$quote->save();
			
			return true;
			
		} catch (\Exception $e) {
			// Only log actual database errors (not data issues)
			if (strpos($e->getMessage(), 'MySQL') !== false || strpos($e->getMessage(), 'SQL') !== false) {
				\XF::logError("Failed to save quote for {$symbol->symbol}: " . $e->getMessage(), false);
			}
			return false;
		}
	}
}
