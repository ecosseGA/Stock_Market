<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int user_achievement_id
 * @property int user_id
 * @property int achievement_id
 * @property int earned_date
 * @property int xp_awarded
 * @property int|null season_id
 * @property int|null account_id
 * @property array|null progress_data
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \IC\StockMarket\Entity\Achievement Achievement
 * @property \IC\StockMarket\Entity\Season Season
 * @property \IC\StockMarket\Entity\Account Account
 */
class UserAchievement extends Entity
{
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			// Don't update user_stats - using career table instead
			// $this->updateUserStats();
			
			// Don't send alert here - Achievement service handles it
			// $this->sendAchievementAlert();
			
			// Award trophy if configured
			$this->awardTrophy();
			
			// Award badge if configured
			$this->awardBadge();
			
			// Award credits if configured
			$this->awardCredits();
		}
	}

	protected function updateUserStats()
	{
		$stats = $this->em()->findOne('IC\StockMarket:UserStats', [
			'user_id' => $this->user_id,
			'season_id' => $this->season_id ?? 0
		]);

		if (!$stats)
		{
			$stats = $this->em()->create('IC\StockMarket:UserStats');
			$stats->user_id = $this->user_id;
			$stats->season_id = $this->season_id ?? 0;
			$stats->last_updated = \XF::$time;
		}

		$stats->achievement_points += $this->Achievement->points;
		$stats->save();
	}

	protected function sendAchievementAlert()
	{
		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->alertFromUser(
			$this->User,
			$this->User,
			'ic_sm_achievement',
			$this->user_achievement_id,
			'earned'
		);
	}

	protected function awardTrophy()
	{
		if (!$this->Achievement->trophy_id || !\XF::isAddOnActive('XF'))
		{
			return;
		}

		/** @var \XF\Repository\Trophy $trophyRepo */
		$trophyRepo = $this->repository('XF:Trophy');
		$trophy = $this->em()->find('XF:Trophy', $this->Achievement->trophy_id);
		
		if ($trophy)
		{
			$trophyRepo->awardTrophyToUser($trophy, $this->User);
		}
	}

	protected function awardBadge()
	{
		if (!$this->Achievement->badge_id || !\XF::isAddOnActive('OzzModz/Badges'))
		{
			return;
		}

		try
		{
			$badge = $this->em()->find('OzzModz\Badges:Badge', $this->Achievement->badge_id);
			if ($badge)
			{
				/** @var \OzzModz\Badges\Service\Award $awardService */
				$awardService = $this->app()->service('OzzModz\Badges:Award', $this->User, $badge);
				$awardService->setIsAutomated();
				$awardService->setNotify(true);
				$awardService->save();
			}
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Badge awarding error: ');
		}
	}

	protected function awardCredits()
	{
		if (!$this->Achievement->credits_reward || !\XF::isAddOnActive('DBTech/Credits'))
		{
			return;
		}

		try
		{
			/** @var \DBTech\Credits\Repository\Currency $currencyRepo */
			$currencyRepo = $this->repository('DBTech\Credits:Currency');
			$currency = $currencyRepo->getDefaultCurrency();
			
			if ($currency)
			{
				/** @var \DBTech\Credits\Service\Currency\Transaction $transactionService */
				$transactionService = $this->app()->service('DBTech\Credits:Currency\Transaction', $this->User, $currency);
				$transactionService->adjustCurrencyValue(
					$this->Achievement->credits_reward,
					[
						'event' => 'achievement',
						'message' => \XF::phrase('ic_sm_achievement_earned_x', ['title' => $this->Achievement->title])->render()
					]
				);
			}
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, 'Credits awarding error: ');
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_user_achievement';
		$structure->shortName = 'IC\StockMarket:UserAchievement';
		$structure->primaryKey = 'user_achievement_id';
		$structure->columns = [
			'user_achievement_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'achievement_id' => ['type' => self::UINT, 'required' => true],
			'earned_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'xp_awarded' => ['type' => self::UINT, 'default' => 0],
			'season_id' => ['type' => self::UINT, 'nullable' => true],
			'account_id' => ['type' => self::UINT, 'nullable' => true],
			'progress_data' => ['type' => self::JSON_ARRAY, 'nullable' => true]
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Achievement' => [
				'entity' => 'IC\StockMarket:Achievement',
				'type' => self::TO_ONE,
				'conditions' => 'achievement_id',
				'primary' => true
			],
			'Season' => [
				'entity' => 'IC\StockMarket:Season',
				'type' => self::TO_ONE,
				'conditions' => 'season_id'
			],
			'Account' => [
				'entity' => 'IC\StockMarket:Account',
				'type' => self::TO_ONE,
				'conditions' => 'account_id'
			]
		];

		return $structure;
	}
}
