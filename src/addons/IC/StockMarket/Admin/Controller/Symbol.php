<?php

namespace IC\StockMarket\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Symbol extends AbstractController
{
	/**
	 * List all symbols
	 */
	public function actionIndex()
	{
		$page = $this->filterPage();
		$perPage = 50;
		
		$symbolFinder = $this->finder('IC\StockMarket:Symbol')
			->with('Market')
			->with('Quote')
			->order(['Market.display_order', 'symbol']);
		
		$total = $symbolFinder->total();
		$symbols = $symbolFinder->limitByPage($page, $perPage)->fetch();
		
		// Calculate stats
		$activeSymbols = $this->finder('IC\StockMarket:Symbol')
			->where('is_active', true)
			->total();
		
		$inactiveSymbols = $total - $activeSymbols;
		
		// Get last updated time from most recent quote
		$lastUpdatedQuote = $this->finder('IC\StockMarket:Quote')
			->order('last_updated', 'DESC')
			->fetchOne();
		
		$lastUpdated = $lastUpdatedQuote ? $lastUpdatedQuote->last_updated : 0;
		
		$viewParams = [
			'symbols' => $symbols,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
			'totalSymbols' => $total,
			'activeSymbols' => $activeSymbols,
			'inactiveSymbols' => $inactiveSymbols,
			'lastUpdated' => $lastUpdated,
		];
		
		return $this->view('IC\StockMarket:Symbol\List', 'ic_sm_symbol_list', $viewParams);
	}
	
	/**
	 * Add new symbol
	 */
	public function actionAdd()
	{
		$symbol = $this->em()->create('IC\StockMarket:Symbol');
		return $this->symbolAddEdit($symbol);
	}
	
	/**
	 * Edit symbol
	 */
	public function actionEdit(ParameterBag $params)
	{
		$symbol = $this->assertSymbolExists($params->symbol_id);
		return $this->symbolAddEdit($symbol);
	}
	
	/**
	 * Add/Edit form handler
	 */
	protected function symbolAddEdit(\IC\StockMarket\Entity\Symbol $symbol)
	{
		$marketRepo = $this->repository('IC\StockMarket:Market');
		$markets = $marketRepo->findAllMarkets()->fetch();  // Show ALL markets in admin
		
		// Prepare market display names with status
		$marketChoices = [];
		foreach ($markets as $market)
		{
			$displayName = $market->market_name;
			if (!$market->is_active)
			{
				$displayName .= ' (Inactive)';
			}
			$marketChoices[$market->market_id] = $displayName;
		}
		
		$viewParams = [
			'symbol' => $symbol,
			'markets' => $markets,
			'marketChoices' => $marketChoices
		];
		
		return $this->view('IC\StockMarket:Symbol\Edit', 'ic_sm_symbol_edit', $viewParams);
	}
	
	/**
	 * Save symbol
	 */
	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();
		
		if ($params->symbol_id) {
			$symbol = $this->assertSymbolExists($params->symbol_id);
		} else {
			$symbol = $this->em()->create('IC\StockMarket:Symbol');
		}
		
		$this->symbolSaveProcess($symbol)->run();
		
		return $this->redirect($this->buildLink('stock-market/symbols'));
	}
	
	/**
	 * Save process
	 */
	protected function symbolSaveProcess(\IC\StockMarket\Entity\Symbol $symbol)
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'market_id' => 'uint',
			'symbol' => 'str',
			'company_name' => 'str',
			'is_active' => 'bool',
			'is_featured' => 'bool',
			'display_order' => 'uint'
		]);
		
		$form->basicEntitySave($symbol, $input);
		
		return $form;
	}
	
	/**
	 * Delete symbol
	 */
	public function actionDelete(ParameterBag $params)
	{
		$symbol = $this->assertSymbolExists($params->symbol_id);
		
		if ($this->isPost()) {
			$symbol->delete();
			return $this->redirect($this->buildLink('stock-market/symbols'));
		} else {
			$viewParams = [
				'symbol' => $symbol
			];
			return $this->view('IC\StockMarket:Symbol\Delete', 'ic_sm_symbol_delete', $viewParams);
		}
	}
	
	/**
	 * Import symbols form
	 */
	public function actionImport()
	{
		$marketRepo = $this->repository('IC\StockMarket:Market');
		$markets = $marketRepo->findActiveMarkets()->fetch();
		
		$viewParams = [
			'markets' => $markets
		];
		
		return $this->view('IC\StockMarket:Symbol\Import', 'ic_sm_symbol_import', $viewParams);
	}
	
	/**
	 * Process bulk import
	 */
	public function actionImportProcess()
	{
		$this->assertPostOnly();
		
		$marketId = $this->filter('market_id', 'uint');
		$symbolsData = $this->filter('symbols_data', 'str');
		
		if (!$marketId) {
			return $this->error(\XF::phrase('please_select_valid_market'));
		}
		
		$market = $this->assertRecordExists('IC\StockMarket:Market', $marketId);
		
		// Check if file was uploaded
		$upload = $this->request->getFile('csv_file');
		if ($upload && $upload->isValid()) {
			// Read file contents
			$symbolsData = file_get_contents($upload->getTempFile());
		}
		
		if (empty($symbolsData)) {
			return $this->error(\XF::phrase('please_provide_symbols_data_or_upload_file'));
		}
		
		// Parse CSV: symbol,company_name
		$lines = explode("\n", $symbolsData);
		$imported = 0;
		$errors = [];
		
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line)) continue;
			
			// Skip header row if it exists
			if (stripos($line, 'symbol') === 0 || stripos($line, 'ticker') === 0) {
				continue;
			}
			
			$parts = str_getcsv($line);
			if (count($parts) < 2) continue;
			
			$ticker = trim($parts[0]);
			$companyName = trim($parts[1]);
			
			if (empty($ticker) || empty($companyName)) continue;
			
			// Check if already exists
			$existing = $this->finder('IC\StockMarket:Symbol')
				->where('market_id', $marketId)
				->where('symbol', $ticker)
				->fetchOne();
			
			if ($existing) {
				$errors[] = "Symbol {$ticker} already exists";
				continue;
			}
			
			// Create symbol
			$symbol = $this->em()->create('IC\StockMarket:Symbol');
			$symbol->market_id = $marketId;
			$symbol->symbol = $ticker;
			$symbol->company_name = $companyName;
			$symbol->is_active = true;
			$symbol->save();
			
			$imported++;
		}
		
		return $this->redirect($this->buildLink('stock-market/symbols'), \XF::phrase('imported_x_symbols', ['count' => $imported]));
	}
	
	/**
	 * Update quotes for all symbols (manual trigger)
	 */
	public function actionUpdateQuotes()
	{
		// Allow both GET and POST for manual trigger
		if ($this->isPost() || $this->request->exists('update-quotes')) {
			\XF::logError("Update Quotes: Starting manual quote update", false);
			
			$quoteUpdater = new \IC\StockMarket\Service\QuoteUpdater($this->app);
			$stats = $quoteUpdater->updateAllQuotes();
			
			\XF::logError("Update Quotes: Completed - Total: {$stats['total']}, Updated: {$stats['updated']}, Failed: {$stats['failed']}", false);
			
			return $this->message(\XF::phrase('ic_sm_quotes_updated', [
				'total' => $stats['total'],
				'updated' => $stats['updated'],
				'failed' => $stats['failed']
			]));
		}
		
		return $this->redirect($this->buildLink('stock-market/symbols'));
	}
	
	/**
	 * Assert symbol exists
	 */
	protected function assertSymbolExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('IC\StockMarket:Symbol', $id, $with, $phraseKey);
	}
}
