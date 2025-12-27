<?php

namespace IC\StockMarket\Cron;

/**
 * Cron job to update stock quotes from Yahoo Finance
 */
class UpdateQuotes
{
	/**
	 * Update all stock quotes
	 */
	public static function runUpdate()
	{
		$app = \XF::app();
		
		// Get the quote updater service
		$quoteUpdater = new \IC\StockMarket\Service\QuoteUpdater($app);
		
		// Update all quotes
		$stats = $quoteUpdater->updateAllQuotes();
		
		// Only log if there were failures
		if ($stats['failed'] > 0) {
			$message = sprintf(
				"Stock Market Quote Update: Total=%d, Updated=%d, Failed=%d",
				$stats['total'],
				$stats['updated'],
				$stats['failed']
			);
			
			// Log as error if more than 10% failed
			if (($stats['failed'] / $stats['total']) > 0.1) {
				\XF::logError($message, false);
			}
		}
		
		// Silent success - no logging needed
	}
}
