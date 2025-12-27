<?php

namespace IC\StockMarket\Alert;

use XF\Alert\AbstractHandler;
use XF\Mvc\Entity\Entity;

class Achievement extends AbstractHandler
{
	public function getEntityWith()
	{
		return ['Achievement'];
	}
	
	public function getOptOutActions()
	{
		return ['earned'];
	}
	
	public function getOptOutDisplayOrder()
	{
		return 1000;
	}
	
	public function canViewContent(Entity $entity, &$error = null)
	{
		// Achievements are always viewable by the user who earned them
		return true;
	}
}
