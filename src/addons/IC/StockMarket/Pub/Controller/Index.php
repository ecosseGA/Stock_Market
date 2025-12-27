<?php

namespace IC\StockMarket\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Public stock market controller
 */
class Index extends AbstractController
{
	/**
	 * Main stock market page - shows symbol list and user portfolio
	 */
	public function actionIndex()
	{
		// Check permission
		if (!\XF::visitor()->hasPermission('icStockMarket', 'view')) {
			return $this->noPermission();
		}
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		if (!$season) {
			return $this->message(\XF::phrase('ic_sm_no_active_season'));
		}
		
		// Pagination
		$page = $this->filterPage();
		$perPage = $this->options()->icStockMarket_stocks_per_page;
		
		// Market filter
		$marketId = $this->filter('market_id', 'uint');
		$selectedMarket = null;
		if ($marketId) {
			$selectedMarket = $this->em()->find('IC\StockMarket:Market', $marketId);
		}
		
		// Sort parameter
		$sort = $this->filter('sort', 'str');
		$validSorts = ['symbol_asc', 'symbol_desc', 'name_asc', 'name_desc', 'change_desc', 'change_asc'];
		if (!in_array($sort, $validSorts)) {
			$sort = 'symbol_asc'; // Default sort
		}
		
		// Search parameter
		$search = $this->filter('search', 'str');
		
		// Get symbol list with quotes
		$symbolRepo = $this->repository('IC\StockMarket:Symbol');
		$symbolFinder = $symbolRepo->findActiveSymbolsWithQuotes();
		
		// Apply market filter if selected
		if ($marketId) {
			$symbolFinder->where('market_id', $marketId);
		}
		
		// Apply search filter
		if ($search) {
			$searchTerm = '%' . addcslashes($search, '%_\\') . '%';
			$symbolFinder->whereOr(
				['symbol', 'LIKE', $searchTerm],
				['company_name', 'LIKE', $searchTerm]
			);
		}
		
		// Apply sorting
		switch ($sort) {
			case 'symbol_asc':
				$symbolFinder->order('symbol', 'ASC');
				break;
			case 'symbol_desc':
				$symbolFinder->order('symbol', 'DESC');
				break;
			case 'name_asc':
				$symbolFinder->order('company_name', 'ASC');
				break;
			case 'name_desc':
				$symbolFinder->order('company_name', 'DESC');
				break;
			case 'change_desc':
				$symbolFinder->order(['Quote.change_percent', 'DESC']);
				break;
			case 'change_asc':
				$symbolFinder->order(['Quote.change_percent', 'ASC']);
				break;
		}
		
		$total = $symbolFinder->total();
		$symbolFinder->limitByPage($page, $perPage);
		$symbols = $symbolFinder->fetch();
		
		$this->assertValidPage($page, $perPage, $total, 'stock-market');
		$this->assertCanonicalUrl($this->buildPaginatedLink('stock-market', null, $page));
		
		// Get user's account if logged in
		$account = null;
		$portfolio = null;
		
		// Computed balance values (avoids entity cache issues)
		$cashBalanceDisplay = 0;
		$portfolioValueDisplay = 0;
		$totalValueDisplay = 0;
		
		if (\XF::visitor()->user_id) {
			$accountRepo = $this->repository('IC\StockMarket:Account');
			$account = $accountRepo->getUserAccount(\XF::visitor()->user_id, $season->season_id);
			
			if ($account) {
				// Update portfolio value if needed (for existing accounts)
				if ($account->portfolio_value == 0) {
					$account->updatePortfolioValue();
				}
				
				// Compute display values using CurrencyHandler
				$currencyHandler = new \IC\StockMarket\Service\CurrencyHandler($this->app);
				$isExternal = $currencyHandler->isUsingExternalCurrency();
				
				if ($isExternal) {
					$cashBalanceDisplay = $currencyHandler->getUserBalance(\XF::visitor(), $season->season_id);
					$portfolioValueDisplay = $account->portfolio_value;
					$totalValueDisplay = $cashBalanceDisplay + $portfolioValueDisplay;
				} else {
					$cashBalanceDisplay = $account->cash_balance;
					$portfolioValueDisplay = $account->portfolio_value;
					$totalValueDisplay = $account->total_value;
				}
				
				// Get portfolio (positions) for portfolio page link
				$portfolio = $this->finder('IC\StockMarket:Position')
					->where('account_id', $account->account_id)
					->with('Symbol.Quote')
					->fetch();
			}
		}
		
		// Get all markets for filter dropdown
		$markets = $this->finder('IC\StockMarket:Market')
			->where('is_active', 1)
			->order('display_order')
			->fetch();
		
		// Prepare market data for dashboard on index page
		$marketRepo = $this->repository('IC\\StockMarket:Market');
		$activeMarkets = $marketRepo->findActiveMarkets()->fetch();
		$marketDataForIndex = [];
		
		foreach ($activeMarkets as $market)
		{
			$symbolCount = $this->finder('IC\\StockMarket:Symbol')
				->where('market_id', $market->market_id)
				->where('is_active', 1)
				->total();
			
			$marketDataForIndex[] = [
				'market' => $market,
				'symbol_count' => $symbolCount,
				'status' => $market->getMarketStatus(),
				'is_open' => $market->isMarketOpen(),
				'time_until_open' => $market->getTimeUntilOpenFormatted(),
				'time_until_close' => $market->getTimeUntilCloseFormatted(),
				'current_time' => $market->getMarketDateTime()->getTimestamp(),
				'current_time_formatted' => $market->getMarketDateTime()->format('g:i A'),
				'timezone_abbr' => $market->getMarketDateTime()->format('T')
			];
		}
		
		$viewParams = [
			'season' => $season,
			'symbols' => $symbols,
			'account' => $account,
			'portfolio' => $portfolio,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			// Pass computed values directly
			'cashBalanceDisplay' => $cashBalanceDisplay,
			'portfolioValueDisplay' => $portfolioValueDisplay,
			'totalValueDisplay' => $totalValueDisplay,
			// Market filtering
			'markets' => $markets,
			'selectedMarket' => $selectedMarket,
			'marketId' => $marketId,
			// Sorting and search
			'sort' => $sort,
			'search' => $search,
			// Market dashboard data
			'marketData' => $marketDataForIndex
		];
		
		return $this->view('IC\StockMarket:Index', 'ic_sm_index', $viewParams);
	}
	
	/**
	 * Buy stock
	 */
	public function actionBuy(ParameterBag $params)
	{
		$symbol = $this->assertSymbolExists($params->symbol_id);
		
		if (!\XF::visitor()->hasPermission('icStockMarket', 'trade')) {
			return $this->noPermission();
		}
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		if (!$season) {
			return $this->error(\XF::phrase('ic_sm_no_active_season'));
		}
		
		// Get or create user account
		$accountRepo = $this->repository('IC\StockMarket:Account');
		$account = $accountRepo->getOrCreateAccountForUser(\XF::visitor()->user_id, $season->season_id);
		
		// Get current price
		$price = $symbol->getCurrentPrice();
		if ($price <= 0) {
			return $this->error(\XF::phrase('ic_sm_no_price_data'));
		}
		
		// Compute cash balance display
		$currencyHandler = new \IC\StockMarket\Service\CurrencyHandler($this->app);
		$cashBalanceDisplay = $currencyHandler->getUserBalance(\XF::visitor(), $season->season_id);
		
		if ($this->isPost()) {
			$quantity = $this->filter('quantity', 'uint');
			
			if ($quantity < 1) {
				return $this->error(\XF::phrase('ic_sm_invalid_quantity'));
			}
			
			$totalCost = $price * $quantity;
			
			// Check if user can afford
			if ($cashBalanceDisplay < $totalCost) {
				return $this->error(\XF::phrase('ic_sm_insufficient_funds'));
			}
			
			// Check if market is open
			$market = $symbol->Market;
			if (!$market || !$market->isMarketOpen()) {
				return $this->error(\XF::phrase('ic_sm_market_closed'));
			}
			
			// Execute trade
			$tradeService = \XF::service('IC\StockMarket:Trade\Executor', $account, $symbol);
			$result = $tradeService->buy($quantity, $price);
			
			if ($result) {
				return $this->redirect($this->buildLink('stock-market'), \XF::phrase('ic_sm_trade_successful'));
			} else {
				return $this->error(\XF::phrase('ic_sm_trade_failed'));
			}
		} else {
			// Show buy form
			$viewParams = [
				'symbol' => $symbol,
				'account' => $account,
				'price' => $price,
				'cashBalanceDisplay' => $cashBalanceDisplay
			];
			
			return $this->view('IC\StockMarket:Buy', 'ic_sm_buy', $viewParams);
		}
	}
	
	/**
	 * Sell stock
	 */
	public function actionSell(ParameterBag $params)
	{
		$symbol = $this->assertSymbolExists($params->symbol_id);
		
		if (!\XF::visitor()->hasPermission('icStockMarket', 'trade')) {
			return $this->noPermission();
		}
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		if (!$season) {
			return $this->error(\XF::phrase('ic_sm_no_active_season'));
		}
		
		// Get user account
		$accountRepo = $this->repository('IC\StockMarket:Account');
		$account = $accountRepo->getUserAccount(\XF::visitor()->user_id, $season->season_id);
		
		if (!$account) {
			return $this->error(\XF::phrase('ic_sm_no_account'));
		}
		
		// Check if user owns this stock
		$position = $this->finder('IC\StockMarket:Position')
			->where('account_id', $account->account_id)
			->where('symbol_id', $symbol->symbol_id)
			->fetchOne();
		
		if (!$position) {
			return $this->error(\XF::phrase('ic_sm_insufficient_shares'));
		}
		
		// Get current price
		$price = $symbol->getCurrentPrice();
		if ($price <= 0) {
			return $this->error(\XF::phrase('ic_sm_no_price_data'));
		}
		
		if ($this->isPost()) {
			$quantity = $this->filter('quantity', 'uint');
			
			if ($quantity < 1) {
				return $this->error(\XF::phrase('ic_sm_invalid_quantity'));
			}
			
			if ($position->quantity < $quantity) {
				return $this->error(\XF::phrase('ic_sm_insufficient_shares'));
			}
			
			// Check if market is open
			$market = $symbol->Market;
			if (!$market || !$market->isMarketOpen()) {
				return $this->error(\XF::phrase('ic_sm_market_closed'));
			}
			
			// Execute trade
			$tradeService = \XF::service('IC\StockMarket:Trade\Executor', $account, $symbol);
			$result = $tradeService->sell($quantity, $price);
			
			if ($result) {
				return $this->redirect($this->buildLink('stock-market'), \XF::phrase('ic_sm_trade_successful'));
			} else {
				return $this->error(\XF::phrase('ic_sm_trade_failed'));
			}
		} else {
			// Show sell form
			$viewParams = [
				'symbol' => $symbol,
				'account' => $account,
				'position' => $position,
				'price' => $price
			];
			
			return $this->view('IC\StockMarket:Sell', 'ic_sm_sell', $viewParams);
		}
	}
	
	/**
	 * View stock detail page with chart and metrics
	 */
	public function actionStock(ParameterBag $params)
	{
		$symbol = $this->assertSymbolExists($params->symbol_id);
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		// Get user account if logged in
		$account = null;
		$position = null;
		if (\XF::visitor()->user_id && $season) {
			$accountRepo = $this->repository('IC\StockMarket:Account');
			$account = $accountRepo->getUserAccount(\XF::visitor()->user_id, $season->season_id);
			
			if ($account) {
				$position = $this->finder('IC\StockMarket:Position')
					->where('account_id', $account->account_id)
					->where('symbol_id', $symbol->symbol_id)
					->fetchOne();
			}
		}
		
		// Get detailed stock information from Yahoo Finance
		$yahooFinance = new \IC\StockMarket\Service\YahooFinance($this->app);
		$stockDetails = $yahooFinance->fetchStockDetails($symbol->symbol);
		
		// Pre-calculate position values (if user has position)
		$positionValue = 0;
		$positionGainLoss = 0;
		if ($position && $stockDetails && isset($stockDetails['current']['price'])) {
			$currentPrice = $stockDetails['current']['price'];
			$positionValue = $position->quantity * $currentPrice;
			$positionGainLoss = $position->quantity * ($currentPrice - $position->average_price);
		}
		
		$viewParams = [
			'symbol' => $symbol,
			'account' => $account,
			'position' => $position,
			'stockDetails' => $stockDetails,
			'positionValue' => $positionValue,
			'positionGainLoss' => $positionGainLoss,
		];
		
		return $this->view('IC\StockMarket:Stock', 'ic_sm_stock_detail', $viewParams);
	}
	
	/**
	 * View user portfolio
	 */
	public function actionPortfolio()
	{
		if (!\XF::visitor()->user_id) {
			return $this->noPermission();
		}
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		if (!$season) {
			return $this->error(\XF::phrase('ic_sm_no_active_season'));
		}
		
		// Get user account
		$accountRepo = $this->repository('IC\StockMarket:Account');
		$account = $accountRepo->getOrCreateAccountForUser(\XF::visitor()->user_id, $season->season_id);
		
		// Update portfolio value
		$account->updatePortfolioValue();
		
		// Compute display values using CurrencyHandler
		$currencyHandler = new \IC\StockMarket\Service\CurrencyHandler($this->app);
		$isExternal = $currencyHandler->isUsingExternalCurrency();
		
		if ($isExternal) {
			$cashBalanceDisplay = $currencyHandler->getUserBalance(\XF::visitor(), $season->season_id);
			$portfolioValueDisplay = $account->portfolio_value;
			$totalValueDisplay = $cashBalanceDisplay + $portfolioValueDisplay;
		} else {
			$cashBalanceDisplay = $account->cash_balance;
			$portfolioValueDisplay = $account->portfolio_value;
			$totalValueDisplay = $account->total_value;
		}
		
		// Get positions with calculated values
		$positions = $this->finder('IC\StockMarket:Position')
			->where('account_id', $account->account_id)
			->with('Symbol.Quote')
			->order('Symbol.symbol')
			->fetch();
		
		// Pre-calculate values for each position
		$positionsWithValues = [];
		$totalProfitLoss = 0;
		$topStock = null;
		$maxShares = 0;
		
		foreach ($positions as $position) {
			if ($position->Symbol && $position->Symbol->Quote) {
				$currentPrice = $position->Symbol->Quote->price;
				$gainLoss = $position->quantity * ($currentPrice - $position->average_price);
				$positionsWithValues[] = [
					'position' => $position,
					'currentValue' => $position->quantity * $currentPrice,
					'gainLoss' => $gainLoss,
					'returnPercent' => (($currentPrice - $position->average_price) / $position->average_price) * 100,
				];
				$totalProfitLoss += $gainLoss;
				
				// Track most-held stock
				if ($position->quantity > $maxShares) {
					$maxShares = $position->quantity;
					$topStock = [
						'symbol' => $position->Symbol->symbol,
						'company_name' => $position->Symbol->company_name,
						'shares' => $position->quantity
					];
				}
			}
		}
		
		$viewParams = [
			'account' => $account,
			'positions' => $positions,
			'positionsWithValues' => $positionsWithValues,
			'cashBalanceDisplay' => $cashBalanceDisplay,
			'portfolioValueDisplay' => $portfolioValueDisplay,
			'totalProfitLoss' => $totalProfitLoss,
			'topStock' => $topStock,
		];
		
		return $this->view('IC\StockMarket:Portfolio', 'ic_sm_portfolio', $viewParams);
	}
	
	/**
	 * View trading history
	 */
	public function actionHistory()
	{
		if (!\XF::visitor()->user_id) {
			return $this->noPermission();
		}
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		if (!$season) {
			return $this->error(\XF::phrase('ic_sm_no_active_season'));
		}
		
		// Get user account
		$accountRepo = $this->repository('IC\StockMarket:Account');
		$account = $accountRepo->getUserAccount(\XF::visitor()->user_id, $season->season_id);
		
		if (!$account) {
			return $this->error(\XF::phrase('ic_sm_no_account'));
		}
		
		// Get filter parameters
		$tradeType = $this->filter('trade_type', 'str');
		$marketId = $this->filter('market_id', 'uint');
		$sort = $this->filter('sort', 'str');
		
		// Validate filters
		if (!in_array($tradeType, ['', 'buy', 'sell'])) {
			$tradeType = '';
		}
		if (!in_array($sort, ['date_desc', 'date_asc', 'amount_desc', 'amount_asc'])) {
			$sort = 'date_desc';
		}
		
		// Pagination
		$page = $this->filterPage();
		$perPage = 25;
		
		// Get trades with symbol info
		$tradeFinder = $this->finder('IC\StockMarket:Trade')
			->where('account_id', $account->account_id)
			->with(['Symbol', 'Symbol.Market']);
		
		// Apply trade type filter
		if ($tradeType) {
			$tradeFinder->where('trade_type', $tradeType);
		}
		
		// Apply market filter
		if ($marketId) {
			$tradeFinder->where('Symbol.market_id', $marketId);
		}
		
		// Apply sorting
		switch ($sort) {
			case 'date_asc':
				$tradeFinder->order('trade_date', 'ASC');
				break;
			case 'amount_desc':
				$tradeFinder->order('total_cost', 'DESC');
				break;
			case 'amount_asc':
				$tradeFinder->order('total_cost', 'ASC');
				break;
			case 'date_desc':
			default:
				$tradeFinder->order('trade_date', 'DESC');
				break;
		}
		
		$tradeFinder->limitByPage($page, $perPage);
		
		$trades = $tradeFinder->fetch();
		$total = $tradeFinder->total();
		
		// Build filter params for pagination links
		$filterParams = [];
		if ($tradeType) $filterParams['trade_type'] = $tradeType;
		if ($marketId) $filterParams['market_id'] = $marketId;
		if ($sort && $sort !== 'date_desc') $filterParams['sort'] = $sort;
		
		$this->assertValidPage($page, $perPage, $total, 'stock-market/history', $filterParams);
		$this->assertCanonicalUrl($this->buildPaginatedLink('stock-market/history', null, $page, $filterParams));
		
		// Calculate stats (from ALL trades, not just filtered)
		$allTrades = $this->finder('IC\StockMarket:Trade')
			->where('account_id', $account->account_id)
			->fetch();
		
		$totalBuys = 0;
		$totalSells = 0;
		$totalBuyValue = 0;
		$totalSellValue = 0;
		
		foreach ($allTrades as $trade) {
			if ($trade->trade_type === 'buy') {
				$totalBuys++;
				$totalBuyValue += $trade->total_cost;
			} else {
				$totalSells++;
				$totalSellValue += $trade->total_cost;
			}
		}
		
		// Get all markets for filter dropdown
		$markets = $this->finder('IC\StockMarket:Market')
			->where('is_active', true)
			->order('market_name')
			->fetch();
		
		$viewParams = [
			'trades' => $trades,
			'account' => $account,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
			'totalBuys' => $totalBuys,
			'totalSells' => $totalSells,
			'totalBuyValue' => $totalBuyValue,
			'totalSellValue' => $totalSellValue,
			'markets' => $markets,
			'filters' => [
				'trade_type' => $tradeType,
				'market_id' => $marketId,
				'sort' => $sort
			],
		];
		
		return $this->view('IC\StockMarket:History', 'ic_sm_history', $viewParams);
	}
	
	/**
	 * Leaderboard
	 */
	public function actionLeaderboard()
	{
		if (!\XF::visitor()->hasPermission('icStockMarket', 'view')) {
			return $this->noPermission();
		}
		
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$db = \XF::db();
		
		// Get season filter from query params
		$seasonFilter = $this->filter('season', 'str');
		
		// Get active season for default
		$activeSeason = $seasonRepo->getActiveSeason();
		if (!$activeSeason && !$seasonFilter) {
			return $this->message(\XF::phrase('ic_sm_no_active_season'));
		}
		
		// Get available past seasons for dropdown
		$availableSeasons = $this->finder('IC\StockMarket:Season')
			->where('is_active', 0)
			->order('start_date', 'DESC')
			->limit(10)
			->fetch();
		
		// Build query based on filter
		$leaders = [];
		
		if ($seasonFilter == 'all') {
			// All-time leaderboard - aggregate across all seasons
			$leaders = $db->fetchAll("
				SELECT 
					u.user_id,
					u.username,
					SUM(COALESCE(portfolio_value.total_value, 0)) as total_value,
					SUM(COALESCE(portfolio_value.cost_basis, 0)) as cost_basis,
					SUM(COALESCE(portfolio_value.total_value, 0) - COALESCE(portfolio_value.cost_basis, 0)) as profit_loss,
					CASE 
						WHEN SUM(COALESCE(portfolio_value.cost_basis, 0)) > 0 
						THEN ((SUM(COALESCE(portfolio_value.total_value, 0)) - SUM(COALESCE(portfolio_value.cost_basis, 0))) / SUM(portfolio_value.cost_basis) * 100)
						ELSE 0 
					END as return_percent,
					SUM(COALESCE(a.cash_balance, 0)) as cash_balance,
					MAX(a.account_id) as account_id
				FROM xf_user u
				INNER JOIN xf_ic_sm_account a ON a.user_id = u.user_id
				LEFT JOIN (
					SELECT 
						p.account_id,
						SUM(p.quantity * q.price) as total_value,
						SUM(p.quantity * p.average_price) as cost_basis
					FROM xf_ic_sm_position p
					INNER JOIN xf_ic_sm_quote q ON q.symbol_id = p.symbol_id
					GROUP BY p.account_id
				) portfolio_value ON portfolio_value.account_id = a.account_id
				GROUP BY u.user_id
				ORDER BY profit_loss DESC
			");
		} elseif ($seasonFilter && is_numeric($seasonFilter)) {
			// Specific past season
			$leaders = $db->fetchAll("
				SELECT 
					a.user_id,
					a.account_id,
					a.cash_balance,
					u.username,
					COALESCE(portfolio_value.total_value, 0) as total_value,
					COALESCE(portfolio_value.cost_basis, 0) as cost_basis,
					(COALESCE(portfolio_value.total_value, 0) - COALESCE(portfolio_value.cost_basis, 0)) as profit_loss,
					CASE 
						WHEN COALESCE(portfolio_value.cost_basis, 0) > 0 
						THEN ((COALESCE(portfolio_value.total_value, 0) - COALESCE(portfolio_value.cost_basis, 0)) / portfolio_value.cost_basis * 100)
						ELSE 0 
					END as return_percent
				FROM xf_ic_sm_account a
				INNER JOIN xf_user u ON u.user_id = a.user_id
				LEFT JOIN (
					SELECT 
						p.account_id,
						SUM(p.quantity * q.price) as total_value,
						SUM(p.quantity * p.average_price) as cost_basis
					FROM xf_ic_sm_position p
					INNER JOIN xf_ic_sm_quote q ON q.symbol_id = p.symbol_id
					GROUP BY p.account_id
				) portfolio_value ON portfolio_value.account_id = a.account_id
				WHERE a.season_id = ?
				ORDER BY total_value DESC
			", [(int)$seasonFilter]);
		} else {
			// Current season (default)
			$leaders = $db->fetchAll("
				SELECT 
					a.user_id,
					a.account_id,
					a.cash_balance,
					u.username,
					COALESCE(portfolio_value.total_value, 0) as total_value,
					COALESCE(portfolio_value.cost_basis, 0) as cost_basis,
					(COALESCE(portfolio_value.total_value, 0) - COALESCE(portfolio_value.cost_basis, 0)) as profit_loss,
					CASE 
						WHEN COALESCE(portfolio_value.cost_basis, 0) > 0 
						THEN ((COALESCE(portfolio_value.total_value, 0) - COALESCE(portfolio_value.cost_basis, 0)) / portfolio_value.cost_basis * 100)
						ELSE 0 
					END as return_percent
				FROM xf_ic_sm_account a
				INNER JOIN xf_user u ON u.user_id = a.user_id
				LEFT JOIN (
					SELECT 
						p.account_id,
						SUM(p.quantity * q.price) as total_value,
						SUM(p.quantity * p.average_price) as cost_basis
					FROM xf_ic_sm_position p
					INNER JOIN xf_ic_sm_quote q ON q.symbol_id = p.symbol_id
					GROUP BY p.account_id
				) portfolio_value ON portfolio_value.account_id = a.account_id
				WHERE a.season_id = ?
				ORDER BY total_value DESC
			", [$activeSeason->season_id]);
		}
		
		// Get most popular stock for each user in leaderboard
		$mostPopularStocks = [];
		if (!empty($leaders)) {
			$userIds = array_column($leaders, 'user_id');
			$placeholders = implode(',', array_fill(0, count($userIds), '?'));
			
			$stockData = $db->fetchAll("
				SELECT 
					a.user_id,
					s.symbol,
					s.company_name,
					SUM(p.quantity) as total_shares
				FROM xf_ic_sm_position p
				INNER JOIN xf_ic_sm_account a ON a.account_id = p.account_id
				INNER JOIN xf_ic_sm_symbol s ON s.symbol_id = p.symbol_id
				WHERE a.user_id IN ($placeholders)
				GROUP BY a.user_id, s.symbol_id
				ORDER BY a.user_id, total_shares DESC
			", $userIds);
			
			// Group by user_id and get top stock for each
			$currentUserId = null;
			foreach ($stockData as $stock) {
				if ($stock['user_id'] !== $currentUserId) {
					$mostPopularStocks[$stock['user_id']] = [
						'symbol' => $stock['symbol'],
						'company_name' => $stock['company_name'],
						'shares' => (int)$stock['total_shares']
					];
					$currentUserId = $stock['user_id'];
				}
			}
		}
		
		// Format leaderboard with ranks
		$leaderboardWithValues = [];
		$rank = 1;
		
		foreach ($leaders as $leader) {
			// Transform for template compatibility
			$item = [
				'entry' => [
					'rank' => $rank++,
					'user_id' => $leader['user_id'],
					'User' => [
						'user_id' => $leader['user_id'],
						'username' => $leader['username']
					]
				],
				'totalValue' => (float)$leader['total_value'],
				'tradingProfit' => (float)$leader['profit_loss'],
				'returnPercent' => (float)$leader['return_percent'],
				'topStock' => $mostPopularStocks[$leader['user_id']] ?? null
			];
			$leaderboardWithValues[] = $item;
		}
		
		// Get user's rank if logged in
		$userRank = null;
		if (\XF::visitor()->user_id) {
			foreach ($leaderboardWithValues as $item) {
				if ($item['entry']['user_id'] == \XF::visitor()->user_id) {
					$userRank = $item;
					break;
				}
			}
		}
		
		$viewParams = [
			'season' => $activeSeason,
			'seasonFilter' => $seasonFilter,
			'availableSeasons' => $availableSeasons,
			'leaderboardWithValues' => $leaderboardWithValues,
			'userRank' => $userRank,
			'totalAccounts' => count($leaderboardWithValues)
		];
		
		return $this->view('IC\StockMarket:Leaderboard', 'ic_sm_leaderboard', $viewParams);
	}
	
	/**
	 * Show achievements page
	 */
	public function actionAchievements()
	{
		// Check permission
		if (!\XF::visitor()->hasPermission('icStockMarket', 'view')) {
			return $this->noPermission();
		}
		
		// Get active season
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$season = $seasonRepo->getActiveSeason();
		
		if (!$season) {
			return $this->message(\XF::phrase('ic_sm_no_active_season'));
		}
		
		$visitor = \XF::visitor();
		
		// Get user's account for current season
		$accountRepo = $this->repository('IC\StockMarket:Account');
		$account = $accountRepo->getUserAccount($visitor->user_id, $season->season_id);
		
		// Get achievement progress
		$achievementService = $this->service('IC\StockMarket:Achievement');
		$progress = [];
		$earnedCount = 0;
		$totalXp = 0;
		
		if ($account) {
			$progress = $achievementService->getAchievementProgress($account);
			
			// Calculate stats
			foreach ($progress as $item) {
				if ($item['earned']) {
					$earnedCount++;
					$totalXp += $item['achievement']->xp_points;
				}
			}
		} else {
			// No account yet - show all achievements as locked
			$achievements = $this->finder('IC\StockMarket:Achievement')
				->where('is_active', true)
				->order('display_order')
				->fetch();
			
			foreach ($achievements as $achievement) {
				$progress[$achievement->achievement_id] = [
					'achievement' => $achievement,
					'earned' => false,
					'earned_date' => null
				];
			}
		}
		
		$totalCount = count($progress);
		$progressPercent = $totalCount > 0 ? round(($earnedCount / $totalCount) * 100) : 0;
		
		// Group achievements by category
		$byCategory = [];
		foreach ($progress as $item) {
			$category = $item['achievement']->achievement_category;
			if (!isset($byCategory[$category])) {
				$byCategory[$category] = [];
			}
			$byCategory[$category][] = $item;
		}
		
		$viewParams = [
			'season' => $season,
			'account' => $account,
			'progress' => $progress,
			'byCategory' => $byCategory,
			'earnedCount' => $earnedCount,
			'totalCount' => $totalCount,
			'progressPercent' => $progressPercent,
			'totalXp' => $totalXp
		];
		
		return $this->view('IC\StockMarket:Achievements', 'ic_sm_achievements', $viewParams);
	}

	/**
	 * Assert symbol exists and is viewable
	 */
	protected function assertSymbolExists($id)
	{
		$symbol = $this->em()->find('IC\StockMarket:Symbol', $id, 'Quote');
		if (!$symbol || !$symbol->is_active) {
			throw $this->exception($this->notFound(\XF::phrase('ic_sm_symbol_not_found')));
		}
		return $symbol;
	}
}
