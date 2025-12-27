<?php

namespace IC\StockMarket\Service;

use XF\App;

/**
 * Yahoo Finance API integration service
 * Fetches real-time stock quotes from Yahoo Finance
 */
class YahooFinance
{
	protected $app;
	
	public function __construct(App $app)
	{
		$this->app = $app;
	}
	
	/**
	 * Fetch quote for a single symbol
	 * 
	 * @param string $symbol Stock symbol (e.g., 'AAPL')
	 * @return array|null Quote data or null if failed
	 */
	public function fetchQuote($symbol)
	{
		$url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}";
		
		$params = [
			'interval' => '1d',
			'range' => '1d',
			'includePrePost' => 'false'
		];
		
		$url .= '?' . http_build_query($params);
		
		try {
			$response = $this->makeRequest($url);
			
			if (!$response) {
				return null;
			}
			
			$data = json_decode($response, true);
			
			if (!isset($data['chart']['result'][0])) {
				return null;
			}
			
			$quotes = $this->parseQuotes($data['chart']['result']);
			return $quotes[$symbol] ?? null;
			
		} catch (\Exception $e) {
			\XF::logError("Yahoo Finance API error for {$symbol}: " . $e->getMessage());
			return null;
		}
	}
	
	/**
	 * Fetch quotes for multiple symbols
	 * 
	 * @param array $symbols Array of stock symbols
	 * @return array Associative array of symbol => quote data
	 */
	public function fetchQuotes(array $symbols)
	{
		if (empty($symbols)) {
			return [];
		}
		
		// Yahoo Finance v8 API doesn't support multiple symbols in one call
		// We need to fetch each symbol individually
		$quotes = [];
		
		foreach ($symbols as $symbol) {
			$quote = $this->fetchQuote($symbol);
			if ($quote) {
				$quotes[$symbol] = $quote;
			}
		}
		
		return $quotes;
	}
	
	/**
	 * Make HTTP request to Yahoo Finance
	 */
	protected function makeRequest($url)
	{
		$client = $this->app->http()->client();
		
		try {
			$response = $client->get($url, [
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
				],
				'timeout' => 10
			]);
			
			if ($response->getStatusCode() === 200) {
				return $response->getBody()->getContents();
			}
			
		} catch (\Exception $e) {
			// Only log non-404 errors (404 = symbol doesn't exist, expected)
			if (strpos($e->getMessage(), '404') === false) {
				\XF::logError("HTTP request failed: " . $e->getMessage());
			}
		}
		
		return null;
	}
	
	/**
	 * Get detailed stock information including chart data
	 */
	public function fetchStockDetails($symbol)
	{
		// Fetch multiple ranges for chart
		$ranges = ['1d', '5d', '1mo', '3mo', '1y'];
		$details = [
			'symbol' => $symbol,
			'current' => null,
			'stats' => null,
			'historical' => []
		];
		
		// Get current data (1d with more info)
		$url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=5m&range=1d&includePrePost=false";
		$response = $this->makeRequest($url);
		
		if ($response) {
			$data = json_decode($response, true);
			if (isset($data['chart']['result'][0])) {
				$result = $data['chart']['result'][0];
				$meta = $result['meta'];
				
				$details['current'] = [
					'price' => $meta['regularMarketPrice'] ?? 0,
					'change' => ($meta['regularMarketPrice'] ?? 0) - ($meta['chartPreviousClose'] ?? 0),
					'change_percent' => $meta['chartPreviousClose'] ? 
						((($meta['regularMarketPrice'] ?? 0) - ($meta['chartPreviousClose'] ?? 0)) / $meta['chartPreviousClose'] * 100) : 0,
					'previous_close' => $meta['chartPreviousClose'] ?? 0,
					'day_high' => $meta['regularMarketDayHigh'] ?? 0,
					'day_low' => $meta['regularMarketDayLow'] ?? 0,
					'volume' => $meta['regularMarketVolume'] ?? 0,
					'currency' => $meta['currency'] ?? 'USD',
				];
				
				$details['stats'] = [
					'fifty_two_week_high' => $meta['fiftyTwoWeekHigh'] ?? 0,
					'fifty_two_week_low' => $meta['fiftyTwoWeekLow'] ?? 0,
					'market_cap' => $meta['marketCap'] ?? null,
					'exchange' => $meta['exchangeName'] ?? null,
				];
				
				// Get today's chart data
				if (isset($result['timestamp']) && isset($result['indicators']['quote'][0]['close'])) {
					$timestamps = $result['timestamp'];
					$closes = $result['indicators']['quote'][0]['close'];
					
					$details['historical']['1d'] = [];
					foreach ($timestamps as $i => $ts) {
						if (isset($closes[$i]) && $closes[$i] !== null) {
							$details['historical']['1d'][] = [
								'time' => $ts,
								'price' => $closes[$i]
							];
						}
					}
				}
			}
		}
		
		// Get longer range data (1mo for more history)
		$url1mo = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=1mo&includePrePost=false";
		$response1mo = $this->makeRequest($url1mo);
		
		if ($response1mo) {
			$data = json_decode($response1mo, true);
			if (isset($data['chart']['result'][0])) {
				$result = $data['chart']['result'][0];
				
				if (isset($result['timestamp']) && isset($result['indicators']['quote'][0]['close'])) {
					$timestamps = $result['timestamp'];
					$closes = $result['indicators']['quote'][0]['close'];
					
					$details['historical']['1mo'] = [];
					foreach ($timestamps as $i => $ts) {
						if (isset($closes[$i]) && $closes[$i] !== null) {
							$details['historical']['1mo'][] = [
								'time' => $ts,
								'price' => $closes[$i]
							];
						}
					}
				}
			}
		}
		
		return $details;
	}
	
	/**
	 * Parse Yahoo Finance API response into quote data
	 */
	protected function parseQuotes($results)
	{
		$quotes = [];
		
		foreach ($results as $result) {
			if (!isset($result['meta']['symbol'])) {
				continue;
			}
			
			$symbol = $result['meta']['symbol'];
			$meta = $result['meta'];
			
			// Get current price
			$currentPrice = $meta['regularMarketPrice'] ?? null;
			$previousClose = $meta['chartPreviousClose'] ?? null;
			
			if (!$currentPrice) {
				continue;
			}
			
			// Calculate change
			$changeAmount = $previousClose ? ($currentPrice - $previousClose) : 0;
			$changePercent = $previousClose && $previousClose > 0 
				? (($changeAmount / $previousClose) * 100) 
				: 0;
			
			// Get volume
			$volume = 0;
			if (isset($result['indicators']['quote'][0]['volume'])) {
				$volumes = array_filter($result['indicators']['quote'][0]['volume']);
				$volume = !empty($volumes) ? end($volumes) : 0;
			}
			
			$quotes[$symbol] = [
				'symbol' => $symbol,
				'price' => $currentPrice,
				'change_amount' => $changeAmount,
				'change_percent' => $changePercent,
				'volume' => $volume,
				'previous_close' => $previousClose,
				'market_cap' => $meta['marketCap'] ?? null,
				'currency' => $meta['currency'] ?? 'USD',
				'exchange' => $meta['exchangeName'] ?? null,
				'timestamp' => time()
			];
		}
		
		return $quotes;
	}
	
	/**
	 * Search for symbols by query
	 * 
	 * @param string $query Search term
	 * @return array Array of search results
	 */
	public function searchSymbols($query)
	{
		$url = "https://query1.finance.yahoo.com/v1/finance/search";
		
		$params = [
			'q' => $query,
			'quotesCount' => 10,
			'newsCount' => 0,
			'enableFuzzyQuery' => false
		];
		
		$url .= '?' . http_build_query($params);
		
		try {
			$response = $this->makeRequest($url);
			
			if (!$response) {
				return [];
			}
			
			$data = json_decode($response, true);
			
			if (!isset($data['quotes'])) {
				return [];
			}
			
			$results = [];
			foreach ($data['quotes'] as $quote) {
				if (isset($quote['symbol']) && isset($quote['shortname'])) {
					$results[] = [
						'symbol' => $quote['symbol'],
						'name' => $quote['shortname'],
						'exchange' => $quote['exchange'] ?? null,
						'type' => $quote['quoteType'] ?? null
					];
				}
			}
			
			return $results;
			
		} catch (\Exception $e) {
			\XF::logError("Yahoo Finance search error: " . $e->getMessage());
			return [];
		}
	}
}
