<?php
/**
 * Calendarize plugin for Craft CMS 3.x
 *
 * Calendar element types
 *
 * @link      https://union.co
 * @copyright Copyright (c) 2018 Franco Valdes
 */

namespace unionco\calendarize\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\Entry;
use craft\elements\MatrixBlock;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use DateTime;
use DateTimeZone;
use unionco\calendarize\Calendarize;
use unionco\calendarize\fields\CalendarizeField;
use unionco\calendarize\models\CalendarizeModel;
use unionco\calendarize\models\Occurence;
use unionco\calendarize\records\CalendarizeRecord;

/**
 * @author    Franco Valdes
 * @package   Calendarize
 * @since     1.0.0
 */
class CalendarizeService extends Component
{
	// Private Properties
    // =========================================================================

    /** @var CalendarModel[] */
	private $entryCache = [];
	
    // Public Methods
    // =========================================================================

	/**
	 * 
	 */
	public function weekMonthText($date): string 
	{
		if (!$date) return '';
		$prefixes = ['First', 'Second', 'Third', 'Fourth', 'Last'];
		return $prefixes[floor($date->format('j') / 7)] . ' ' . $date->format('l');
	}

    /**
     * 
     */
    public function weekOfMonth($date): string
    {
		if (!$date) return '';
		$prefixes = [1, 2, 3, 4, -1];
        return $prefixes[floor($date->format('j') / 7)];
    }

    /**
     * 
     */
    public function nth($d)
    {
        if ($d > 3 && $d < 21) return 'th';
        switch ($d % 10) {
            case 1: return "st";
            case 2: return "nd";
            case 3: return "rd";
            default: return "th";
        }
	}
	
	/**
	 * Get entries with future occurence of date
	 * 
	 * @param date string|date
	 * @param criteria mixed
	 * @param order string
	 * 
	 * @return occurances array
	 */
	public function after($date, $criteria = [], $order = 'asc')
	{
		if (is_string($date)) {
			$date = DateTimeHelper::toDateTime(new DateTime($date, new DateTimeZone(Craft::$app->getTimeZone())));
		}
		
		// cant use limit in the normal criteria method, store it and unset it
		if (isset($criteria['limit'])) {
			$limit = $criteria['limit'];
			unset($criteria['limit']);
		}

		$entries = $this->_entries($criteria);
		$allOccurences = [];

		foreach ($entries as $key => $entry) {
			$fields = $entry->getFieldLayout()->getFields();
			$fieldIndex = array_search(CalendarizeField::class, array_map(function ($field) { return get_class($field); }, $fields));
			$fieldHandle = $fields[$fieldIndex]->handle;

			if (!$entry->{$fieldHandle}->repeats) {
				$allOccurences[] = new Occurence($entry, $entry->{$fieldHandle}->startDate);
			}
			
			$occurences = $entry->{$fieldHandle}->getOccurrencesBetween($date, null, null);
			
			if ($occurences) {
				foreach ($occurences as $key => $occurence) {
					$allOccurences[] = $occurence;
				}
			}
		}

		// order them
		$allOccurences = $this->sort($allOccurences, strtolower($order));

		// if limit is applied, apply it after the sort to get the right ordered entries
		if (isset($limit)) {
			$allOccurences = array_splice($allOccurences, 0, $limit);
		}

		return $allOccurences;
	}

	/**
	 * Get entries with future occurence of date
	 * 
	 * @param start string|date
	 * @param end string|date
	 * @param criteria mixed
	 * @param order string
	 * 
	 * @return occurances array
	 */
	public function between($start, $end, $criteria = [], $order = 'asc')
	{
		if (is_string($start)) {
			$start = DateTimeHelper::toDateTime(new DateTime($start, new DateTimeZone(Craft::$app->getTimeZone())));
		}

		if (is_string($end)) {
			$end = DateTimeHelper::toDateTime(new DateTime($end, new DateTimeZone(Craft::$app->getTimeZone())));
		}
		
		// cant use limit in the normal criteria method, store it and unset it
		if (isset($criteria['limit'])) {
			$limit = $criteria['limit'];
			unset($criteria['limit']);
		}

		$entries = $this->_entries($criteria);
		$allOccurences = [];

		foreach ($entries as $key => $entry) {
			$fields = $entry->getFieldLayout()->getFields();
			$fieldIndex = array_search(CalendarizeField::class, array_map(function ($field) { return get_class($field); }, $fields));
			$fieldHandle = $fields[$fieldIndex]->handle;

			if (!$entry->{$fieldHandle}->repeats) {
				if ($entry->{$fieldHandle}->startDate >= $start) {
					$allOccurences[] = new Occurence($entry, $entry->{$fieldHandle}->startDate);
				}
			}
			
			$occurences = $entry->{$fieldHandle}->getOccurrencesBetween($start, $end, null);
			
			if ($occurences) {
				foreach ($occurences as $key => $occurence) {
					$allOccurences[] = $occurence;
				}
			}
		}

		// order them
		$allOccurences = $this->sort($allOccurences, strtolower($order));

		// if limit is applied, apply it after the sort to get the right ordered entries
		if (isset($limit)) {
			$allOccurences = array_splice($allOccurences, 0, $limit);
		}

		return $allOccurences;
	}

	/**
	 * Get future occurence
	 * 
	 * @param criteria mixed
	 * @param order string
	 * 
	 * @return occurances array
	 */
	public function upcoming($criteria = [], $order = 'asc')
	{
		$today = DateTimeHelper::toDateTime(new DateTime('now', new DateTimeZone(Craft::$app->getTimeZone())));
		
		return $this->after($today, $criteria, $order);
	}

	/**
	 * Get entries with future occurence
	 * 
	 * @param criteria mixed
	 * 
	 * @return entries array
	 */
	private function _entries($criteria = [])
	{
		$today = DateTimeHelper::toDateTime(new DateTime('now', new DateTimeZone(Craft::$app->getTimeZone())));
		$cacheHash = md5(($today->format('YmdH')) . (Json::encode($criteria)));
		$limit = null;
		$tableName = CalendarizeRecord::$tableName;
		$tableAlias = 'calendarize' . bin2hex(openssl_random_pseudo_bytes(5));
		$on = [
			'and',
			'[[elements.id]] = [['.$tableAlias.'.ownerId]]',
			'[[elements_sites.siteId]] = [['.$tableAlias.'.ownerSiteId]]',
		];

		if (null === $this->entryCache || !isset($this->entryCache[$cacheHash])) {
			$query = Entry::find();
			
			$query->join(
				'JOIN',
				"{$tableName} {$tableAlias}",
				$on
			);

			$query->where([
				'and',
				[
					'not', 
					[ $tableAlias . ".startDate" => null ]
				],
				[
					'or',
					[
						'and',
						[ '=', $tableAlias . ".endRepeat", 'date' ],
						[ '>=', $tableAlias . ".endRepeatDate", Db::prepareDateForDb($today) ],
					],
					[ '=', $tableAlias . ".endRepeat", 'never' ],
					[
						'and',
						[ '=', $tableAlias . ".repeats", 0 ],
						[ '>=', $tableAlias . ".startDate", Db::prepareDateForDb($today) ],
					]
				]
			]);
					
			// configure the rest of the query
			Craft::configure($query, $criteria);

			$this->entryCache[$cacheHash] = $query->all();
		}
		
		return $this->entryCache[$cacheHash];
	}

	/**
	 * Sort entries by next occurences
	 * 
	 * @param entries array
	 * 
	 * @return entries array
	 */
	protected function sort($entries, $order = 'asc')
	{
		usort($entries, function($a, $b) {
			$startA = $a->next;
			$startB = $b->next;

			if ($startA && $startB) {
				return $startA <=> $startB;
			}
		});

		if ($order === 'desc') {
			return array_reverse($entries);
		}

		return $entries;
	}

	/**
	 * Get Field
	 * 
	 * @param field CalendarizeField
	 * @param owner ElementInterface
	 * @param value mixed
	 * 
     * @return mixed
     */
    public function getField(CalendarizeField $field, ElementInterface $owner, $value)
    {
        /** @var Element $owner */
		$record = CalendarizeRecord::findOne(
			[
				'ownerId'     => $owner->id,
				'ownerSiteId' => $owner->siteId,
				'fieldId'     => $field->id,
			]
		);

		if (
			!\Craft::$app->request->isConsoleRequest
			&& \Craft::$app->request->isPost
			&& $value
		) {
			$model = new CalendarizeModel($owner, $value);
		} else if ($record) {
			$model = new CalendarizeModel($owner, $record->getAttributes());
		} else {
			$model = new CalendarizeModel($owner);
		}

		return $model;
    }

    /**
	 * Modifies the query to inject the field data
	 *
	 * @param ElementQueryInterface $query
	 * @param                       $value
	 *
	 * @return null
	 * @throws Exception
	 */
	public function modifyElementsQuery(ElementQueryInterface $query, $value)
	{
		if (!$value) return;
        /** @var ElementQuery $query */

        $tableName = CalendarizeRecord::$tableName;
		$tableAlias = 'calendarize' . bin2hex(openssl_random_pseudo_bytes(5));

		$on = [
			'and',
			'[[elements.id]] = [['.$tableAlias.'.ownerId]]',
			'[[elements_sites.siteId]] = [['.$tableAlias.'.ownerSiteId]]',
		];

		$query->query->join(
			'JOIN',
			"{$tableName} {$tableAlias}",
			$on
		);

		$query->subQuery->join(
			'JOIN',
			"{$tableName} {$tableAlias}",
			$on
		);

		return;
    }

    /**
	 * Saves the field
	 *
	 * @param CalendarizeField $field
	 * @param ElementInterface $owner
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \yii\base\InvalidConfigException
	 */
	public function saveField(CalendarizeField $field, ElementInterface $owner): bool
	{
		/** @var Element $owner */
		$locale = $owner->getSite()->language;
		/** @var Map $value */
		$value = $owner->getFieldValue($field->handle);

		$record = CalendarizeRecord::findOne(
			[
				'ownerId'     => $owner->id,
				'ownerSiteId' => $owner->siteId,
				'fieldId'     => $field->id,
			]
		);

		if (!$record) {
			$record              = new CalendarizeRecord();
			$record->ownerId     = $owner->id;
			$record->ownerSiteId = $owner->siteId;
			$record->fieldId     = $field->id;
		}

        // base
		$record->startDate      = Db::prepareDateForDb($value->startDate);
        $record->endDate        = Db::prepareDateForDb($value->endDate);
        $record->repeats        = (bool) $value->repeats;
		$record->allDay         = (bool) $value->allDay;
		
		if ($record->repeats) {
			$record->endRepeat      = $value->endRepeat ?? null;
			$record->repeatType     = $value->repeatType ?? null;
			$record->days           = Json::encode($value->days ?? []);
			$record->months         = $value->months ?? null;

			if (isset($value->endRepeatDate)) {
				$record->endRepeatDate = Db::prepareDateForDb($value->endRepeatDate);
			}

			if (isset($value->exceptions)) {
				$record->exceptions = Json::encode(array_map(function ($exception) use($value) {
					return Db::prepareDateForDb($exception);
				}, $value->exceptions ?? []));
			}
			
			if (isset($value->timeChanges)) {
				$record->timeChanges = Json::encode(array_map(function ($timeChange) use($value) {
					return Db::prepareDateForDb($timeChange);
				}, $value->timeChanges ?? []));
			}
		} else {
			$record->endRepeat      = null;
			$record->endRepeatDate  = null;
			$record->repeatType     = null;
			$record->days           = null;
			$record->months         = null;
			$record->timeChanges    = null;
		}

		$save = $record->save();

		if (!$save) {
			Craft::getLogger()->log(
				$record->getErrors(),
				LOG_ERR,
				'calendarize'
			);
		}
		
		return $save;
	}
}
