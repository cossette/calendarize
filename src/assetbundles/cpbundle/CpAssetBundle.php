<?php
/**
 * Calendarize plugin for Craft CMS 3.x
 *
 * Calendar element types
 *
 * @link      https://union.co
 * @copyright Copyright (c) 2018 Franco Valdes
 */

namespace unionco\calendarize\assetbundles\cpbundle;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Franco Valdes
 * @package   Calendarize
 * @since     1.0.0
 */
class CpAssetBundle extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@unionco/calendarize/assetbundles/cpbundle/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/main.js',
        ];

        $this->css = [
            'css/app.css',
        ];

        parent::init();
    }
}
