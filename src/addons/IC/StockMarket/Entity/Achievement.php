<?php

namespace IC\StockMarket\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int achievement_id
 * @property string achievement_key
 * @property string achievement_category
 * @property int xp_points
 * @property string difficulty_tier
 * @property int points
 * @property int credits_reward
 * @property int|null trophy_id
 * @property int|null badge_id
 * @property bool is_repeatable
 * @property bool is_active
 * @property int display_order
 *
 * GETTERS
 * @property string title
 * @property string description
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property \XF\Entity\Phrase MasterDescription
 */
class Achievement extends Entity
{
	/**
	 * Get achievement title phrase
	 */
	public function getTitle()
	{
		return \XF::phrase($this->getPhraseName(true));
	}

	/**
	 * Get achievement description phrase
	 */
	public function getDescription()
	{
		return \XF::phrase($this->getPhraseName(false));
	}

	/**
	 * Get phrase name for this achievement
	 */
	public function getPhraseName($title)
	{
		return 'ic_sm_achievement_' . ($title ? 'title' : 'desc') . '.' . $this->achievement_key;
	}

	/**
	 * Get master phrase for editing
	 */
	public function getMasterPhrase($title)
	{
		$phrase = $title ? $this->MasterTitle : $this->MasterDescription;
		if (!$phrase)
		{
			$phrase = $this->_em->create('XF:Phrase');
			$phrase->title = $this->_getDeferredValue(function () use ($title) {
				return $this->getPhraseName($title);
			}, 'save');
			$phrase->language_id = 0;
			$phrase->addon_id = 'IC/StockMarket';
		}

		return $phrase;
	}

	/**
	 * Get icon for this achievement based on category
	 */
	public function getIcon()
	{
		$icons = [
			'trading' => 'fa-chart-line',
			'performance' => 'fa-trophy',
			'holding' => 'fa-gem',
			'social' => 'fa-users'
		];

		return $icons[$this->achievement_category] ?? 'fa-star';
	}

	/**
	 * Check if achievement can be deleted
	 */
	public function canDelete()
	{
		// Can always delete achievements in admin
		return true;
	}

	protected function _postSave()
	{
		// Save or update title and description phrases
		$titlePhrase = $this->getMasterPhrase(true);
		$descPhrase = $this->getMasterPhrase(false);
		
		// Phrases are separate entities, they handle their own changed detection
		if ($titlePhrase)
		{
			$titlePhrase->save();
		}
		
		if ($descPhrase)
		{
			$descPhrase->save();
		}
	}
	
	protected function _postDelete()
	{
		// Delete associated phrases
		if ($this->MasterTitle)
		{
			$this->MasterTitle->delete();
		}
		
		if ($this->MasterDescription)
		{
			$this->MasterDescription->delete();
		}
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_ic_sm_achievement';
		$structure->shortName = 'IC\StockMarket:Achievement';
		$structure->primaryKey = 'achievement_id';
		$structure->columns = [
			'achievement_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'achievement_key' => ['type' => self::STR, 'maxLength' => 50, 'required' => true, 'unique' => true],
			'achievement_category' => ['type' => self::STR, 'allowedValues' => ['trading', 'performance', 'holding', 'social', 'portfolio', 'profit', 'diversity'], 'required' => true],
			'xp_points' => ['type' => self::UINT, 'default' => 10],
			'difficulty_tier' => ['type' => self::STR, 'allowedValues' => ['easy', 'medium', 'hard', 'very_hard', 'epic', 'legendary'], 'default' => 'easy'],
			'points' => ['type' => self::UINT, 'default' => 0],
			'credits_reward' => ['type' => self::UINT, 'default' => 0],
			'trophy_id' => ['type' => self::UINT, 'nullable' => true],
			'badge_id' => ['type' => self::UINT, 'nullable' => true],
			'is_repeatable' => ['type' => self::BOOL, 'default' => false],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'display_order' => ['type' => self::UINT, 'default' => 0]
		];
		$structure->getters = [
			'title' => true,
			'description' => true
		];
		$structure->relations = [
			'MasterTitle' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'ic_sm_achievement_title.', '$achievement_key']
				]
			],
			'MasterDescription' => [
				'entity' => 'XF:Phrase',
				'type' => self::TO_ONE,
				'conditions' => [
					['language_id', '=', 0],
					['title', '=', 'ic_sm_achievement_desc.', '$achievement_key']
				]
			]
		];

		return $structure;
	}
}
