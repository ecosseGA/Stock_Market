<?php

namespace IC\StockMarket\Repository;

use XF\Mvc\Entity\Repository;

class Leaderboard extends Repository
{
	/**
	 * Get leaderboard for season
	 */
	public function getLeaderboardForSeason($seasonId, $limit = 100)
	{
		return $this->finder('IC\StockMarket:Leaderboard')
			->with('User')
			->with('Account')
			->where('season_id', $seasonId)
			->order('rank')
			->limit($limit)
			->fetch();
	}
	
	/**
	 * Rebuild leaderboard for season
	 */
	public function rebuildLeaderboard($seasonId)
	{
		$db = $this->db();
		
		// Get all accounts for this season, ordered by total value
		$accounts = $this->finder('IC\StockMarket:Account')
			->where('season_id', $seasonId)
			->order('total_value', 'DESC')
			->fetch();
		
		// Clear existing leaderboard
		$db->delete('xf_ic_sm_leaderboard', 'season_id = ?', $seasonId);
		
		// Rebuild with ranks
		$rank = 1;
		foreach ($accounts as $account) {
			/** @var \IC\StockMarket\Entity\Season $season */
			$season = $account->Season;
			$startingBalance = $season ? $season->starting_balance : 10000;
			
			$returnPercent = 0;
			if ($startingBalance > 0) {
				$returnPercent = (($account->total_value - $startingBalance) / $startingBalance) * 100;
			}
			
			/** @var \IC\StockMarket\Entity\Leaderboard $entry */
			$entry = $this->em->create('IC\StockMarket:Leaderboard');
			$entry->season_id = $seasonId;
			$entry->user_id = $account->user_id;
			$entry->account_id = $account->account_id;
			$entry->rank = $rank;
			$entry->total_value = $account->total_value;
			$entry->return_percent = $returnPercent;
			$entry->last_updated = \XF::$time;
			$entry->save();
			
			$rank++;
		}
		
		return $rank - 1; // Return number of entries
	}
	
	/**
	 * Get user's rank/entry in current season
	 */
	public function getUserRank($userId, $seasonId)
	{
		return $this->finder('IC\StockMarket:Leaderboard')
			->where('user_id', $userId)
			->where('season_id', $seasonId)
			->with('User')
			->fetchOne();
	}
}
