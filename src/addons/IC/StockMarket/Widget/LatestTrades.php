<?php

namespace IC\StockMarket\Widget;

use XF\Widget\AbstractWidget;

class LatestTrades extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 10
	];

	public function render()
	{
		$visitor = \XF::visitor();
		
		// Check if visitor can view stock market
		if (!$visitor->hasPermission('icStockMarket', 'view'))
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];

		// Fetch recent trades
		$db = \XF::db();
		$trades = $db->fetchAll("
			SELECT 
				t.trade_id,
				t.account_id,
				t.symbol_id,
				t.quantity,
				t.price,
				t.trade_type as type,
				t.trade_date,
				u.user_id,
				u.username,
				s.symbol,
				s.company_name,
				m.market_code
			FROM xf_ic_sm_trade t
			INNER JOIN xf_ic_sm_account a ON a.account_id = t.account_id
			INNER JOIN xf_user u ON u.user_id = a.user_id
			INNER JOIN xf_ic_sm_symbol s ON s.symbol_id = t.symbol_id
			INNER JOIN xf_ic_sm_market m ON m.market_id = s.market_id
			ORDER BY t.trade_date DESC
			LIMIT ?
		", $limit);

		if (empty($trades))
		{
			return '';
		}

		$viewParams = [
			'title' => $this->getTitle() ?: \XF::phrase('ic_sm_latest_trades'),
			'trades' => $trades
		];
		
		return $this->renderer('ic_sm_widget_latest_trades', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint'
		]);
		
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}
		
		if ($options['limit'] > 50)
		{
			$options['limit'] = 50;
		}

		return true;
	}
}
