<?php

namespace IC\StockMarket\Widget;

use XF\Widget\AbstractWidget;

class LeaderboardPreview extends AbstractWidget
{
	protected $defaultOptions = [
		'limit' => 5
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

		// Get current active season
		$db = \XF::db();
		$season = $db->fetchRow("
			SELECT season_id, season_name as name
			FROM xf_ic_sm_season
			WHERE is_active = 1
			LIMIT 1
		");

		if (!$season)
		{
			return '';
		}

		// Get top traders for current season - ranked by PORTFOLIO VALUE only
		$leaders = $db->fetchAll("
			SELECT 
				a.user_id,
				a.cash_balance,
				u.username,
				COALESCE(portfolio_value.total_value, 0) as total_value
			FROM xf_ic_sm_account a
			INNER JOIN xf_user u ON u.user_id = a.user_id
			LEFT JOIN (
				SELECT 
					p.account_id,
					SUM(p.quantity * q.price) as total_value
				FROM xf_ic_sm_position p
				INNER JOIN xf_ic_sm_quote q ON q.symbol_id = p.symbol_id
				GROUP BY p.account_id
			) portfolio_value ON portfolio_value.account_id = a.account_id
			WHERE a.season_id = ?
			ORDER BY total_value DESC
			LIMIT ?
		", [$season['season_id'], $limit]);

		if (empty($leaders))
		{
			return '';
		}
		
		// Add rank to each leader
		$rank = 1;
		foreach ($leaders as &$leader) {
			$leader['rank'] = $rank++;
		}

		$viewParams = [
			'title' => $this->getTitle() ?: \XF::phrase('ic_sm_leaderboard_preview'),
			'leaders' => $leaders,
			'seasonName' => $season['name'],
			'link' => \XF::app()->router('public')->buildLink('stock-market/leaderboard')
		];
		
		return $this->renderer('ic_sm_widget_leaderboard_preview', $viewParams);
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
		
		if ($options['limit'] > 10)
		{
			$options['limit'] = 10;
		}

		return true;
	}
}
