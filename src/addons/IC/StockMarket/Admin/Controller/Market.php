<?php

namespace IC\StockMarket\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Market extends AbstractController
{
	/**
	 * List all markets
	 */
	public function actionIndex()
	{
		$marketRepo = $this->repository('IC\StockMarket:Market');
		
		$markets = $marketRepo->finder('IC\StockMarket:Market')
			->order('display_order')
			->fetch();
		
		$viewParams = [
			'markets' => $markets
		];
		
		return $this->view('IC\StockMarket:Market\List', 'ic_sm_market_list', $viewParams);
	}
	
	/**
	 * Add new market
	 */
	public function actionAdd()
	{
		$market = $this->em()->create('IC\StockMarket:Market');
		return $this->marketAddEdit($market);
	}
	
	/**
	 * Edit market
	 */
	public function actionEdit(ParameterBag $params)
	{
		$market = $this->assertMarketExists($params->market_id);
		return $this->marketAddEdit($market);
	}
	
	/**
	 * Add/Edit form handler
	 */
	protected function marketAddEdit(\IC\StockMarket\Entity\Market $market)
	{
		$viewParams = [
			'market' => $market
		];
		
		return $this->view('IC\StockMarket:Market\Edit', 'ic_sm_market_edit', $viewParams);
	}
	
	/**
	 * Save market
	 */
	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();
		
		if ($params->market_id) {
			$market = $this->assertMarketExists($params->market_id);
		} else {
			$market = $this->em()->create('IC\StockMarket:Market');
		}
		
		$this->marketSaveProcess($market)->run();
		
		return $this->redirect($this->buildLink('stock-market/markets'));
	}
	
	/**
	 * Save process
	 */
	protected function marketSaveProcess(\IC\StockMarket\Entity\Market $market)
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'market_code' => 'str',
			'market_name' => 'str',
			'country_code' => 'str',
			'timezone' => 'str',
			'market_open_time' => 'str',
			'market_close_time' => 'str',
			'pre_market_open' => 'str',
			'after_hours_close' => 'str',
			'trading_days' => 'str',
			'is_active' => 'bool',
			'display_order' => 'uint'
		]);
		
		// Handle nullable fields
		if (empty($input['pre_market_open'])) {
			$input['pre_market_open'] = null;
		}
		if (empty($input['after_hours_close'])) {
			$input['after_hours_close'] = null;
		}
		
		$form->basicEntitySave($market, $input);
		
		return $form;
	}
	
	/**
	 * Delete market
	 */
	public function actionDelete(ParameterBag $params)
	{
		$market = $this->assertMarketExists($params->market_id);
		
		if ($this->isPost()) {
			$market->delete();
			return $this->redirect($this->buildLink('stock-market/markets'));
		} else {
			$viewParams = [
				'market' => $market
			];
			return $this->view('IC\StockMarket:Market\Delete', 'ic_sm_market_delete', $viewParams);
		}
	}
	
	/**
	 * Assert market exists
	 */
	protected function assertMarketExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('IC\StockMarket:Market', $id, $with, $phraseKey);
	}
}
