<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $market_id
 * @property string $market_code
 * @property string $market_name
 * @property string $country_code
 * @property string $timezone
 * @property string $market_open_time
 * @property string $market_close_time
 * @property string|null $pre_market_open
 * @property string|null $after_hours_close
 * @property string $trading_days
 * @property bool $is_active
 * @property int $display_order
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\IC\StockMarket\Entity\Symbol[] $Symbols
 */
class Market extends Entity
{
	const STATUS_CLOSED = 'closed';
	const STATUS_PRE_MARKET = 'pre_market';
	const STATUS_OPEN = 'open';
	const STATUS_AFTER_HOURS = 'after_hours';
	
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_market';
		$structure->shortName = 'IC\StockMarket:Market';
		$structure->primaryKey = 'market_id';
		
		$structure->columns = [
			'market_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'market_code' => ['type' => self::STR, 'maxLength' => 10, 'required' => true,
				'unique' => 'market_code_must_be_unique'
			],
			'market_name' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'country_code' => ['type' => self::STR, 'maxLength' => 5, 'default' => ''],
			'timezone' => ['type' => self::STR, 'maxLength' => 50, 'default' => 'America/New_York'],
			'market_open_time' => ['type' => self::STR, 'maxLength' => 10, 'default' => '09:30'],
			'market_close_time' => ['type' => self::STR, 'maxLength' => 10, 'default' => '16:00'],
			'pre_market_open' => ['type' => self::STR, 'maxLength' => 10, 'nullable' => true, 'default' => null],
			'after_hours_close' => ['type' => self::STR, 'maxLength' => 10, 'nullable' => true, 'default' => null],
			'trading_days' => ['type' => self::STR, 'maxLength' => 20, 'default' => '1,2,3,4,5'],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'display_order' => ['type' => self::UINT, 'default' => 0]
		];
		
		$structure->relations = [
			'Symbols' => [
				'entity' => 'IC\StockMarket:Symbol',
				'type' => self::TO_MANY,
				'conditions' => 'market_id',
				'order' => 'symbol'
			]
		];
		
		return $structure;
	}
	
	/**
	 * Get current date/time in market's timezone
	 */
	public function getMarketDateTime()
	{
		try {
			$tz = new \DateTimeZone($this->timezone);
			return new \DateTime('now', $tz);
		} catch (\Exception $e) {
			// Fallback to system timezone
			return new \DateTime('now');
		}
	}
	
	/**
	 * Check if market is currently trading (regular hours only)
	 */
	public function isMarketOpen()
	{
		if (!$this->is_active) {
			return false;
		}
		
		return $this->getMarketStatus() === self::STATUS_OPEN;
	}
	
	/**
	 * Get detailed market status
	 * @return string One of: closed, pre_market, open, after_hours
	 */
	public function getMarketStatus()
	{
		if (!$this->is_active) {
			return self::STATUS_CLOSED;
		}
		
		$now = $this->getMarketDateTime();
		$dayOfWeek = (int)$now->format('N'); // 1=Monday, 7=Sunday
		
		// Check if today is a trading day
		$tradingDays = array_map('intval', explode(',', $this->trading_days));
		if (!in_array($dayOfWeek, $tradingDays)) {
			return self::STATUS_CLOSED;
		}
		
		$currentTime = $now->format('H:i');
		
		// Check pre-market
		if ($this->pre_market_open && $currentTime >= $this->pre_market_open && $currentTime < $this->market_open_time) {
			return self::STATUS_PRE_MARKET;
		}
		
		// Check regular hours
		if ($currentTime >= $this->market_open_time && $currentTime < $this->market_close_time) {
			return self::STATUS_OPEN;
		}
		
		// Check after-hours
		if ($this->after_hours_close && $currentTime >= $this->market_close_time && $currentTime < $this->after_hours_close) {
			return self::STATUS_AFTER_HOURS;
		}
		
		return self::STATUS_CLOSED;
	}
	
	/**
	 * Check if market is in extended hours (pre-market or after-hours)
	 */
	public function isExtendedHours()
	{
		$status = $this->getMarketStatus();
		return in_array($status, [self::STATUS_PRE_MARKET, self::STATUS_AFTER_HOURS]);
	}
	
	/**
	 * Get time until market opens (in seconds)
	 * Returns null if market is already open
	 */
	public function getTimeUntilOpen()
	{
		if ($this->isMarketOpen()) {
			return null;
		}
		
		$now = $this->getMarketDateTime();
		$openTime = clone $now;
		$tradingDays = array_map('intval', explode(',', $this->trading_days));
		
		// Set open time for today
		list($hour, $minute) = explode(':', $this->market_open_time);
		$openTime->setTime((int)$hour, (int)$minute, 0);
		
		// Check if we need to move to next day
		$currentDayOfWeek = (int)$now->format('N');
		$needNextDay = false;
		
		// Move to next day if:
		// 1. Today is not a trading day, OR
		// 2. Opening time has already passed today
		if (!in_array($currentDayOfWeek, $tradingDays) || $openTime <= $now) {
			$needNextDay = true;
		}
		
		if ($needNextDay) {
			$openTime->modify('+1 day');
			
			// Find next trading day
			$maxDays = 7;
			while ($maxDays-- > 0) {
				$dayOfWeek = (int)$openTime->format('N');
				
				if (in_array($dayOfWeek, $tradingDays)) {
					break;
				}
				
				$openTime->modify('+1 day');
			}
		}
		
		return $openTime->getTimestamp() - $now->getTimestamp();
	}
	
	/**
	 * Get time until market closes (in seconds)
	 * Returns null if market is not open
	 */
	public function getTimeUntilClose()
	{
		if (!$this->isMarketOpen()) {
			return null;
		}
		
		$now = $this->getMarketDateTime();
		$closeTime = clone $now;
		
		list($hour, $minute) = explode(':', $this->market_close_time);
		$closeTime->setTime((int)$hour, (int)$minute, 0);
		
		return $closeTime->getTimestamp() - $now->getTimestamp();
	}
	
	/**
	 * Get human-readable market status phrase key
	 */
	public function getStatusPhraseKey()
	{
		$status = $this->getMarketStatus();
		
		switch ($status) {
			case self::STATUS_OPEN:
				return 'ic_sm_market_open';
			case self::STATUS_PRE_MARKET:
				return 'ic_sm_market_pre_market';
			case self::STATUS_AFTER_HOURS:
				return 'ic_sm_market_after_hours';
			case self::STATUS_CLOSED:
			default:
				return 'ic_sm_market_closed';
		}
	}
	
	protected function formatDuration($seconds)
	{
		if ($seconds === null || $seconds < 0)
		{
			return null;
		}
		
		$hours = floor($seconds / 3600);
		$minutes = floor(($seconds % 3600) / 60);
		
		if ($hours > 0)
		{
			return $hours . 'h ' . $minutes . 'm';
		}
		else
		{
			return $minutes . 'm';
		}
	}
	
	public function getTimeUntilOpenFormatted()
	{
		return $this->formatDuration($this->getTimeUntilOpen());
	}
	
	public function getTimeUntilCloseFormatted()
	{
		return $this->formatDuration($this->getTimeUntilClose());
	}
}
