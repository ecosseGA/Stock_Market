<?php

namespace IC\StockMarket\Widget;

use XF\Widget\AbstractWidget;

class TopMovers extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5,
		'show_type' => 'both' // both, gainers, losers
	];

	public function render()
	{
		$visitor = \XF::visitor();
		
		if (!$visitor->hasPermission('icStockMarket', 'view'))
		{
			return '';
		}

		$options = $this->options;
		$limit = $options['limit'];
		$showType = $options['show_type'];

		$db = \XF::db();
		
		// Get top gainers
		$gainers = [];
		if ($showType == 'both' || $showType == 'gainers')
		{
			$gainers = $db->fetchAll("
				SELECT 
					s.symbol_id,
					s.symbol,
					s.company_name,
					q.price,
					q.change_amount,
					q.change_percent,
					m.market_code
				FROM xf_ic_sm_symbol s
				INNER JOIN xf_ic_sm_quote q ON q.symbol_id = s.symbol_id
				INNER JOIN xf_ic_sm_market m ON m.market_id = s.market_id
				WHERE q.change_percent > 0
				ORDER BY q.change_percent DESC
				LIMIT ?
			", $limit);
		}

		// Get top losers
		$losers = [];
		if ($showType == 'both' || $showType == 'losers')
		{
			$losers = $db->fetchAll("
				SELECT 
					s.symbol_id,
					s.symbol,
					s.company_name,
					q.price,
					q.change_amount,
					q.change_percent,
					m.market_code
				FROM xf_ic_sm_symbol s
				INNER JOIN xf_ic_sm_quote q ON q.symbol_id = s.symbol_id
				INNER JOIN xf_ic_sm_market m ON m.market_id = s.market_id
				WHERE q.change_percent < 0
				ORDER BY q.change_percent ASC
				LIMIT ?
			", $limit);
		}

		if (empty($gainers) && empty($losers))
		{
			return '';
		}

		$viewParams = [
			'title' => $this->getTitle() ?: \XF::phrase('ic_sm_top_movers'),
			'gainers' => $gainers,
			'losers' => $losers,
			'showType' => $showType
		];
		
		return $this->renderer('ic_sm_widget_top_movers', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'limit' => 'uint',
			'show_type' => 'str'
		]);
		
		if ($options['limit'] < 1)
		{
			$options['limit'] = 1;
		}
		
		if ($options['limit'] > 20)
		{
			$options['limit'] = 20;
		}

		if (!in_array($options['show_type'], ['both', 'gainers', 'losers']))
		{
			$options['show_type'] = 'both';
		}

		return true;
	}
}
