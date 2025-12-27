<?php

namespace IC\StockMarket\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Tools extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('stockMarket');
	}

	public function actionIndex()
	{
		$viewParams = [];
		return $this->view('IC\StockMarket:Tools\Index', 'ic_sm_tools', $viewParams);
	}

	public function actionRebuildAchievements()
	{
		if ($this->isPost())
		{
			try
			{
				/** @var \IC\StockMarket\Repository\Achievement $achievementRepo */
				$achievementRepo = $this->repository('IC\StockMarket:Achievement');
				
				$count = $achievementRepo->rebuildAchievementUserCache();
				
				if ($count == 0)
				{
					return $this->message(\XF::phrase('ic_sm_no_achievements_to_rebuild'));
				}
				
				return $this->message(\XF::phrase('ic_sm_achievements_rebuilt', ['count' => $count]));
			}
			catch (\XF\Db\Exception $e)
			{
				return $this->error(\XF::phrase('ic_sm_rebuild_error', ['error' => $e->getMessage()]));
			}
		}
		else
		{
			$viewParams = [];
			return $this->view('IC\StockMarket:Tools\RebuildAchievements', 'ic_sm_tools_rebuild_achievements', $viewParams);
		}
	}
}
