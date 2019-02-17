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
use craft\helpers\FileHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use DateTime;
use DateTimeZone;
use unionco\calendarize\Calendarize;
use unionco\calendarize\fields\CalendarizeField;
use unionco\calendarize\models\CalendarizeModel;
use unionco\calendarize\models\Occurrence;
use unionco\calendarize\records\CalendarizeRecord;

/**
 * @author    Franco Valdes
 * @package   Calendarize
 * @since     1.0.0
 */
class ICS extends Component
{
    /**
	 * 
	 */
	public function make(CalendarizeModel $model)
	{
        $owner = $model->getOwner();
        $rule = $model->rrule()->getRRules()[0];

		$cal = "BEGIN:VCALENDAR\n".
                "VERSION:2.0\n".
                "PRODID:-//CALENDARIZE Craft //EN\n".
                "BEGIN:VEVENT\n".
                $rule->rfcString()."\n".
                "DTEND;TZID=". $model->endDate->getTimezone()->getName().":". $model->endDate->format('Ymd\THis') ."\n".
                "SUMMARY:".$this->_escapeString($owner->title)."\n".
                "DESCRIPTION:\n".
                "URL;VALUE=URI:".$owner->url."\n".
                "UID:".uniqid()."\n".
                "DTSTAMP:".$this->_dateToCal()."\n".
                "END:VEVENT\n".
                "END:VCALENDAR\n";
        
        $storage = Craft::$app->getPath()->getStoragePath();
        $path = $storage . "/calendarize/" . $owner->slug . ".ics";
        $file = FileHelper::writeToFile($path, $cal);
        
        return $path;
    }
    
    /**
	 * Generate the specific date markup for a ics file
	 * 
	 * @param  integer $timestamp Timestamp to be transformed
	 * @return string
	 */
	private function _dateToCal(DateTime $dateTime = null)
	{
        if (!$dateTime) {
            $dateTime = new DateTime('now');
        }

        return $dateTime->setTimezone(new \DateTimeZone("UTC"))->format('Ymd\THis\Z');
	}
	/**
	 * Escape characters
	 * 
	 * @param  string $string String to be escaped
	 * @return string
	 */
	private function _escapeString($string)
	{
		return preg_replace('/([\,;])/','\\\$1', ($string) ? $string : '');
	}
}