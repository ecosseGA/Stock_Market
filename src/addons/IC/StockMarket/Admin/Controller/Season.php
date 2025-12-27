<?php

namespace IC\StockMarket\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Season extends AbstractController
{
	/**
	 * List all seasons
	 */
	public function actionIndex()
	{
		$seasonRepo = $this->repository('IC\StockMarket:Season');
		$seasons = $seasonRepo->findSeasons()->fetch();
		
		$viewParams = [
			'seasons' => $seasons
		];
		
		return $this->view('IC\StockMarket:Season\List', 'ic_sm_season_list', $viewParams);
	}
	
	/**
	 * Add new season
	 */
	public function actionAdd()
	{
		$season = $this->em()->create('IC\StockMarket:Season');
		$season->start_date = \XF::$time;
		$season->starting_balance = 10000;
		
		return $this->seasonAddEdit($season);
	}
	
	/**
	 * Edit season
	 */
	public function actionEdit(ParameterBag $params)
	{
		$season = $this->assertSeasonExists($params->season_id);
		return $this->seasonAddEdit($season);
	}
	
	/**
	 * Add/Edit form handler
	 */
	protected function seasonAddEdit(\IC\StockMarket\Entity\Season $season)
	{
		$viewParams = [
			'season' => $season
		];
		
		return $this->view('IC\StockMarket:Season\Edit', 'ic_sm_season_edit', $viewParams);
	}
	
	/**
	 * Save season
	 */
	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();
		
		if ($params->season_id) {
			$season = $this->assertSeasonExists($params->season_id);
		} else {
			$season = $this->em()->create('IC\StockMarket:Season');
		}
		
		$this->seasonSaveProcess($season)->run();
		
		return $this->redirect($this->buildLink('stock-market/seasons'));
	}
	
	/**
	 * Save process
	 */
	protected function seasonSaveProcess(\IC\StockMarket\Entity\Season $season)
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'season_name' => 'str',
			'start_date' => 'datetime',
			'end_date' => 'datetime',
			'is_active' => 'bool',
			'starting_balance' => 'float'
		]);
		
		$form->basicEntitySave($season, $input);
		
		return $form;
	}
	
	/**
	 * End season
	 */
	public function actionEnd(ParameterBag $params)
	{
		$season = $this->assertSeasonExists($params->season_id);
		
		if ($this->isPost()) {
			$season->end_date = \XF::$time;
			$season->is_active = false;
			$season->save();
			
			// Rebuild leaderboard for final standings
			$leaderboardRepo = $this->repository('IC\StockMarket:Leaderboard');
			$leaderboardRepo->rebuildLeaderboard($season->season_id);
			
			return $this->redirect($this->buildLink('stock-market/seasons'), \XF::phrase('season_ended_successfully'));
		} else {
			$viewParams = [
				'season' => $season
			];
			return $this->view('IC\StockMarket:Season\End', 'ic_sm_season_end', $viewParams);
		}
	}
	
	/**
	 * Delete season
	 */
	public function actionDelete(ParameterBag $params)
	{
		$season = $this->assertSeasonExists($params->season_id);
		
		if ($this->isPost()) {
			$season->delete();
			return $this->redirect($this->buildLink('stock-market/seasons'));
		} else {
			$viewParams = [
				'season' => $season
			];
			return $this->view('IC\StockMarket:Season\Delete', 'ic_sm_season_delete', $viewParams);
		}
	}
	
	/**
	 * Assert season exists
	 */
	protected function assertSeasonExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('IC\StockMarket:Season', $id, $with, $phraseKey);
	}
}
