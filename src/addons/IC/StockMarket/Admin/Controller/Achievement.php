<?php

namespace IC\StockMarket\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Achievement extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('stockMarket');
	}

	public function actionIndex()
	{
		$achievementRepo = $this->getAchievementRepo();
		
		$finder = $achievementRepo->findAchievementsForList();
		$achievements = $finder->fetch();
		
		// Group by category
		$grouped = $achievements->groupBy('achievement_category');
		
		$viewParams = [
			'achievements' => $achievements,
			'grouped' => $grouped,
			'total' => $achievements->count()
		];
		
		return $this->view('IC\StockMarket:Achievement\List', 'ic_sm_achievement_list', $viewParams);
	}

	protected function achievementAddEdit(\IC\StockMarket\Entity\Achievement $achievement)
	{
		$categories = [
			'trading' => \XF::phrase('ic_sm_achievement_category.trading'),
			'portfolio' => \XF::phrase('ic_sm_achievement_category.portfolio'),
			'profit' => \XF::phrase('ic_sm_achievement_category.profit'),
			'diversity' => \XF::phrase('ic_sm_achievement_category.diversity'),
			'holding' => \XF::phrase('ic_sm_achievement_category.holding'),
			'performance' => \XF::phrase('ic_sm_achievement_category.performance'),
			'social' => \XF::phrase('ic_sm_achievement_category.social')
		];
		
		$difficultyTiers = [
			'easy' => \XF::phrase('ic_sm_difficulty_easy'),
			'medium' => \XF::phrase('ic_sm_difficulty_medium'),
			'hard' => \XF::phrase('ic_sm_difficulty_hard'),
			'very_hard' => \XF::phrase('ic_sm_difficulty_very_hard'),
			'epic' => \XF::phrase('ic_sm_difficulty_epic'),
			'legendary' => \XF::phrase('ic_sm_difficulty_legendary')
		];
		
		$viewParams = [
			'achievement' => $achievement,
			'categories' => $categories,
			'difficultyTiers' => $difficultyTiers
		];
		
		return $this->view('IC\StockMarket:Achievement\Edit', 'ic_sm_achievement_edit', $viewParams);
	}

	public function actionEdit(ParameterBag $params)
	{
		$achievement = $this->assertAchievementExists($params->achievement_id);
		return $this->achievementAddEdit($achievement);
	}

	public function actionAdd()
	{
		$achievement = $this->em()->create('IC\StockMarket:Achievement');
		return $this->achievementAddEdit($achievement);
	}

	protected function achievementSaveProcess(\IC\StockMarket\Entity\Achievement $achievement)
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'achievement_key' => 'str',
			'achievement_category' => 'str',
			'xp_points' => 'uint',
			'difficulty_tier' => 'str',
			'points' => 'uint',
			'display_order' => 'uint',
			'is_active' => 'bool'
		]);
		
		$form->basicEntitySave($achievement, $input);
		
		// Get phrase inputs
		$phraseInput = $this->filter([
			'title' => 'str',
			'description' => 'str'
		]);
		
		// Save phrases AFTER achievement is saved (so achievement_key is available)
		$form->apply(function() use ($achievement, $phraseInput) {
			// Save title phrase
			$titlePhraseName = 'ic_sm_achievement_title.' . $achievement->achievement_key;
			$titlePhrase = $this->em()->findOne('XF:Phrase', [
				'language_id' => 0,
				'title' => $titlePhraseName
			]);
			
			if (!$titlePhrase)
			{
				$titlePhrase = $this->em()->create('XF:Phrase');
				$titlePhrase->title = $titlePhraseName;
				$titlePhrase->language_id = 0;
				$titlePhrase->addon_id = 'IC/StockMarket';
			}
			$titlePhrase->phrase_text = $phraseInput['title'];
			$titlePhrase->save();
			
			// Save description phrase
			$descPhraseName = 'ic_sm_achievement_desc.' . $achievement->achievement_key;
			$descPhrase = $this->em()->findOne('XF:Phrase', [
				'language_id' => 0,
				'title' => $descPhraseName
			]);
			
			if (!$descPhrase)
			{
				$descPhrase = $this->em()->create('XF:Phrase');
				$descPhrase->title = $descPhraseName;
				$descPhrase->language_id = 0;
				$descPhrase->addon_id = 'IC/StockMarket';
			}
			$descPhrase->phrase_text = $phraseInput['description'];
			$descPhrase->save();
		});
		
		return $form;
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();

		if ($params->achievement_id)
		{
			$achievement = $this->assertAchievementExists($params->achievement_id);
		}
		else
		{
			$achievement = $this->em()->create('IC\StockMarket:Achievement');
		}

		$this->achievementSaveProcess($achievement)->run();

		return $this->redirect($this->buildLink('stock-market/achievements') . $this->buildLinkHash($achievement->achievement_id));
	}

	public function actionDelete(ParameterBag $params)
	{
		$achievement = $this->assertAchievementExists($params->achievement_id);
		
		if ($this->isPost())
		{
			$achievement->delete();
			
			return $this->redirect($this->buildLink('stock-market/achievements'));
		}
		else
		{
			$viewParams = [
				'achievement' => $achievement
			];
			return $this->view('IC\StockMarket:Achievement\Delete', 'ic_sm_achievement_delete', $viewParams);
		}
	}

	public function actionToggle()
	{
		/** @var \XF\ControllerPlugin\Toggle $plugin */
		$plugin = $this->plugin('XF:Toggle');
		return $plugin->actionToggle('IC\StockMarket:Achievement', 'is_active');
	}

	/**
	 * @param string $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \IC\StockMarket\Entity\Achievement
	 */
	protected function assertAchievementExists($id, $with = null, $phraseKey = null)
	{
		return $this->assertRecordExists('IC\StockMarket:Achievement', $id, $with, $phraseKey);
	}

	/**
	 * @return \IC\StockMarket\Repository\Achievement
	 */
	protected function getAchievementRepo()
	{
		return $this->repository('IC\StockMarket:Achievement');
	}
}
