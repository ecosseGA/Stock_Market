<?php

namespace IC\StockMarket\Service;

use XF\App;

/**
 * Service for calculating experience points and ranks
 */
class ExperienceCalculator
{
	protected $app;
	
	/**
	 * XP values by difficulty tier
	 */
	const XP_VALUES = [
		'easy' => 10,
		'medium' => 25,
		'hard' => 50,
		'very_hard' => 100,
		'epic' => 250,
		'legendary' => 500
	];
	
	/**
	 * Rank tiers based on XP thresholds
	 */
	const RANK_TIERS = [
		0 => 'ic_sm_rank_novice',       // Novice Trader
		100 => 'ic_sm_rank_apprentice', // Apprentice Trader (1.00 XP)
		500 => 'ic_sm_rank_trader',     // Trader (5.00 XP)
		1000 => 'ic_sm_rank_senior',    // Senior Trader (10.00 XP)
		2500 => 'ic_sm_rank_expert',    // Expert Trader (25.00 XP)
		5000 => 'ic_sm_rank_master',    // Master Trader (50.00 XP)
		10000 => 'ic_sm_rank_legend'    // Trading Legend (100.00 XP)
	];
	
	public function __construct(App $app)
	{
		$this->app = $app;
	}
	
	/**
	 * Calculate season rank from season XP
	 */
	public function calculateSeasonRank(int $seasonXp): string
	{
		return $this->getRankFromXp($seasonXp);
	}
	
	/**
	 * Calculate career rank from lifetime XP
	 */
	public function calculateCareerRank(int $lifetimeXp): string
	{
		return $this->getRankFromXp($lifetimeXp);
	}
	
	/**
	 * Get rank name from XP amount
	 */
	protected function getRankFromXp(int $xp): string
	{
		$rank = 'ic_sm_rank_novice';
		
		foreach (self::RANK_TIERS as $threshold => $rankKey) {
			if ($xp >= $threshold) {
				$rank = $rankKey;
			} else {
				break;
			}
		}
		
		return $rank;
	}
	
	/**
	 * Get next rank information
	 * Returns ['rank' => string, 'xp_needed' => int, 'threshold' => int]
	 */
	public function getNextRank(int $currentXp): ?array
	{
		$currentRank = null;
		$nextRank = null;
		$nextThreshold = null;
		
		foreach (self::RANK_TIERS as $threshold => $rankKey) {
			if ($currentXp >= $threshold) {
				$currentRank = $rankKey;
			} else {
				$nextRank = $rankKey;
				$nextThreshold = $threshold;
				break;
			}
		}
		
		// Already at max rank
		if (!$nextRank) {
			return null;
		}
		
		return [
			'rank' => $nextRank,
			'xp_needed' => $nextThreshold - $currentXp,
			'threshold' => $nextThreshold
		];
	}
	
	/**
	 * Get progress to next rank as percentage (0-100)
	 */
	public function getProgressToNextRank(int $currentXp): float
	{
		$currentThreshold = 0;
		$nextThreshold = null;
		
		foreach (self::RANK_TIERS as $threshold => $rankKey) {
			if ($currentXp >= $threshold) {
				$currentThreshold = $threshold;
			} else {
				$nextThreshold = $threshold;
				break;
			}
		}
		
		// At max rank
		if (!$nextThreshold) {
			return 100.0;
		}
		
		$range = $nextThreshold - $currentThreshold;
		$progress = $currentXp - $currentThreshold;
		
		return ($progress / $range) * 100;
	}
	
	/**
	 * Format XP for display (1486 -> "14.86")
	 */
	public function formatXpDisplay(int $xp): string
	{
		return number_format($xp / 100, 2);
	}
	
	/**
	 * Get XP value for a difficulty tier
	 */
	public function getXpForDifficulty(string $tier): int
	{
		return self::XP_VALUES[$tier] ?? 10;
	}
	
	/**
	 * Get all rank tiers with thresholds
	 */
	public function getAllRanks(): array
	{
		$ranks = [];
		
		foreach (self::RANK_TIERS as $threshold => $rankKey) {
			$ranks[] = [
				'threshold' => $threshold,
				'key' => $rankKey,
				'name' => \XF::phrase($rankKey)->render(),
				'xp_display' => $this->formatXpDisplay($threshold)
			];
		}
		
		return $ranks;
	}
	
	/**
	 * Get rank info for specific XP amount
	 */
	public function getRankInfo(int $xp): array
	{
		$rankKey = $this->getRankFromXp($xp);
		$nextRank = $this->getNextRank($xp);
		$progress = $this->getProgressToNextRank($xp);
		
		return [
			'current_rank' => $rankKey,
			'current_rank_name' => \XF::phrase($rankKey)->render(),
			'xp' => $xp,
			'xp_display' => $this->formatXpDisplay($xp),
			'next_rank' => $nextRank,
			'progress_percent' => round($progress, 1)
		];
	}
	
	/**
	 * Check if user ranked up
	 */
	public function didRankUp(int $oldXp, int $newXp): ?string
	{
		$oldRank = $this->getRankFromXp($oldXp);
		$newRank = $this->getRankFromXp($newXp);
		
		if ($oldRank !== $newRank) {
			return $newRank;
		}
		
		return null;
	}
}
