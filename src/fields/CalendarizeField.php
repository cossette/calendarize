<?php
/**
 * Calendarize plugin for Craft CMS 3.x
 *
 * Calendar element types
 *
 * @link      https://union.co
 * @copyright Copyright (c) 2018 Franco Valdes
 */

namespace unionco\calendarize\fields;

use Craft;
use craft\i18n\Locale;
use craft\base\Element;
use craft\base\Field;
use craft\base\ElementInterface;
use unionco\calendarize\Calendarize;
use craft\base\PreviewableFieldInterface;
use craft\elements\db\ElementQueryInterface;
use unionco\calendarize\assetbundles\cpbundle\CpAssetBundle;
use unionco\calendarize\gql\types\CalendarizeType;

/**
 * @author    Franco Valdes
 * @package   Calendarize
 * @since     1.0.0
 */
class CalendarizeField extends Field implements PreviewableFieldInterface
{
    // Public Properties
    // =========================================================================

    /**
     * @var datetime
     */
    public $startDate;

    /**
     * @var datetime
     */
    public $endDate;

    /**
     * @var boolean
     */
    public $repeats = false;

    /**
     * @var boolean
     */
    public $allDay = false;

    /**
     * @var array
     */
    public $days = [];

    /**
     * @var string
     */
    public $endRepeat = NULL;

    /**
     * @var datetime
     */
    public $endRepeatDate = NULL;

    /**
     * @var array
     */
    public $exceptions = [];

    /**
     * @var array
     */
    public $timeChanges = [];

    /**
     * @var string
     */
    public $repeatType = NULL;

    /**
     * @var string
     */
    public $months = null;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('calendarize', 'Calendarize');
    }

    /**
	 * @inheritdoc
	 */
	public static function hasContentColumn(): bool
	{
		return false;
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        return $rules;
    }

    /**
	 * @inheritdoc
	 */
	public function getElementValidationRules(): array
	{
		return [
			[CalendarizeValidator::class, 'on' => Element::SCENARIO_LIVE],
		];
	}

    /**
     * @inheritdoc
     */
    public function getContentGqlType(): array|\GraphQL\Type\Definition\Type
    {
        return CalendarizeType::getType();
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ElementInterface $element = null): mixed
    {
        return Calendarize::$plugin->calendar->getField($this, $element, $value);
    }

    /**
	 * @inheritdoc
	 */
	public function modifyElementsQuery(ElementQueryInterface $query, mixed $value): void
	{
		// For whatever reason, this function can be
		// run BEFORE Calendarize has been initialized
		if (!Calendarize::$plugin) {
            return;
        }

		Calendarize::$plugin->calendar->modifyElementsQuery($this, $query, $value);

		return;
    }

    /**
	 * @inheritdoc
	 */
	public function afterElementSave(ElementInterface $element, bool $isNew): void
	{
		Calendarize::$plugin->calendar->saveField($this, $element);
		parent::afterElementSave($element, $isNew);
    }

    /**
     * @inheritdoc
     */
    public function getTableAttributeHtml(mixed $value, ElementInterface $element): string
    {
        if (empty($value->startDate) && empty($value->endDate)) {
            return '-';
        }

        $formatter = Craft::$app->getFormatter();
        $hr = $value->readable(['locale' => Craft::$app->locale->id]);
        $html = "<span title=\"{$hr}\">";

        $html .= $formatter->asDatetime($value->next()->format('U'), Locale::LENGTH_LONG);
        $html .= "<br/><span style=\"text-transform: capitalize\">" . $hr . "</span></span>";

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml(mixed $value, ElementInterface $element = null): string
    {
        // Register our asset bundle
        $view = Craft::$app->getView();

        // Get our id and namespace
        $id = $view->formatInputId($this->handle);
        $namespacedId = $view->namespaceInputId($id);
        $locale = Craft::$app->getLocale()->id;
        $dateFormat = Craft::$app->getLocale()->getDateFormat(Locale::LENGTH_MEDIUM);

        $view->registerAssetBundle(CpAssetBundle::class);
        $view->registerJs("new Calendarize('{$namespacedId}', '{$locale}', '{$dateFormat}');");

        // Render the input template
        return $view->renderTemplate(
            'calendarize/_components/fields/CalendarizeField_input',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'id' => $id,
                'namespacedId' => $namespacedId,
                'settings' => Calendarize::$plugin->getSettings()
            ]
        );
    }
}
