<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class Season extends Repository
{
	/**
	 * Get active season
	 */
	public function getActiveSeason()
	{
		return $this->finder('IC\StockMarket:Season')
			->where('is_active', 1)
			->order('season_id', 'DESC')
			->fetchOne();
	}
	
	/**
	 * Get or create default season
	 */
	public function getOrCreateDefaultSeason()
	{
		$season = $this->getActiveSeason();
		
		if (!$season) {
			$season = $this->em->create('IC\StockMarket:Season');
			$season->season_name = 'Season 1';
			$season->start_date = \XF::$time;
			$season->is_active = true;
			$season->starting_balance = 10000;
			$season->save();
		}
		
		return $season;
	}
	
	/**
	 * Find all seasons
	 */
	public function findSeasons()
	{
		return $this->finder('IC\StockMarket:Season')
			->order('season_id', 'DESC');
	}
}
